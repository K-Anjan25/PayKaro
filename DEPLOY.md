# Deploying PayKaro

PayKaro is a Laravel 12 / PHP 8.3 application with **no build step**: the design
system is committed at `public/assets/app.css`, there is no Vite/npm pipeline, no
queue worker and no scheduled command. A production deployment is therefore the
classic Laravel shape — a PHP-FPM host, a database, TLS and SMTP — plus three
things this app adds on top:

- the **statutory numbers** in `config/paykaro.php` are configuration, not code
  (`PAYKARO_BANK_RATE`, `PAYKARO_MSME_DUE_DAYS`, …), so they must be set before
  the config is cached;
- the **demo flag** (`PAYKARO_DEMO`) has to be turned off, or the workspace is
  labelled "Demo" and the seeded logins are printed on the sign-in page;
- there are **no file uploads** — the evidence checklist records that a document
  exists, it does not store it (`app/Support/Legal.php`) — so `storage:link` is
  not part of the deploy.

`RUN.md` covers local development and the php-wasm sandbox; this file covers a
real host.

---

## 1. What the server needs

| Requirement | Notes |
|---|---|
| PHP **8.3** (8.4 may run it) | `composer.json` pins `config.platform.php` to 8.3.0, so the committed lock resolves for 8.3. Run the same on the server. |
| PHP extensions | `ctype curl dom fileinfo filter hash mbstring openssl pcre pdo session tokenizer xml`, plus `pdo_mysql` (or `pdo_pgsql` / `pdo_sqlite`) |
| Composer 2 | `composer install --no-dev` from the committed `composer.lock` |
| Web server | Nginx or Apache with the document root on `public/` |
| Database | MySQL 8 / MariaDB 10.6+ / PostgreSQL is recommended. SQLite works only on a **single** host with a persistent disk — see §8 |
| SMTP credentials | Default `MAIL_MAILER=log` writes invoices/reminders to `storage/logs/laravel.log` and delivers nothing |
| Node / npm | **Not required** — nothing is built at deploy time |
| Queue worker / cron | **Not required** — mail is sent synchronously and `routes/console.php` schedules nothing |

---

## 2. First-time server setup (Ubuntu + Nginx example)

```bash
sudo apt install php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl \
                 php8.3-mysql php8.3-sqlite3 nginx composer git unzip
sudo adduser --system --group --home /var/www/paykaro paykaro
```

The web server only ever needs to read `public/`; Laravel needs to write
`storage/` and `bootstrap/cache/`:

```bash
sudo chown -R paykaro:www-data /var/www/paykaro
sudo chmod -R ug+rwX /var/www/paykaro/storage /var/www/paykaro/bootstrap/cache
```

TLS is non-negotiable here — the app handles credentials and Google OAuth:

```bash
sudo certbot --nginx -d app.example.com
```

---

## 3. Get the code and configure it

```bash
sudo -u paykaro git clone https://github.com/K-Anjan25/PayKaro.git /var/www/paykaro
cd /var/www/paykaro

sudo -u paykaro composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

sudo -u paykaro cp .env.example .env
sudo -u paykaro php artisan key:generate      # writes APP_KEY — keep it forever (§10)
```

Then edit `.env`. A production file looks like this (only the values you must
decide are commented):

```dotenv
APP_NAME=PayKaro
APP_ENV=production
APP_KEY=base64:…            # generated above, never regenerated on deploy
APP_DEBUG=false
APP_URL=https://app.example.com
APP_TIMEZONE=Asia/Kolkata   # the workspace day boundary; interest/ageing read it

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paykaro
DB_USERNAME=paykaro
DB_PASSWORD=…

SESSION_DRIVER=database     # `file` is fine on one host; `database` for >1
SESSION_SECURE_COOKIE=true  # requires HTTPS (see §7 if you sit behind a proxy)
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=invoices@example.com
MAIL_FROM_NAME="PayKaro"

# Business rules — every screen and the claim packet read these, not hard-coded values.
PAYKARO_MSME_DUE_DAYS=45
PAYKARO_DEFAULT_TAX_RATE=18
PAYKARO_BANK_RATE=5.5       # RBI *Bank Rate*, reviewed whenever it changes (§10)
PAYKARO_INTEREST_MULTIPLIER=3
PAYKARO_FINANCE_READY_SCORE=85
PAYKARO_ALERT_LIMIT=6

PAYKARO_DEMO=false          # a real book of business is not a demo
PAYKARO_HEADLINE="Make every invoice count"
PAYKARO_DESCRIPTOR="MSME invoice & receivables tracker"

# Optional — omit all three to disable Google entirely (button hidden, routes 404)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://app.example.com/auth/google/callback
```

