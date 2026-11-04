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

The fixture `APP_KEY` in `phpunit.xml` must decode to exactly 32 bytes: `AES-256-CBC`
requires it, and during testing the framework does not load `.env` at all, so that entry is
the *only* key the suite ever sees. A wrong length there is invisible on a machine with a
generated `.env` and looks like 99 unrelated failures on a clean one.


```bash
php artisan test                     # whole suite (SQLite :memory:, RefreshDatabase)
php artisan test --filter=ReceivablesTest
./vendor/bin/pint                    # fix style; CI runs `pint --test`
```

`phpunit.xml` pins the testing environment: in-memory SQLite, `array` cache/session,
`sync` queue and a fixed `APP_KEY`, so no `.env` and no `key:generate` are needed to run
the suite — which is what CI relies on.

## Printed documents

Anything that leaves the product on paper goes through two shared pieces, so a
filing cannot carry the wrong claimant or the wrong number:

- `components/letterhead.blade.php` — the supplier's own letterhead: their name,
  GSTIN, PAN, Udyam, remittance account and the signing member, with the document
  type and number on the right. It is deliberately plain (one rule, no tint, no
  shadow) because it is read on A4, photocopied, and sometimes scanned back in.
  `Invoices\\claim.blade.php` opens on it, and the invoice email's masthead follows
  the same rule: the supplier first, PayKaro once, small, as the tool it was
  prepared with.
- The `@media print` section of `public/assets/app.css` — chrome hidden, light
  tokens re-asserted under `html.dark`, schedules kept off page breaks, `@page`
  margin, and a fixed footer carrying the GSTIN and invoice number on every sheet.

The print sheet is audited by `tests/Feature/InvoicePipelineTest.php` (the strings a filing is cited by).

## What the marketing pages may claim

`App\Support\Proof` is the only source a marketing page may quote a figure from,
and it reads the same config and enums the domain computes with — the MSME due
window, the interest multiplier, the evidence checklist's size, and the
finance-readiness threshold. Change `PAYKARO_MSME_DUE_DAYS` and the landing page
changes with it.

That exists because the pages used to publish traction nothing measured: ₹4.2Cr+
"Receivables tracked" (with "Across active tenants in the last 30 days"), ₹240Cr+
"invoices cleared", a 99.8% reconciliation rate, an under-48-hour disbursal
figure, plus "RBI regulated entities", "RBI TReDS direct gateway", "256-bit SSL /
encrypted ledger" and "GSTN & TReDS verified". `tests/Feature/AuthenticityTest.php`
lists every one of them: the rendered pages must not contain them, no template may
reintroduce them (a source scan strips comments first), the published figures must
follow the configuration, and the landing page must state what it does *not* claim —
the same standard `App\Support\Legal` already held itself to.

The news feed is the team's own writing and says so: "Product notes", "Written by
the PayKaro team", tags that do not imply a news desk.

## The first-run checklist

A new workspace's dashboard carries a four-step card — add a buyer, raise an
invoice, complete an evidence trail, confirm each buyer's TReDS status — read from
the workspace's own state by `App\Brand\Onboarding`. It is not stored, so a member
who inherits a populated workspace never sees it, one who starts fresh does, and
the card disappears when the last step is done. Every step is a write, so a
read-only member never sees it at all.

## Email

Three messages, all to the buyer, all sent from the invoice page's **Correspondence**
panel — and all computed from the invoice rather than typed at send time:

| Message | What it carries |
|---------|-----------------|
| `InvoiceSentMail` | the invoice, its stored due date (derived by `Receivables`, never hand-entered), and the remittance account |
| `OverdueReminderMail` | days past due and the interest accrued to date, escalating in tone at 15 and 30 days |
| `EvidenceRequestMail` | the evidence rows the invoice's own checklist reports as missing |

Each has a plain-text alternative, because MSME accounts-payable inboxes are
cheap Android clients. `MAIL_MAILER` decides where they go — `log` writes them to
`storage/logs/laravel.log`, which is the sane default for a demo; set SMTP
credentials in `.env` to actually deliver.

