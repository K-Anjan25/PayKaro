# Deploying PayKaro on Render

PayKaro is Laravel 12 / PHP 8.3 with **no build step**: the design system is committed
at `public/assets/app.css`, there is no Vite/npm pipeline, no queue worker and no
scheduled command. A deploy is therefore: build one container, point it at a database,
set a handful of environment variables.

Three things this app adds on top of a normal Laravel deploy:

- the **statutory numbers** in `config/paykaro.php` are configuration, not code
  (`PAYKARO_BANK_RATE`, `PAYKARO_MSME_DUE_DAYS`, …);
- the **demo flag** (`PAYKARO_DEMO`) has to be `false`, or the workspace is labelled
  "Demo" and the seeded logins are printed on the sign-in page;
- there are **no file uploads** — the evidence checklist records that a document
  exists, it does not store it — so `storage:link` is not part of the deploy.

## What runs where

| Piece | On Render |
|---|---|
| Runtime | **Docker** — Render's native runtimes are node, python, ruby, go, elixir and rust; there is no PHP one. `Dockerfile` builds FrankenPHP (Caddy + PHP 8.3) serving `public/` on `$PORT`. |
| Region | `oregon`, `ohio`, `virginia`, `frankfurt` or `singapore`. There is no India region; `singapore` is the closest, and a service's region cannot be changed after creation. |
| Database | **External to the container** — the filesystem is ephemeral, so SQLite is not an option in production. The Blueprint creates a **free Render Postgres** in `singapore` and passes its private connection string to the app as `DB_URL`. Any Laravel driver works; Neon/Supabase/MySQL are a one-line change. |
| TLS | Render terminates it. The app must trust that proxy (`TRUSTED_PROXIES=*`) or it will generate `http://` URLs and drop the secure session cookie. |
| Queue / cron | Not needed — mail is synchronous and `routes/console.php` schedules nothing. |

## Deploy with the Blueprint (recommended)

`render.yaml` in the repo root is the whole deployment.

1. **New → Blueprint** in the Render dashboard, pick this repository.
2. Render reads `render.yaml` and asks for the two variables it cannot invent:

   | Key | Value |
   |-----|-------|
   | `APP_KEY` | output of `php artisan key:generate --show`, **including** the `base64:` prefix |
   | `APP_URL` | `https://<your-service>.onrender.com` |

   The database needs no input: the Blueprint creates the `paykaro-db` Postgres
   instance in the same region and maps its private `connectionString` to `DB_URL`.
3. **Apply**. The first build takes a few minutes; Render then routes traffic only once
   `GET /up` answers 200.

### The free database expires

A free Render Postgres is **deleted 30 days after it is created** (14-day grace to
upgrade), and a workspace gets one. Fine for a demo; wrong for a real book of
business. Two ways out, neither needing a code change:

- **Paid Render Postgres** — set `plan: basic-256mb` (from ~$6/mo) in the
  `databases:` block of `render.yaml` and sync.
- **External provider** — Neon and Supabase have free Postgres that does not expire.
  Delete the `databases:` block, put `DB_URL` back to `sync: false`, and paste the
  connection string. `DATABASE_URL` works too: `config/database.php` reads `DB_URL`
  first and falls back to it, and `docker/entrypoint.sh` normalises the two.