`SESSION_DRIVER=database` works out of the box: the `sessions` table ships in
`0001_01_01_000001_create_users_table.php`, as do `cache` and `jobs`.

### Migrate and cache

```bash
sudo -u paykaro php artisan migrate --force   # --force: no interactive prompt in production
sudo -u paykaro php artisan optimize          # config + routes + views + events
```

**Do not run `migrate --seed` in production.** The seeder creates two demo
tenants and is only for the zero-config demo. The first real workspace is created
through `/signup`, which provisions a business and its owner
(`App\Services\AccountProvisioner`).

`php artisan optimize` bakes `.env` into `bootstrap/cache/config.php`; re-run it
after **any** environment or `PAYKARO_*` change, or the old value stays live.

---

## 4. Web server

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name app.example.com;

    root /var/www/paykaro/public;   # the only directory that may be exposed
    index index.php;
    charset utf-8;

    ssl_certificate     /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }

    # The CSS/JS are committed, not fingerprinted — a long cache would keep
    # serving last release's stylesheet after a deploy. Keep it short or purge.
    location /assets/ {
        expires 1h;
        add_header Cache-Control "public";
    }
}
```

`.env`, `storage/`, `vendor/` and `database/` are above the document root, so
they are not reachable over HTTP. `public/.htaccess` ships with the repo for
Apache/cPanel hosts — point the vhost's `DocumentRoot` at `public/` and enable
`mod_rewrite`.

### PHP-FPM

Production opcache settings (`/etc/php/8.3/fpm/conf.d/10-opcache.ini`):

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0   # reload FPM on every deploy (§5)
```

---

## 5. Deploying a new release

```bash
cd /var/www/paykaro

sudo -u paykaro git pull --ff-only

sudo -u paykaro composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

sudo -u paykaro php artisan migrate --force
sudo -u paykaro php artisan optimize
sudo systemctl reload php8.3-fpm
```

For a few seconds of downtime instead of a possibly mixed release:

```bash
sudo -u paykaro php artisan down --secret="$(openssl rand -hex 16)"
# …the commands above…
sudo -u paykaro php artisan up
```

The `down` secret lets you browse `https://app.example.com/<secret>` while the
release runs. Migrations in this repo are additive and each has a `down()`, so a
rollback is `git checkout <previous-tag>` + `php artisan migrate:rollback --step=1`
when the release included a schema change.

**Zero-downtime alternative:** deploy into `/var/www/paykaro/releases/<sha>` and
switch a `current` symlink; Nginx keeps `root /var/www/paykaro/current/public`.
Nothing in the app writes into the release directory except `storage/` and
`bootstrap/cache/`, so make those two shared symlinks per release.

---

## 6. Verify a deploy

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://app.example.com/up          # 200
curl -sS -o /dev/null -w '%{http_code}\n' https://app.example.com/login       # 200
curl -sS -o /dev/null -w '%{http_code}\n' https://app.example.com/dashboard   # 302 → /login
```

`/up` is Laravel's built-in health route (`bootstrap/app.php`, `health: '/up'`).
It proves the app boots; it deliberately does **not** query the database — use a
business page (`/login` renders, a signed-in `/dashboard` returns 200) if you
want the DB in the check.

Then, signed in:

1. `/dashboard` renders the KPI cards; `/invoices` lists the book; `/reports` and
   `/treds` load.
2. Create one invoice; the due date is derived from `PAYKARO_MSME_DUE_DAYS` and
   the interest figure from `PAYKARO_BANK_RATE × PAYKARO_INTEREST_MULTIPLIER`.
3. `/invoices/{id}/claim` opens and **prints** — the one page where a visual bug
   is a legal one.
4. Send an invoice to a buyer with an email address to prove SMTP:

   ```bash
   sudo -u paykaro php artisan tinker --execute="Mail::raw('PayKaro SMTP test', fn (\$m) => \$m->to('ops@example.com')->subject('PayKaro SMTP test'));"
   ```

5. If Google is configured, `https://app.example.com/auth/google` completes and
   the callback URI registered in Google Cloud Console matches **exactly**.

