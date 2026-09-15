# Running PayKaro

PayKaro is a Laravel 12 application on PHP 8.3. Nothing needs a database server:
`migrate --seed` builds a SQLite file and fills it with two demo businesses.

## Local

Before pushing a dependency change, check the lock against the PHP CI actually runs:

```
node bridge/check-lock.mjs          # target defaults to composer.json's platform pin
node bridge/check-lock.mjs 8.3.33
```

It exits non-zero and names every locked package whose `php` floor CI cannot satisfy — the
failure a locally-green `composer install` cannot show you, because a lock always agrees
with the machine that produced it.


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

### The JS harness

The date-field calendar popup is inline script with no build step, so nothing in the PHP
suite can reach it. `bridge/datepicker-harness.mjs` mounts the include's own markup and
script in jsdom and drives it (click, arrow, type), deriving every month length and cell
count from the real calendar rather than hard-coded dates:

```bash
cd bridge && npm i jsdom          # dev-only, deliberately not in package.json
node bridge/datepicker-harness.mjs
PIN_TODAY=2024-02-29 node bridge/datepicker-harness.mjs   # edge days too
```

It earned its place: it caught a `parse()` regex that could never match (`/^\d{4}…/` in the
source, so a literal backslash), a `Date` leaking into a `[y,m,d]` reader, an off-by-one
weekday offset against the widget's own Monday-first header, and a null dereference that
killed the popup whenever a field had been replaced by a re-render. `node --check` passed
through every one of those.

A green run does not cover visual alignment or the native picker — jsdom performs no
layout. Check those in a browser.

### The overflow sim

Layout can't be tested in jsdom, but the *rules* that decide whether anything can
overflow can be. `bridge/overflow-sim.mjs` parses `public/assets/app.css` (plus the
datepicker include's own `<style>`) and asserts the structural facts that CSS overflow
bugs are made of: every `fr` track is `minmax(0,…)`, scroll containers scroll instead
of clipping, `overflow-wrap` is inherited from the page roots, no track is sized by
`max-content` text, and the fixed-width popup has a viewport clamp.

It also audits the printed claim packet, because that is the one artefact where a visual
bug has legal consequence: the chrome is hidden, the light palette is re-asserted for
`html.dark` (and compared token-by-token against `:root`, so a copy cannot quietly go
stale), each schedule is kept off a page break, and the running footer that carries the
GSTIN and invoice number is `position:fixed`.

```bash
node bridge/overflow-sim.mjs                                  # current sheet, 15 checks
git show main:public/assets/app.css > /tmp/app.css.before
CSS=/tmp/app.css.before INCLUDE=/dev/null node bridge/overflow-sim.mjs   # control: 2 hazards
```

Always run the control too. A checker that parses nothing reports nothing, so the tool
aborts if it reads fewer than 60 rules and the control run is what proves the checks can
fail. The control above is `main`'s sheet, taken before this work: it reports the two
missing print facts, and it is also what the original 32-hazard run used. Most of those 32
were the same single mistake repeated: the base rules guarded their tracks with
`minmax(0,1fr)` but the responsive overrides re-wrote them as bare `1fr`, re-arming the
blow-out at exactly the widths where text is most likely to spill. The refresh then
re-introduced four of the same shape under new names — `auto minmax(18rem,1fr) auto` and
friends — which is why the minimum now sits on the *item* (`min-width:18rem` on the side
stack) and the track stays shrinkable.

### The brand assets

The favicon, the tab/home-screen icons and the 1200×630 share card are committed
files — the app still has no build step — but they are *generated*, so they can be
re-rendered when the palette or the wordmark changes instead of being binaries
nobody can reproduce:

```bash
cd /tmp && npm pack @expo-google-fonts/plus-jakarta-sans && tar xzf *.tgz
cd - && FONT_DIR=/tmp/package node bridge/brand-assets.mjs
```

It draws with the app's own brand font (Plus Jakarta Sans, the same family the
layouts load from Google Fonts) and refuses to write a card whose copy overruns the
text column. Outputs: `public/favicon.ico` (16/32/48), `public/assets/img/icon-32|180|512.png`
and `public/assets/img/og-default.png`.

`tests/Feature/BrandAssetsTest.php` pins what those files have to be — the ICO is
not the 0-byte stub it used to be, the PNGs are the sizes their `sizes=` attributes
claim, and every layout links them and publishes an absolute `og:image`.

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

Both are audited by `bridge/overflow-sim.mjs` (the print section) and
`tests/Feature/InvoicePipelineTest.php` (the strings a filing is cited by).

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

## The brand book

