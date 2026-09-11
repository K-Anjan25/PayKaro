# From flat PHP to Laravel

PayKaro used to be one hand-rolled front controller (`public/index.php`, ~2 200 lines)
over a `PayKaro.php` service and a PDO singleton, with superglobals routed by `switch`.
It is now a Laravel 12 application. The product is the same: same pages, same design
system, same pipeline, same numbers. This file records where each legacy concern went,
and every place the behaviour deliberately changed.

## What was replaced

| Was | Now |
|-----|-----|
| `public/index.php` (router + all views + `?err=` flash) | `routes/web.php`, controllers in `app/Http/Controllers`, Blade in `resources/views` |
| `PayKaro.php` (auth, tenant guard, invoice rules, queries) | `app/Services` (rules + workflow), `app/Tenancy` (isolation), Eloquent models |
| `config.php` | `config/paykaro.php` + `.env` (framework settings stay in `config/*`) |
| `db.php` (PDO singleton) | Laravel's database layer; `DB_CONNECTION=sqlite` by default |
| `schema.sql` (one file, `CREATE TABLE IF NOT EXISTS`) | 11 forward migrations in `database/migrations` |
| `bin/seed.php` + `bridge/seed.mjs` | `DatabaseSeeder` → `DemoWorkspaceSeeder`, run by `php artisan migrate --seed` |
| `public/router.php`, `bridge/serve.mjs`, `bridge/boot.php` | dropped — `php artisan serve` (and PHP's own built-in server) does the job |
| hand-rolled session table + `token` cookie | Laravel's `web` guard; sessions in `file` by default (`database` also available via the framework's own sessions table) |
| `sessions` / `oauth_states` tables | session driver + Socialite's stateless state in the session; both tables deleted from the schema |
| `config.php`'s `google_oauth` + curl-shaped token exchange | `laravel/socialite` behind `config/services.php`, wrapped by `App\Support\GoogleSignIn` |
| `?err=invalid` / `?err=taken` redirects between requests | validation failures with `->withInput()` and per-field messages |
| `bridge/lint.mjs` | kept, for sandboxed syntax checks only — CI lints with real PHP |

Kept verbatim: `public/assets/app.css` and `public/assets/img/*`. The Blade views emit
the same class names, so the ported markup — and therefore the look — is unchanged.

## Where the rules live

| Legacy | Now |
|--------|-----|
| `PayKaro::dueDate()` (45-day window) | `Receivables::dueDate()`, `config/paykaro.php` |
| `PayKaro::interest()` (`bank_rate × 3`, daily) | `Receivables::interest()` |
| `PayKaro::ageing()` | `AgeingBucket::fromOverdueDays()` |
| `PayKaro::readiness()` / finance-ready cut-off | `Receivables::readiness()` + `config('paykaro.finance_ready_score')` |
| `PayKaro::tredsStatus()` | `Receivables::tredsStatus()` → `TredsStatus` enum |
| `invoice['balance']` computed in SQL | `Invoice::balance()` over the payment rows |
| `claimPacket()` | `App\Services\ClaimPacket` |
| `dashboard()` aggregates | `App\Services\Dashboard` → `DashboardSummary` |
| `tid()` guard in every query | `App\Tenancy\TenantScope` (global) + `TenantContext` (resolution) |
| `badge()`, `progress()`, `kpiCard()`, `statusTimeline()`, `sideIcon()` | Blade components in `resources/views/components` |
| `landingPage()`, `pricingPage()`, `helpPage()`, `contactPage()`, `newsArticlePage()` | `resources/views/marketing/*` + `HomeController` / `PageController` |
| `paykaroNews()` | `App\Support\News` + `NewsArticle` (same three articles, same copy) |
| `layout()` (workspace shell) | `components/layouts/app.blade.php`; marketing shell is `components/layouts/public.blade.php`; auth split screen is `components/layouts/auth.blade.php` |

## Behaviour that deliberately changed

Each of these was a rough edge in the flat app; none of them changes a number a supplier
depends on.

1. **`treds_status` is no longer a column.** It used to be stored on the invoice and
   refreshed by whatever code last touched the row, so it could go stale. It is now
   computed (`Invoice::treds()`) from status + buyer onboarding. Same states
   (`na`, `ready`, `pending_buyer_onboard`, `financed`, `ineligible`).