`php artisan about` prints the environment, cache and driver state in one screen.

---

## 7. TLS, proxies and cookies

`bootstrap/app.php` does not call `trustProxies()`. On a plain Nginx host that is
correct. If you terminate TLS in front of the app (Cloudflare, an ALB, a load
balancer, a PaaS router), Laravel will see plain HTTP and:

- generate `http://` URLs from `route()`/`asset()`,
- and, with `SESSION_SECURE_COOKIE=true`, the session cookie can fail to be set.

Fix it in `bootstrap/app.php` rather than by turning the secure cookie off:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');   // or your proxy's CIDR range
    // …existing redirectGuestsTo / redirectUsersTo…
})
```

Keep `APP_URL` on the public HTTPS host regardless — it is what `og:image`,
redirects and the derived Google callback are built from.

---

## 8. More than one app server

| Concern | Single host | Multiple hosts |
|---|---|---|
| Sessions | `file` (default) | `database` (table ships) or `redis` |
| Cache | `file` | `database` or `redis` |
| Uploaded files | none — nothing to share | none |
| Database | SQLite is acceptable if the file lives on persistent disk; back it up | **MySQL/PostgreSQL** — do not put SQLite on a network share |
| Queue | `sync`; mail sends inside the request | Still `sync` unless you later queue mail; the `jobs` table exists, and then run `php artisan queue:work` under Supervisor |

There is no scheduler entry to install. If you add queued mail later, remember
SMTP timeouts currently block the request that sends an invoice.

---

## 9. Operations

- **Backups:** the database nightly (`mysqldump paykaro | gzip > …`), plus `.env`
  (it holds `APP_KEY` and SMTP/OAuth credentials) and `storage/logs/`. If the
  session driver is `file`, that directory is expendable — restoring without it
  only signs everyone out.
- **Database user:** migrations need DDL rights; you can run them as an admin
  user and give the runtime user only `SELECT/INSERT/UPDATE/DELETE`.
- **Logs:** `storage/logs/laravel.log`, rotated by logrotate; `LOG_LEVEL=warning`
  is the sane production level.
- **Maintenance page:** `php artisan down` / `up`, secret link optional.
- **PHP version changes:** the lock is resolved for 8.3. If you move the platform
  pin, run `composer update` (not `composer update --lock`) and check it with
  `node bridge/check-lock.mjs` — see `RUN.md`.

---

## 10. The two things that are easy to get wrong

**`APP_KEY`.** It signs cookies and encrypted payloads. Generate once and keep it
in the secret store. Rotating it signs every user out (and invalidates anything
encrypted); `php artisan key:generate` on every deploy is a bug.

**`PAYKARO_BANK_RATE`.** This is the RBI **Bank Rate** (5.50% since December
2025), not the repo rate, and statutory interest is `bank_rate × 3` compounded
with monthly rests. Changing it re-prices every open claim, so it is an operator
decision with a date attached, not a config tweak:

- set it before `php artisan optimize`, and note *when* the notification took
  effect;
- if a period spans a rate change, record both in
  `config/paykaro.php`'s `bank_rate_history` (`[['from' => 'Y-m-d', 'rate' => …]]`)
  so each monthly rest is charged at the rate in force on its own date;
- the dashboard, ageing chart, finance queue and claim packet all read the same
  config, so they cannot disagree — but they will all move together.

Also review `PAYKARO_MSME_DUE_DAYS` (45 by default) against any written buyer
agreement, because it is what defines "overdue" and starts the clock.

---

## 11. Where to run it

| Target | Notes |
|---|---|
| **VPS** (Ubuntu + Nginx + PHP-FPM + MySQL) | The instructions above; the most predictable |
| **Laravel Forge / Ploi** | Same `.env` and deploy script; the panel provides Nginx, TLS, PHP-FPM and queue supervision |
| **Shared hosting / cPanel** | Works: PHP 8.3, `DocumentRoot` → `public/`, `mod_rewrite` (`.htaccess` is committed), MySQL, Composer. No daemons needed |
| **Vercel** | Possible in two ways — the committed `vercel.json` + `api/index.php` (serverless PHP runtime), or the container image from §13 via Vercel's Docker/FrankenPHP path. Both need an **external database**. See §12 |
| **Fly.io / Render / Railway / DigitalOcean App Platform / Cloud Run / AWS / Azure** | The repo ships a `Dockerfile` (FrankenPHP + Caddy, one process on `$PORT`), so all of these deploy the same image — **Fly.io has a Mumbai region**, the others are Singapore/US/EU or their own India regions. See §13 for the shortlist and per-platform quickstarts |

The app itself needs nothing beyond a PHP 8.3 runtime, a writable `storage/`, and
a database — every option above is a different way of providing those three.

---

## 12. Vercel

Yes — this repo is already wired for Vercel, but it is the **second-best** target
for this app, and the trade-off is worth stating plainly before the how-to.

### What Vercel cannot give this app

Vercel Functions have **no persistent disk**. Only `/tmp` is writable, and it lasts
as long as one function instance. That rules out the zero-config default:

- **SQLite is out.** `database/paykaro.sqlite` would be recreated empty on cold
  starts, so a receivables ledger would silently lose rows. An external
  Postgres/MySQL is mandatory.
- **File sessions and the file cache are out** (they already are, in the committed
  `vercel.json` — sessions are moved off disk and the cache is in memory).
- **No queue worker, no scheduler.** PayKaro needs neither today: mail is sent
  synchronously inside the request and `routes/console.php` schedules nothing.
  If you later queue mail, that work moves to Vercel Cron + a queue service.
- **No uploaded files.** Not a problem — the app stores no documents (§ "What the
  server needs"); the evidence checklist records that a document exists.

And two commercial/platform facts:

- The **Hobby plan is for non-commercial projects**; a product with paying MSME
  customers needs **Pro**. Check the current terms before you commit.
- The serverless route below uses **`vercel-php`**, a community runtime (pinned
  at `0.7.4`, which is the PHP **8.3** line — the same version `composer.json`
  pins). Vercel's *official* PHP support is the container path in §12B.

### 12A. Serverless runtime (what is committed)

Three files, already in the repo:

| File | Job |
|---|---|
| `vercel.json` | Declares `api/index.php` on `vercel-php@0.7.4`, serves `public/` as the output directory (so the CSS and icons are static, never PHP), rewrites every other path to the function, and pins the `bom1` (Mumbai) region |
| `api/index.php` | The entrypoint: creates `/tmp/storage`, defaults the caches/views/logs (and sessions) to it **without overriding anything the project sets**, tells the SAPI it is serving `/`, relocates `storage_path()` to `/tmp/storage`, then hands the request to the same `bootstrap/app.php` a PHP-FPM host uses |
| `.vercelignore` | Keeps `vendor/`, `node_modules/`, local SQLite files, logs and compiled caches out of the upload |

Nothing in `app/`, `routes/`, `resources/` or `public/` differs from the VPS
deployment. `public/index.php` remains the front controller everywhere else.

#### 1. Create the database (outside Vercel)

Any managed Postgres or MySQL. Postgres example (Neon):

```bash
# Neon/Supabase pooled connection string, TLS required
DB_CONNECTION=pgsql
DB_URL="postgres://USER:PASSWORD@HOST/DBNAME?sslmode=require"
```

The vercel-php runtime ships `pdo_pgsql`, `pdo_mysql` and `pdo_sqlite`, so all
three Laravel drivers are available. The schema uses no driver-specific types.

> If `pdo_pgsql` fails to negotiate TLS against a pooler, use the direct (non-pooled)
> host for migrations and `?sslmode=require` — the runtime bundles an old libpq.

#### 2. Migrate from a machine with PHP — not from the function

```bash
APP_ENV=production \
DB_CONNECTION=pgsql \
DB_URL="postgres://…?sslmode=require" \
php artisan migrate --force
```

Do **not** run `migrate --seed`, and do not migrate on cold start: several
instances start at once and would race. In CI it is one step:

```yaml
- name: Migrate production database
  if: github.ref == 'refs/heads/main'
  run: php artisan migrate --force
  env:
    APP_ENV: production
    DB_CONNECTION: pgsql
    DB_URL: ${{ secrets.DATABASE_URL }}