Sending needs an address on the buyer record (`buyers.email`, nullable). One that
is missing is not an error: the panel says which buyers cannot be emailed and the
send button reports it instead of failing. `tests/Feature/InvoiceMailTest.php`
holds the coherence rule — the reminder quotes the *same interest figure* the
invoice page shows — plus the role check (a viewer sends nothing) and the tenant
scope (another workspace's invoice is a 404).

## Configuration

Everything the app can be told lives in `.env` / `config/paykaro.php`:

| Key | Default | Effect |
|-----|---------|--------|
| `APP_URL` | `http://localhost` | used for generated absolute URLs |
| `DB_CONNECTION` | `sqlite` | `mysql`/`pgsql` work unchanged — the schema uses no SQLite-specific types |
| `SESSION_DRIVER` | `file` | `database` also works; the sessions table ships in the base migration |
| `PAYKARO_MSME_DUE_DAYS` | `45` | the window after which an invoice is overdue and interest starts |
| `PAYKARO_DEFAULT_TAX_RATE` | `18` | GST applied when an invoice is raised without a tax figure |
| `PAYKARO_BANK_RATE` | `5.5` | the RBI **Bank Rate** (not the repo rate) — 5.5% since Dec 2025. Multiplied below |
| `PAYKARO_INTEREST_MULTIPLIER` | `3` | statutory interest = bank rate × 3, **compound with monthly rests** (Section 16) |
| `PAYKARO_FINANCE_READY_SCORE` | `85` | readiness at/above which an invoice is financeable |
| `PAYKARO_ALERT_LIMIT` | `6` | rows in the dashboard's "Needs attention" list |
| `PAYKARO_DEMO` | `true` | labels the workspace "Demo" in the utility bar; turn off for real data |
| `PAYKARO_HEADLINE` | `Make every invoice count` | the brand line: landing hero, page `<title>`, sign-in shell, share card |
| `PAYKARO_DESCRIPTOR` | `MSME invoice & receivables tracker` | the line under the wordmark — a descriptor, not a tagline |
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

## Which PHP the dependencies resolve for

`composer.json` pins `config.platform.php` to `8.3.0`. Composer normally resolves against
whichever PHP your CLI runs, so on 8.4 a `composer update` re-locks `symfony/clock`,
`css-selector`, `event-dispatcher`, `string` and `translation` to v8 — floor `>=8.4.1` —
and `composer install` then fails on every 8.3 machine, CI included. The pin makes all of
us resolve the set CI installs, and it matters because the lock file is committed.

If you change it, re-resolve rather than just re-hashing:

```
composer update            # correct: picks new versions for the pinned platform
composer update --lock     # wrong here: rewrites the hash, keeps the 8.4-only versions
```

`require.php` stays `^8.3`: 8.4 is fine to *run* on, it just must not decide the lock.
Nothing else in the dependency set needs 8.4, so relocking on 8.3 is a complete fix.

## Routes

Public: `/` landing · `/pricing` · `/help` · `/contact` · `/news` → `/#news` ·
`/news/{slug}` · `/terms` · `/privacy` · `/security` (legal set, content in
`app/Support/Legal.php`, one layout at `resources/views/components/legal-layout.blade.php`) ·
`/up` (health).

Auth: `/login` `/signup` (GET+POST) · `/auth/google` `/auth/google/callback` · `POST /logout`.

Workspace: `/dashboard` · `/invoices` (list, `?status=` and `?q=`) · `/invoices/create` ·
`/invoices/{id}` · `/invoices/{id}/edit` · `PATCH /invoices/{id}/status` ·
`PUT /invoices/{id}/evidence` · `POST /invoices/{id}/send` · `POST /invoices/{id}/remind` ·
`POST /invoices/{id}/request-evidence` ·
`POST /invoices/{id}/payments` ·
`POST /invoices/{id}/financings` · `POST /invoices/{id}/disputes` ·
`GET /invoices/{id}/claim` · `/buyers` `/buyers/create` · `/treds` · `/reports` ·
`/settings` (GET+PUT) · `POST /alerts/read`.

Legacy addresses the flat-PHP app used, still honoured: `/invoices/new` →
`/invoices/create`, `/invoice?id=N` → `/invoices/N`, `/invoice?edit=N` → `/invoices/N/edit`,
`/claim?id=N` → `/invoices/N/claim`.