2. **Derived money is never stored.** `invoices` keeps only base, tax and total; balance,
   interest, ageing and readiness are computed through `Receivables` on read. Amount
   columns moved from `REAL` to `DECIMAL(14,2)`.
3. **Drafts are excluded from the ageing chart and the 30-day receivable KPI.** They were
   counted before, which inflated both with invoices nobody has sent yet. The pipeline
   filter for `draft` still exists.
4. **Roles are enforced.** The legacy schema stored `role` and ignored it: a viewer could
   settle an invoice by POSTing the form. Owner and accountant may write the book; a
   viewer reads it; only the owner changes the business's GSTIN/PAN/Udyam/bank identity
   (a viewer or accountant posting `/settings` gets `403`).
5. **Cross-tenant reads stay 404s, and misdirected *writes* do too.** Same rule as before
   ("not found", not "forbidden"), now enforced by the global scope for every model
   instead of a `tid()` clause someone had to remember.
6. **`/invoice?id=N` and friends redirect instead of rendering.** The canonical URIs are
   `/invoices/N`, `/invoices/N/edit`, `/invoices/N/claim`; the old shapes 302 to them so
   links already sent in email and WhatsApp keep working. `?id` with no id lands on the
   invoice list.
7. **A duplicate email at sign-up is a field error on the form**, with the input kept,
   instead of `?err=taken` on a blank page.
8. **Sign-in is rate limited** — five attempts per email + IP, then a "try again in
   N seconds" message. The legacy form would accept a dictionary forever.
9. **Format rules on identity fields.** GSTIN, PAN, IFSC and Udyam are validated by
   `app/Rules/*` and upper-cased before storage, because those strings are what a claim
   cites. The legacy app stored whatever was typed.
10. **Google-only accounts are refused in words.** With a `NULL` password the old form
    path could hand the hasher nothing; `LoginRequest` now checks `hasPassword()` and
    tells the member to use the Google button (or set a password).
11. **One-time OAuth `state` lives in the session** (Socialite) rather than in
    `oauth_states` with a 20-minute TTL — no table to vacuum, and the state is bound to
    the browser that started the flow.
12. **The workspace header search actually searches.** It was a decorative input; `/invoices?q=`
    now filters by invoice number *or* buyer name, inside the tenant, and the filter
    survives the status tabs and pagination. LIKE wildcards in the term are treated as
    text.
13. **Marketing pages are auth-aware.** A signed-in member sees "Dashboard" / "+ New
    invoice" in the header instead of "Sign in" / "Create account".
14. **Readiness is graded against configuration**, not a literal 85 baked into the
    markup (the component reads `config('paykaro.finance_ready_score')`).
15. **`financing` table → `financings`**, per Laravel's plural table convention, and
    `invoices.new` is a redirect to `/invoices/create` so the resource routes are the
    only place invoice writes are declared.
16. **`alerts.read` marks read instead of deleting** (unchanged behaviour) and now answers
    with a count of what it dismissed.

Unchanged on purpose: the pipeline states and their meaning, the five seeded evidence
rows per invoice (four required, `contract` optional), the interest formula, the
finance queue's two entry states, the claim packet's rows, the two seeded demo
businesses and their 18 invoices, and the 404-not-403 isolation contract.

## Verifying it

- `php artisan migrate --seed` on a scratch SQLite file reproduces both demo tenants —
  seeded *through* `InvoiceWorkflow`, so the demo data satisfies the same rules as real data.
- `php artisan test` — see `tests/`; the arithmetic is pinned in `tests/Unit/ReceivablesTest.php`
  and the rest is exercised over HTTP (pipeline, isolation, Google provisioning, pages).
- `.github/workflows/ci.yml` runs Pint, the suite, and a boot job that migrates, seeds,
  serves and curls `/`, `/login`, `/pricing`, `/assets/app.css` plus the legacy
  redirects. (Adding or changing anything under `.github/workflows` needs a GitHub
  token with the `workflows` scope, so this file may sit one commit behind the rest of
  the port — check `git log .github/workflows/ci.yml` before trusting CI to cover it.)
- In the agent sandbox there is no native PHP and Packagist is unreachable, so the app
  could not be booted here: the PHP layer was checked with `bridge/lint.mjs` (a php-wasm
  `php -l`) and the Blade layer structurally (component/route/method cross-checks). Running
  `php artisan test` locally or in CI is the real gate.