`/brand` is the brand guidelines, readable signed out and linked from every
marketing footer. It is not a document that describes the product — it *renders*
it: the tokens are read out of `public/assets/app.css`, the contrast ratios are
measured by `App\Brand\Palette`, and the type scale is parsed from the same
stylesheet. If the page and the app disagree, the app is right and the page is a
bug report.

Two classes back it, both §5.2's "guidelines as code":

| Class | Owns | Guarded by |
|-------|------|-----------|
| `App\Brand\Palette` | the tokens, and the 31 colour pairings the product relies on with the ratio each must clear | `tests/Unit/BrandPaletteTest.php` — fails below a floor, and fails when a token is declared in `:root` but never restated in `html.dark` |
| `App\Brand\Type` | the type scale, read from the stylesheet | `tests/Feature/BrandPageTest.php` — the page has to show what the CSS sets |

Measuring is not decoration: the audit found six failing pairings in the shipped
palette (see the comment beside the tokens in `app.css`). A new screen that needs a
colour takes a token; a new pairing gets declared in `Palette::pairs()` with the
reason it exists, and the test says whether it is legible.

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
| `PAYKARO_BANK_RATE` | `6.5` | prevailing rate, multiplied below |
| `PAYKARO_INTEREST_MULTIPLIER` | `3` | statutory interest = bank rate × 3, accrued daily |
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

## Sandbox (no native PHP)

The agent sandbox has Node but no `php`/`composer` binary and no Packagist access, so the
app is booted inside `@php-wasm/node` — PHP 8.3 compiled to WebAssembly — behind a small
Node HTTP bridge. Laravel runs unmodified: `public/index.php` is the front controller and
the bridge does only what a web server would.

```bash
cd bridge
npm ci                                    # @php-wasm/node, pinned in package-lock.json
node serve.mjs                            # http://127.0.0.1:8080
```

First boot copies `.env.example` → `.env`, generates an `APP_KEY`, runs `migrate --seed`
and then serves; later boots go straight to serving, and `PAYKARO_SKIP_SEED=1` skips the
database step entirely. The logins are the demo businesses the seeder prints, password
`demo1234`.

`bridge/serve.mjs`:

- runs one request at a time through the front controller and states the SAPI variables a
  web server would (`SCRIPT_NAME`, `DOCUMENT_ROOT`, `REQUEST_URI`, `HTTPS`) — php-wasm
  otherwise reports the request path as the *script* name, and Laravel derives a bogus
  base path from that, prefixing every route and URL;
- serves `public/` itself for anything that exists on disk and is not PHP;
- passes the visitor's origin down as `APP_URL` (from `X-Forwarded-Host`), so `asset()`,
  `route()` and redirects point at the sandbox preview host instead of at `localhost`.

Two runtime limitations are accommodated rather than fixed:

- **`SESSION_DRIVER=database`**, rewritten in `.env` on boot. Laravel's *file* session
  driver reads through `Filesystem::sharedGet()`, which needs `flock($handle, LOCK_SH)`;
  WebAssembly refuses shared locks, that read comes back empty, and every request would
  mint a fresh CSRF token — so `POST /login` answers `419 Page Expired` forever. The
  `sessions` table is already part of the app's own migrations. On a real server `file`
  (or Redis) stays the right driver; this is the runtime, not the app.
- **No `intl`/`zip`/`curl` extensions** in the wasm build. Nothing the app itself renders
  needs them — `bridge/check-lock.mjs` is the one script that wants `intl`, and it is run
  on a machine that has it.

The suite runs here too, but not through `php artisan test`: that command shells out to
`vendor/bin/phpunit` through Symfony Process, and this runtime has no child process to
spawn. `bridge/test.mjs` starts PHPUnit directly instead, through php-wasm's real CLI SAPI
— which is where argv, exit codes and STDERR come from:

```bash
cd bridge && npm ci
node test.mjs                              # whole suite
node test.mjs --filter=WorkspacePagesTest  # any PHPUnit argument
node test.mjs --testdox
```

Two accommodations are visible if you look for them. `bridge/bootstrap.mjs` writes a
composer-compatible autoloader, not Composer's `vendor/bin` shims, so the launcher is
named by its path. And the runner sets `PAYKARO_SANDBOX_PHPUNIT=1`, which
`tests/TestCase.php` reads to skip Laravel's mocked console output: building that mock
traps the WebAssembly runtime, and `RefreshDatabase` migrates through the exact call that
builds it, so a database-backed test would die before its first assertion. On CI the
variable is unset and the mock is used as normal — CI remains the authority: real PHP
8.3, a real Composer, the whole suite, and every assertion on artisan output.