Remember to dump and reload the data when you move (`php artisan db:dump` /
`pg_dump`, or Render's own backup for a paid instance).

### If the database was not created

`render.yaml` only describes the database; a **Blueprint sync** is what creates it.
A push to the linked branch normally triggers one, but if the container starts and
the entrypoint reports `neither DB_URL nor DB_HOST is set`, no sync has happened — or
it failed. Open **Blueprints → <this blueprint> → Sync** and read what it reports:

- **"Database creation failed" and the workspace already has a free Postgres** — the
  usual cause. A workspace is limited to one free instance. Delete the old one and
  sync again, or move this database to a paid plan.
- **Nothing to sync** — the Blueprint is linked to a different branch than the one
  you pushed, or auto-sync is off. Check the Blueprint's branch setting.
- **A second, empty `paykaro-db` appeared** — a database was created by hand under a
  different name, so the Blueprint made its own. Point `DB_URL` at whichever one
  holds the data and delete the other.

The build log will *not* tell you any of this: `render.yaml`, `docs/` and `*.md` are
all excluded by `.dockerignore`, so a Blueprint-only change produces an image where
every layer is `CACHED`. A fully cached build is not evidence that nothing changed —
it is evidence that only the Blueprint changed.

## Deploy by hand

If you would rather not use the Blueprint: **New → Web Service**, runtime **Docker**,
region `singapore`, no build command, no start command (the image's `ENTRYPOINT`/`CMD`
are correct), health check path `/up`, and the environment variables below.

## Environment variables

Everything not listed here already has a production default in the image or in
`config/`.

| Key | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | `true` prints stack traces, including queries, to the browser |
| `APP_KEY` | `base64:…` | Laravel wants a `base64:`-prefixed 32-byte key. A plain generated string of the wrong length makes the encrypter throw on every request. |
| `APP_URL` | your service URL | absolute URLs, mail links, redirects |
| `TRUSTED_PROXIES` | `*` | Render terminates TLS in front of the app |
| `LOG_CHANNEL` | `stderr` | Render collects stdout/stderr into its log stream |
| `DB_CONNECTION` | `pgsql` (or `mysql`) | |
| `DB_URL` | connection string | the value your provider labels `DATABASE_URL`. Either name works — `config/database.php` reads `DB_URL` and falls back to `DATABASE_URL` — but one of them must be set, or Laravel connects to `127.0.0.1`/`laravel` and the deploy dies in the migration |
| `SESSION_DRIVER` | `database` | survives deploys and is shared between instances; the table ships in the base migration |
| `CACHE_STORE` | `database` | same reason |
| `SESSION_SECURE_COOKIE` | `true` | the site is HTTPS-only |
| `QUEUE_CONNECTION` | `sync` | nothing is queued |
| `RUN_MIGRATIONS` | `true` | `docker/entrypoint.sh` runs `php artisan migrate --force` before serving |
| `BUILD_CACHES` | `true` | `php artisan optimize` (config, routes, views) at start-up |
| `PAYKARO_DEMO` | `false` | hides the demo banner and the seeded logins |
| `PAYKARO_MSME_DUE_DAYS` | `45` | the MSMED window the interest clock starts from |
| `PAYKARO_BANK_RATE` | `5.5` | RBI bank rate; interest is `bank_rate × PAYKARO_INTEREST_MULTIPLIER` |
| `PAYKARO_INTEREST_MULTIPLIER` | `3` | |
| `PAYKARO_DEFAULT_TAX_RATE` | `18` | |
| `PAYKARO_FINANCE_READY_SCORE` | `85` | readiness threshold for the finance queue |
| `PAYKARO_ALERT_LIMIT` | `6` | |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | optional | unset means the Google button is not rendered and `/auth/google*` answers 404. The redirect URI must match the Google Cloud Console entry exactly: `https://<service>.onrender.com/auth/google/callback` |

Never run `php artisan migrate --seed` against a real book of business — the seeder
writes two demo tenants and eighteen invoices.

## Migrations

`RUN_MIGRATIONS=true` migrates on every container start, which is correct for one
instance and for a fresh environment. If you scale past one instance, turn it off and
run migrations once per release instead — two containers racing the same migration is
how a deploy half-fails. Render's **pre-deploy command** does exactly this:

```
php artisan migrate --force
```

Pre-deploy commands are only available on paid plans.

## Verify a deploy

```bash
curl -i https://<service>.onrender.com/up          # 200, empty body
curl -I  https://<service>.onrender.com/           # 200, landing page
curl -I  https://<service>.onrender.com/login      # 200
curl -I  https://<service>.onrender.com/dashboard  # 302 → /login (nothing is signed in)
```

Then sign in, create one invoice and check that: the interest figure is non-zero, the
URLs in the page are `https://`, and a `POST` (save the invoice) does not come back
`419 Page Expired`. A `419` on the first write means the session is not sticking —
`TRUSTED_PROXIES` and `SESSION_SECURE_COOKIE`.

## What the free tier actually costs

- A free web service **spins down after ~15 minutes** without traffic; the next request
  waits for a cold container. `0.5c-512mb` (paid) keeps it warm.
- The filesystem is **ephemeral** — anything written outside the database is gone at the
  next deploy. Nothing in this app needs it to persist.
- A **free Render Postgres is deleted 30 days after creation.** Use a paid instance or
  an external provider for anything real.
- Build minutes and bandwidth are metered per workspace.

## When it goes wrong

| Symptom | Cause |
|---|---|
| Build fails on `composer install` | The lock file resolves for PHP 8.3 (`composer.json` pins `config.platform.php`). The image installs 8.3; a hand-built image must too. |
| App starts, every page 500s, logs show `No supported cipher…` | `APP_KEY` is missing or is not `base64:<44 chars>` |
| `could not find driver` | `DB_CONNECTION` says `pgsql` but the URL is MySQL, or the driver is not in the image (all three PDO drivers are installed by the `Dockerfile`) |
| Container dies on boot with `[paykaro] DB_CONNECTION=pgsql but neither DB_URL nor DB_HOST is set.` | No connection string reached the container. The Blueprint maps `DB_URL` from `paykaro-db`; if you deploy by hand, set `DB_URL` or `DATABASE_URL`. |
| Container dies on boot with `connection to server at "127.0.0.1", port 5432 failed … Database: laravel` | Same cause on an older image: Laravel fell back to its own defaults. Set `DB_URL` (or `DATABASE_URL`) and redeploy. |
| The database host is unreachable, or resolves but refuses the connection | The service and the database are in different regions — `connectionString` is a private-network address. Set `region:` to the same value on both. |
| Sign-in succeeds and then bounces back to `/login` | session not sticking: `SESSION_DRIVER=database` and the `sessions` table exists; `TRUSTED_PROXIES` set |
| Health check fails, deploy marked unhealthy | `healthCheckPath` must be `/up`; a 500 there is a missing `APP_KEY` or a database it cannot reach |
| Sign-in page shows "Demo workspace" and the demo logins | `PAYKARO_DEMO` is still `true` |

## Local parity

The same image runs on a laptop:

```bash
docker build -t paykaro .
docker run --rm -p 8080:8080 --env-file .env -e RUN_MIGRATIONS=true paykaro
```
