# Running PayKaro

PayKaro is a Laravel 12 application on PHP 8.3. Nothing needs a database server:
`migrate --seed` builds a SQLite file and fills it with two demo businesses.

## Local

```bash
composer install     # from the committed composer.lock
cp .env.example .env
php artisan key:generate

touch database/paykaro.sqlite
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8080
```

On Windows (PowerShell 5.1 — no `&&`, so one command per line; `composer` must be on
`PATH` via `composer.bat`, or call `php "$env:LOCALAPPDATA\Programs\composer.phar"` directly):

```powershell
composer install                          # installs from the committed composer.lock
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan migrate:fresh --seed          # creates database\paykaro.sqlite as needed
php artisan test
php artisan serve
```

`migrate --seed` prints the demo logins (`sunita@shreeprecision.in`, `farhan@metrowceramics.in`,
password `demo1234`) and is idempotent — it skips itself the moment
any user exists, so re-running it never doubles the demo book. To start over:

```bash
php artisan migrate:fresh --seed
```

### Seeded workspace

| Business | Users | Buyers | Invoices |
|----------|-------|--------|----------|
| Shree Precision Components | Sunita Rao (owner) | 4 | 15 — settled, financed, disputed, overdue |
| MetRow Ceramics | Farhan Ali (owner) | 2 | 3 — one invoice against each |

Password for both: `demo1234`.

The two tenants are seeded *through the app's own pipeline*
(`App\Services\InvoiceWorkflow`), not by inserting rows — so the demo data satisfies
the same rules as real data, including the derived due dates, the seeded evidence
checklists and the "buyer is not on TReDS" alerts. `INV-2026-007` additionally carries
a real MSEFC dispute row with its filing deadline, which is what the claim packet and
the "File before" line are there to serve.

## Tests

```bash
php artisan test                     # whole suite (SQLite :memory:, RefreshDatabase)
php artisan test --filter=ReceivablesTest
./vendor/bin/pint                    # fix style; CI runs `pint --test`
```

`phpunit.xml` pins the testing environment: in-memory SQLite, `array` cache/session,
`sync` queue and a fixed `APP_KEY`, so no `.env` and no `key:generate` are needed to run
the suite — which is what CI relies on.

## Configuration

Everything the app can be told lives in `.env` / `config/paykaro.php`:

| Key | Default | Effect |
|-----|---------|--------|
| `APP_URL` | `http://localhost` | used for generated absolute URLs |
| `DB_CONNECTION` | `sqlite` | `mysql`/`pgsql` work unchanged — the schema uses no SQLite-specific types |
| `SESSION_DRIVER` | `file` | `database` also works; the sessions table ships in the base migration |
| `PAYKARO_MSME_DUE_DAYS` | `45` | the window after which an invoice is overdue and interest starts |
| `PAYKARO_DEFAULT_TAX_RATE` | `18` | GST applied when an invoice is raised without a tax figure |
| `PAYKARO_BANK_RATE` | `6.5` | prevailing rate, multiplied below |
| `PAYKARO_INTEREST_MULTIPLIER` | `3` | statutory interest = bank rate × 3, accrued daily |
| `PAYKARO_FINANCE_READY_SCORE` | `85` | readiness at/above which an invoice is financeable |
| `PAYKARO_ALERT_LIMIT` | `6` | rows in the dashboard's "Needs attention" list |
| `PAYKARO_DEMO` | `true` | labels the workspace "Demo" in the utility bar; turn off for real data |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | blank | blank means no Google button and `404` on the OAuth routes |
| `GOOGLE_REDIRECT_URI` | blank | derived from the current request when blank |

Changing a `PAYKARO_*` value changes the app's arithmetic — the ageing chart, the KPI
cards, the finance queue and the claim packet all read the same configuration.

## Point it at MySQL

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=paykaro \
DB_USERNAME=paykaro DB_PASSWORD='…' php artisan migrate --seed
```

## Google Sign-In

1. In Google Cloud Console create an **OAuth client ID** (Web application) and add the
   callback URI exactly as the app generates it: `https://your-domain/auth/google/callback`.
2. Put the credentials in `.env`:

```dotenv
GOOGLE_CLIENT_ID=1234567890-abcdef.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-…
# optional; derived from the signed-in request URL when blank
GOOGLE_REDIRECT_URI=https://your-domain/auth/google/callback
```

3. Reload config: `php artisan config:clear`.

The flow is `/auth/google` → Google consent → `/auth/google/callback`, and the CSRF
`state` rides the session (Socialite), so there is no state table to expire. Signing in
then follows one order, in `App\Services\AccountProvisioner`:

- a user with that `google_id` → signed in;
- else a user with that **email** → the Google identity is linked to the existing
  account (avatar filled in only when blank), so a password member never ends up with
  a second, empty workspace;
- else a new business + owner is created, with **no password** — that account can only
  ever sign in with Google, and the password form says so instead of failing.

With no credentials configured the whole feature switches itself off: no button, `404`
routes. That is also how the test suite exercises it (see `tests/Feature/GoogleSignInTest.php`,
which mocks Socialite rather than dialling Google).

## Routes

Public: `/` landing · `/pricing` · `/help` · `/contact` · `/news` → `/#news` ·
`/news/{slug}` · `/terms` · `/privacy` · `/security` (legal set, content in
`app/Support/Legal.php`, one layout at `resources/views/components/legal-layout.blade.php`) ·
`/up` (health).

Auth: `/login` `/signup` (GET+POST) · `/auth/google` `/auth/google/callback` · `POST /logout`.

Workspace: `/dashboard` · `/invoices` (list, `?status=` and `?q=`) · `/invoices/create` ·
`/invoices/{id}` · `/invoices/{id}/edit` · `PATCH /invoices/{id}/status` ·
`PUT /invoices/{id}/evidence` · `POST /invoices/{id}/payments` ·
`POST /invoices/{id}/financings` · `POST /invoices/{id}/disputes` ·
`GET /invoices/{id}/claim` · `/buyers` `/buyers/create` · `/treds` · `/reports` ·
`/settings` (GET+PUT) · `POST /alerts/read`.

Legacy addresses the flat-PHP app used, still honoured: `/invoices/new` →
`/invoices/create`, `/invoice?id=N` → `/invoices/N`, `/invoice?edit=N` → `/invoices/N/edit`,
`/claim?id=N` → `/invoices/N/claim`.

## Sandbox notes (no native PHP)

This repo's agent sandbox has no `php` or `composer` binary and cannot reach Packagist,
so the app cannot be booted there — verification in the sandbox is structural plus the
php-wasm syntax checker kept for that purpose:

```bash
cd bridge && npm install
VITEST=1 node lint.mjs ../app/Services/Receivables.php   # any repo .php file(s)
```

`php artisan test`, `migrate --seed` and the live preview all require a real PHP 8.3
runtime — i.e. CI, or your machine.