```

#### 3. Set the environment variables

In **Vercel → Project → Settings → Environment Variables** (never in
`vercel.json`, which is committed):

```dotenv
APP_NAME=PayKaro
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:…                 # php artisan key:generate --show
APP_URL=https://paykaro.vercel.app
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=pgsql
DB_URL=postgres://…?sslmode=require

SESSION_DRIVER=database          # the sessions table ships; or `cookie`
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp                 # + MAIL_HOST / MAIL_PORT / MAIL_USERNAME / …
MAIL_FROM_ADDRESS=invoices@example.com
MAIL_FROM_NAME=PayKaro

PAYKARO_DEMO=false
PAYKARO_BANK_RATE=5.5
PAYKARO_MSME_DUE_DAYS=45
PAYKARO_INTEREST_MULTIPLIER=3
PAYKARO_FINANCE_READY_SCORE=85

GOOGLE_CLIENT_ID=…               # optional
GOOGLE_CLIENT_SECRET=…
GOOGLE_REDIRECT_URI=https://paykaro.vercel.app/auth/google/callback
```

`CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=stderr`, the `/tmp`
cache paths and `SESSION_DRIVER=database` are **defaults set by `api/index.php`**
when the project does not define them — a value you set in the dashboard always
wins, so the two lists cannot fight.

`SESSION_DRIVER=database` is the right choice for a finance app (sessions can be
revoked and are shared across instances). `cookie` also works and removes a
database round-trip per request; it caps session data at ~4 KB, which this app
does not approach.

#### 4. Deploy

```bash
npm i -g vercel
vercel link
vercel deploy --prod
```

Or import the GitHub repository in the Vercel dashboard; every push to `main`
then deploys. There is **no build command** — the runtime runs `composer install`
from the committed `composer.lock` itself. Do **not** add `php artisan optimize`
to a build step: configuration caching bakes in build-time env, while Vercel
supplies `APP_KEY` and `DB_URL` at runtime. (Route and view caches are safe and
optional; Laravel reads config fresh.)

#### 5. Verify

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://paykaro.vercel.app/up        # 200
curl -sS -o /dev/null -w '%{http_code}\n' https://paykaro.vercel.app/login     # 200
curl -sS -o /dev/null -w '%{http_code}\n' https://paykaro.vercel.app/dashboard # 302
```

