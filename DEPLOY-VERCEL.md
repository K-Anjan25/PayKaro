# Deploying PayKaro on Vercel — step by step

Vercel can host this app: the repo already ships `vercel.json` + `api/index.php`
(serverless PHP 8.3 via the `vercel-php` runtime). One thing is not optional —
**Vercel has no persistent disk**, so the app needs an **external Postgres**. The
local zero-config SQLite file cannot be used here.

Budget **20–30 minutes**. You need no credit card for Vercel Hobby or Neon free.

| | |
|---|---|
| Cost | $0 on Hobby + Neon free |
| URL | `https://<project>.vercel.app` (TLS automatic, no domain needed) |
| Region | `bom1` (Mumbai) for the function |
| Database | Neon Postgres (free: 1 GB, auto-wakes after ~0.5 s) |
| Cold start | ~250 ms |
| **Hobby plan is non-commercial** | A product with paying customers needs Pro — decide before you point customers at it |

---

## Step 0 · What you need

- The repo with this work merged to **`main`** — PR #15
  (<https://github.com/K-Anjan25/PayKaro/pull/15>). If it is not merged yet, know
  that Vercel deploys a *branch* just as well: you can import the repository and
  set the production branch to `arena/7126d4e5-paykaro`.
- A **Vercel** account (<https://vercel.com/signup>, sign in with GitHub).
- A **Neon** account (<https://neon.com>, free, no card).
- `openssl` (preinstalled on macOS/Linux; Git Bash on Windows) to generate a key.
- *Optional:* a PHP 8.3 machine, only if you prefer to run migrations locally
  instead of from GitHub Actions (Step 4A vs 4B).

---

## Step 1 · Create the database (Neon)

1. Neon console → **New project**.
2. Name `paykaro`, and pick the region closest to `bom1`: **Singapore
   (`ap-southeast-1`)**. If Neon offers an India region for your account, take it.
3. Copy the **direct** connection string (the panel shows *Pooled* and *direct*;
   choose **direct**, and if you only see "Connect", turn the *Pooled connection*
   switch **off**).

It looks like:

```
postgresql://neondb_owner:PASSWORD@ep-cool-name-123456.ap-southeast-1.aws.neon.tech/neondb?sslmode=require
```

> Use the **direct** host, not the `-pooler` one. PgBouncer in transaction mode
> breaks PDO's prepared statements, and Laravel uses those by default.

The schema is created in Step 4 — not by the app.

---

## Step 2 · Generate an application key

`APP_KEY` encrypts cookies and sessions. Generate it **once** and keep it; changing
it later signs every user out.

```bash
echo "base64:$(openssl rand -base64 32)"
```

Windows PowerShell equivalent:

```powershell
"base64:" + [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Max 256 }))
```

Copy the whole line, including `base64:`.

---

## Step 3 · Create the schema (migrations)

Pick **one** route. Both run `php artisan migrate` against Neon — never
`migrate --seed` (that creates the two demo tenants).

### 3A · From GitHub Actions (recommended — nothing to install)

Add this workflow to the repo as `.github/workflows/migrate.yml`:

```yaml
name: Migrate production database

on:
  workflow_dispatch:          # manual, from the Actions tab — never on every push

jobs:
  migrate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_pgsql, mbstring, xml, curl, zip, intl
          coverage: none

      - run: composer install --no-dev --no-interaction --prefer-dist --no-progress

      - name: Migrate
        run: php artisan migrate --force
        env:
          APP_ENV: production
          APP_KEY: ${{ secrets.APP_KEY }}
          DB_CONNECTION: pgsql
          DB_URL: ${{ secrets.DATABASE_URL }}
          DB_SSLMODE: require
```

Then: repo → **Settings → Secrets and variables → Actions → New repository
secret**, twice:

| Secret | Value |
|---|---|
| `APP_KEY` | the `base64:…` line from Step 2 |
| `DATABASE_URL` | the Neon **direct** connection string |

Push the workflow, open **Actions → Migrate production database → Run workflow**.
Green run = tables exist. Re-run it whenever you deploy code containing a new
migration.

### 3B · From your own machine (if you have PHP 8.3)

```bash
composer install
APP_ENV=production \
APP_KEY="base64:…" \
DB_CONNECTION=pgsql \
DB_URL='postgresql://…neon.tech/neondb' \
DB_SSLMODE=require \
php artisan migrate --force
```

(Later: `php artisan migrate --force` again after pulling a release with a schema
change.)

---

## Step 4 · Import the project into Vercel

1. <https://vercel.com/new> → **Import Git Repository** → pick **PayKaro**
   (grant access if asked).
2. Configure:
   - **Framework Preset:** *Other* (leave it — `vercel.json` sets `framework: null`)
   - **Root Directory:** `./`
   - **Build & Output Settings:** leave both empty; do **not** add
     `php artisan optimize` as a build command (see the note below)
   - **Environment Variables:** add the block in Step 5 *before* the first deploy
3. Click **Deploy**.

> **Why no build command:** `php artisan optimize` bakes environment variables
> into the config cache *at build time*, but Vercel injects `APP_KEY` and the
> database URL at **runtime**. A config cache built without them produces
> `http://` URLs and an empty key. The route/view caches are harmless but
> unnecessary here.

---

## Step 5 · Environment variables

Project → **Settings → Environment Variables**. Add each with
**Production** (and **Preview** if you want previews to work) ticked:

| Key | Value |
|---|---|
| `APP_NAME` | `PayKaro` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | `base64:…` from Step 2 |
| `APP_URL` | `https://<project>.vercel.app` (the exact domain Vercel gave you) |
| `APP_TIMEZONE` | `Asia/Kolkata` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | Neon **direct** connection string |
| `DB_SSLMODE` | `require` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `array` |
| `LOG_CHANNEL` | `stderr` |
| `PAYKARO_DEMO` | `false` |
| `PAYKARO_MSME_DUE_DAYS` | `45` |
| `PAYKARO_DEFAULT_TAX_RATE` | `18` |
| `PAYKARO_BANK_RATE` | `5.5` |
| `PAYKARO_INTEREST_MULTIPLIER` | `3` |
| `PAYKARO_FINANCE_READY_SCORE` | `85` |
| `MAIL_MAILER` | `log` *(switch to `smtp` + host/user/pass to actually send)* |

`api/index.php` already defaults the things that must live in `/tmp` — the
config/route/view cache paths, `LOG_CHANNEL=stderr`, `CACHE_STORE=array`,
`QUEUE_CONNECTION=sync`, `SESSION_DRIVER=database` and
`SESSION_SECURE_COOKIE=true` — but only where the project does not define them.
Setting them explicitly (as above) is the safer habit; the dashboard always wins.

**`TRUSTED_PROXIES` stays unset.** Vercel terminates TLS at its edge and
`api/index.php` already normalises the request; nothing to trust.
(`DEPLOY.md` §7 covers the proxy setting for other hosts.)

> After changing **any** variable you must **redeploy** (Deployments → ⋯ →
> Redeploy) — Vercel reads env at build/instance start.

---

## Step 6 · Verify the deployment

Replace `<project>` with your Vercel domain:

```bash
curl -sS -o /dev/null -w 'up        %{http_code}\n' https://<project>.vercel.app/up
curl -sS -o /dev/null -w 'login     %{http_code}\n' https://<project>.vercel.app/login
curl -sS -o /dev/null -w 'dashboard %{http_code}\n' https://<project>.vercel.app/dashboard
curl -sS -o /dev/null -w 'css       %{http_code}\n' https://<project>.vercel.app/assets/app.css
```

Expected: `200`, `200`, `302` (to `/login`), `200`.

In the browser, `https://<project>.vercel.app/login` must render **with styling**.
If it is unstyled white HTML, the static output is misconfigured — see the
troubleshooting table.

Check the deployment log once: **Deployments → your deployment → Functions →
`api/index.php`** should show `vercel-php@0.7.4`, PHP 8.3, region `bom1`.

---

## Step 7 · Create the real workspace

Nothing is seeded; the first business is created through the app.

1. Open `https://<project>.vercel.app/signup` → create the business + owner.
2. **Customers → Add buyer** → one customer.
3. **Invoices → New invoice** → raise one. The due date and statutory interest
   are computed (45-day MSMED window, `5.5 × 3` % compounded monthly), not typed.
4. Open the invoice → **Claim packet** → **Print**. This is the page a forum
   reads: confirm letterhead, GSTIN and the interest schedule print properly.
5. **Settings** → fill GSTIN, PAN, Udyam and bank details (the packet and the
   emails read from there).

### To actually send email

`MAIL_MAILER=log` writes messages to Vercel's runtime logs and delivers nothing.
Use any SMTP provider (Brevo/Resend free tiers are ~100–300 mails/day):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=invoices@yourdomain
MAIL_FROM_NAME=PayKaro
```

Redeploy, then send one invoice to yourself. Mail is sent **inside the request**
(there is no queue worker on Vercel), which is why `vercel.json` sets
`maxDuration: 30` — a slow SMTP handshake is the one thing that can time out.

---

## Step 8 · Shipping updates

```bash
git push origin main
```

Vercel builds and deploys automatically. If the release contains a **new
migration**, run the Actions workflow from Step 3A (or `php artisan migrate
--force` from your machine) *before* the new code takes traffic — the app fails
closed on a missing column, so order matters.

Rollback is a click: **Deployments → an older deployment → Promote to
Production**. Note that code rollback does **not** roll back the database.

---

## Step 9 · A custom domain later (optional)

1. Project → **Settings → Domains → Add** → `app.example.com`; Vercel prints the
   DNS records (usually `CNAME → cname.vercel-dns.com`).
2. Add them at your registrar, wait for **Valid Configuration** (TLS is issued
   automatically).
3. Update and redeploy: `APP_URL=https://app.example.com`.
4. If you use Google Sign-In, add
   `https://app.example.com/auth/google/callback` to the Google Cloud Console
   **and** set `GOOGLE_REDIRECT_URI` to the same value.

---

## If something breaks

| Symptom | Cause and fix |
|---|---|
| `500` — "No application encryption key has been specified" | `APP_KEY` missing, or added **after** the deployment. Add it, then **Redeploy** |
| `500` on every page, function log mentions a write path | A write outside `/tmp`. Confirm `api/index.php` is the entrypoint in `vercel.json` (it sets `storage_path()` to `/tmp/storage`) |
| Unstyled page, `assets/app.css` → `404` | Static output not picked up. Project → **Settings → Build & Deployment → Output Directory** = `public`, then redeploy |
| `500` + `could not find driver` | `DB_CONNECTION` is not `pgsql` (or a typo). The runtime ships pdo_pgsql |
| `500` + "password authentication failed" / SSL error | Use the **direct** Neon host and `DB_SSLMODE=require`; regenerate the password if it was rotated |
| `500` + `relation "sessions" does not exist` | Step 3 never ran. Migrate, then retry |
| Login succeeds, then you are signed out on the next page | `SESSION_DRIVER` is `file` (writes to `/tmp`, dies with the instance). Set `database` |
| Session/cookie dropped, redirect loops | `APP_URL` scheme or host does not match the browser, or `SESSION_SECURE_COOKIE` is on while you access over plain HTTP (Vercel is always HTTPS — check `APP_URL`) |
| `504` / gateway timeout when sending an invoice | SMTP is slow: `maxDuration` is 30 s, so a dead SMTP host stalls the request. Verify credentials, or raise `maxDuration` (Hobby caps at 60 s) |
| Pages feel slow on first hit | Cold start (~250 ms) plus a Neon wake (~0.5 s). Normal; keep one function warm by using the app |
| Build fails: "composer install" / memory | Redeploy; if it repeats, check `composer.lock` is committed and `vendor/` is in `.vercelignore` |
| Changes do not appear | Build cache: redeploy with **Use existing Build Cache** unticked, or set `VERCEL_FORCE_NO_BUILD_CACHE=1` in the project env |

Runtime logs live in **Deployments → Functions → Logs** (the app writes there via
`LOG_CHANNEL=stderr`); build logs are on the deployment page.

---

## What Vercel does not give this app

- **Persistent disk** → external Postgres, as set up above. (No uploads are stored
  by the app, so nothing else needs a volume.)
- **Queue worker / scheduler** → none today; mail is synchronous. If you later
  queue mail, add a queue service and Vercel Cron.
- **Commercial use on Hobby** → a paying-customer deployment needs **Pro**.
- **Long requests** → 30 s here; anything heavier belongs on a container host.

When you outgrow it, nothing is rewritten: the same repo ships a `Dockerfile`
(`DEPLOY.md` §13) and the Oracle Always Free walkthrough
(`DEPLOY-ORACLE.md`). Moving database is a `pg_dump`/`pg_restore` plus an
`APP_URL` change.
