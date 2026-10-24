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
| **Docker / Fly.io / Render / Railway / Cloud Run** | The repo has **no** `Dockerfile`, `Procfile` or platform manifest yet — one has to be added. The image needs `php:8.3-fpm` (or `-alpine`) with the extensions above, Nginx or Caddy in front, `root public/`, and the release commands from §5 as an entrypoint/release step |

A container image is the natural next step if you want reproducible deploys; the
app itself needs nothing beyond PHP-FPM, a writable `storage/`, and the database.