Then: the stylesheet loads (it comes from `public/` as a static asset, not from
PHP), sign in, create an invoice, open `/reports`, and print one claim packet.
Live logs are in the Vercel dashboard's **Runtime Logs** — that is where
`LOG_CHANNEL=stderr` sends them.

#### Local check before pushing

The runtime is a thin wrapper around PHP's built-in server, so the entrypoint can
be exercised locally (the repo's dev flow stays `php artisan serve`):

```bash
php -S 127.0.0.1:8080 api/index.php
```

### 12B. Official container path (FrankenPHP)

Vercel now documents a supported way to run Laravel in a **container-backed
Function** (FrankenPHP + Caddy, built into an image). It keeps a real web server
and a normal Laravel lifecycle, so it removes most of the `/tmp` juggling — at
the cost of building an image on every deploy.

It is not committed, because it changes how the repo is packaged. If you want it:

1. `Dockerfile.vercel` — `FROM dunglas/frankenphp:1-php8.3-bookworm`,
   `install-php-extensions intl zip pdo_pgsql` (or `pdo_mysql`), `composer install
   --no-dev --optimize-autoloader`, copy the app, create `storage/*` and
   `bootstrap/cache`, run as `www-data`, `ENV PORT=80`;
2. `Caddyfile` — `:{$PORT:80} { root * /app/public  encode zstd gzip  php_server }`;
3. `vercel.json` — replace the `functions`/`rewrites` block with
   `"services": { "app": { "root": ".", "entrypoint": "Dockerfile.vercel", "runtime": "container" } }`
   and a catch-all rewrite to that service.

Two rules from Vercel's guide matter here: **migrations run in CI, not at
container start**, and **do not bake a config cache into the image** (env is
injected at runtime). Everything else in §12A step 3 still applies — external
database, `APP_KEY` from the dashboard, `PAYKARO_DEMO=false`.

### Which Vercel path to pick

| | Serverless (`vercel-php`) | Container (FrankenPHP) |
|---|---|---|
| Committed already | ✅ | ✗ (3 files to add) |
| PHP version | 8.3 (runtime `0.7.4`) | whatever the image pins |
| Cold start | ~250 ms, warm ~5 ms | image pull + boot |
| Real web server | ✗ (PHP dev server in the lambda) | ✅ Caddy |
| Image / bundle size limits | ~250 MB unzipped | container image limits |
| Best for | a demo, a pilot, a low-traffic deployment | a serious production deployment |

Either way the database, sessions and the `PAYKARO_*` numbers are configured the
same way — and an external database is not optional on Vercel.

---

## 13. Other platforms — and why the repo now ships a container

Everything below runs **one artifact**: the `Dockerfile` in the repo root
(FrankenPHP + Caddy, PHP 8.3, one process, `$PORT`). The database is external on
every one of them. For the bare-metal path nothing changed — §1–§10 still apply.

### The shortlist for this app

| Platform | India region | Smallest useful cost | Effort | Verdict for PayKaro |
|---|---|---|---|---|
| **Fly.io** | ✅ **Mumbai (`bom`)** | ~$3–7 compute + Postgres | `fly launch` + `fly secrets set` | **Best latency story.** App and DB inside India; 5–20 ms from Indian users |
| **DigitalOcean App Platform** | ✅ **Bangalore (`blr1`)** | $5/app + $15 managed DB | connect repo | Good middle ground; INR billing, Indian region, one-click Postgres |
| **Railway** | ✗ (Singapore is closest) | ~$5–25 all-in | connect repo | **Best DX of the lot.** Postgres one click, private network, no Dockerfile needed (though it uses ours) |
| **Render** | ✗ (Singapore is closest) | $7/app + $6 DB | connect repo | Boring in the good way; free tier sleeps after 15 min — fine for a demo, not for a ledger |
| **Google Cloud Run** | ✅ `asia-south1` Mumbai | ~$0 idle, ~$10–20 busy | `gcloud run deploy` | Scales to zero; needs Cloud SQL (or Neon) and a container registry |
| **AWS (App Runner / ECS Fargate)** | ✅ `ap-south-1` Mumbai, `ap-south-2` Hyderabad | ~$15–25 | most setup | Right answer if the company is already on AWS and wants VPC/RDS |
| **Azure App Service** | ✅ Central India (Pune) | ~$13 (B1) | zip/container deploy | Same reasoning for Microsoft shops |
| **Hetzner / DigitalOcean Droplet + Forge or Ploi** | ✗ (EU/US only) | €4–6 box + $12–15 panel | panel handles it | **Cheapest at scale**; no India region, ~120–150 ms to Mumbai |
| **Indian hosts (E2E Networks, Utho, Hostinger VPS)** | ✅ | ₹300–800/mo | manual, §1–§5 | INR billing and an India DC; you own the maintenance |
| **Shared hosting / cPanel** | varies | ₹150–400/mo | upload + `public/` | Works (§11) but no Composer-free path, no long-running processes; fine for a pilot |
| **Kubernetes** | depends on cluster | cluster cost | most | Only if you already run one |

**Regions are not a detail for this product.** A supplier on a 4G phone in
Hyderabad is the target user; Singapore adds 60–100 ms per round trip, Oregon
200–300 ms. That is the difference between the invoice list feeling instant and
feeling like a form submission. If you must pick one thing to optimise, pick
`bom` / `blr1` / `asia-south1` / `ap-south-1`.

### Deploying the image anywhere (the common part)

```bash
docker build -t paykaro .
docker run --rm -p 8080:8080 \
  -e APP_KEY="$(php artisan key:generate --show)" \
  -e APP_URL=https://paykaro.example.com \
  -e DB_CONNECTION=pgsql -e DB_URL='postgres://…?sslmode=require' \
  -e SESSION_DRIVER=database -e CACHE_STORE=database \
  -e TRUSTED_PROXIES='*' \
  -e PAYKARO_DEMO=false \
  -e RUN_MIGRATIONS=true \
  paykaro
```

What the image does and does not do:

| | |
|---|---|
| Serves | `public/` on `$PORT` (default 8080), PHP routes through `public/index.php` |
| Writes | `storage/`, `bootstrap/cache/` — created and chowned at build time |
| Migrates | only when `RUN_MIGRATIONS=true` (safe on one instance; use CI when you scale) |
| Caches | only when `BUILD_CACHES=true` — deliberately not baked into the image, because env arrives at runtime |
| Logs | app → stderr (`LOG_CHANNEL=stderr`), requests → stdout (Caddyfile) |
| Does not contain | `.env`, SQLite files, `vendor/` from your laptop, tests |

`TRUSTED_PROXIES=*` is what makes HTTPS work behind every one of these proxies —
`bootstrap/app.php` now reads it, off by default. Without it Laravel sees plain
HTTP and the secure session cookie can be dropped.

Local parity check before pushing:

```bash
cp .env.example .env
docker compose run --rm app php artisan key:generate --show   # paste APP_KEY into .env
docker compose up --build                                     # app + Postgres on :8080
```

### Per-platform quickstart

**Fly.io** — the only mainstream PaaS with a Mumbai region:

```bash
fly launch --dockerfile Dockerfile --region bom --no-deploy
fly postgres create --name paykaro-db --region bom
fly postgres attach paykaro-db            # sets DATABASE_URL
fly secrets set APP_KEY="base64:…" APP_URL=https://paykaro.fly.dev \
                TRUSTED_PROXIES='*' PAYKARO_DEMO=false RUN_MIGRATIONS=true
fly deploy
```

Set `internal_port = 8080` in the generated `fly.toml`, and keep `min_machines_running = 1`
(or Fly will stop the machine and the next request pays a cold start). `RUN_MIGRATIONS=true`
is fine at one machine; move it to a `release_command` once you scale.

**Render** — connect the repo, choose *Docker*, region **Singapore**, add a
Postgres instance, then set `APP_KEY`, `APP_URL`, `TRUSTED_PROXIES=*`,
`PAYKARO_DEMO=false`, `RUN_MIGRATIONS=true`, and point `DB_*` at the *internal*
connection string. Free web services sleep after 15 minutes — acceptable for a
demo, not for invoice reminders.

**Railway** — new project → *Deploy from GitHub*; the Dockerfile is detected.
Add Postgres from the template list, reference `${{Postgres.DATABASE_URL}}` as
`DB_URL`, then set the same variables. Railway's private network means the DB
traffic never leaves the project. Migration: `RUN_MIGRATIONS=true` on one
replica, or add a one-off `php artisan migrate --force` deploy step.

**DigitalOcean App Platform** — create an app from the repo, *Dockerfile* build,
region **Bangalore (BLR1)**, add a Managed Postgres (same region), set the env
vars, HTTP port 8080, health check `/up`.

**Google Cloud Run** — build and push to Artifact Registry, then:

```bash
gcloud run deploy paykaro \
  --image asia-south1-docker.pkg.dev/PROJECT/paykaro/paykaro:latest \
  --region asia-south1 --port 8080 --allow-unauthenticated \
  --set-env-vars APP_ENV=production,APP_DEBUG=false,LOG_CHANNEL=stderr,TRUSTED_PROXIES='*' \
  --set-secrets APP_KEY=paykaro-app-key:latest,DB_URL=paykaro-db-url:latest
```

Migrations are not a cold-start job: run `php artisan migrate --force` from CI
(or a Cloud Run Job) against Cloud SQL / Neon. Scale-to-zero is real, so set a
minimum instance of 1 if invoice pages must stay warm.

**AWS App Runner / ECS Fargate** — push the image to ECR, create an App Runner
service with port 8080 and `APP_KEY`/`DB_URL` as secrets, RDS in `ap-south-1`.
Fargate adds an ALB, a VPC and task definitions — worth it only if you already
need them.

### What none of them give you

- **A queue worker or a scheduler.** PayKaro needs neither today (mail is sent
  in-request). If you later queue mail, every platform above needs a second
  process or a scheduled job — Fly `[processes]`, Railway/Render worker
  services, Cloud Run Jobs + Cloud Scheduler.
- **Persistent disk.** Object storage or an external database is the only
  durable state; the app has no uploads, so this costs nothing today.
- **Managed backups for less than a plan tier.** On Railway and Fly, Postgres is
  something you operate: schedule `pg_dump` yourself, or use a managed provider
  (Neon, Supabase, RDS) and keep only the app on the PaaS.
