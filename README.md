# PayKaro — MSME invoice & receivables tracker

**PayKaro** turns an invoice into a finance-ready asset. It gives India's MSMEs one
pipeline to track what's owed, what's overdue, and what they could finance today —
with the evidence and interest numbers that make a delayed-payment or TReDS claim
actually stand.

A Laravel 12 application: routes, Eloquent models and Blade views. The design system
is the app's own (`public/assets/app.css`) and was ported markup-for-markup, so the
product looks the same as the flat-PHP original it replaced.

## What it does

- **One pipeline** for every invoice: `raised → accepted → financed → settled`, or
  `disputed` — each with a live balance and accruing statutory interest.
- **Evidence checklist** per invoice (purchase order, delivery acknowledgement, goods
  receipt note, GST-valid copy) so a financing or claim packet is never missing a document.
- **Readiness score** (0–100) that decides what belongs on the TReDS / finance queue,
  computed from evidence, buyer onboarding and how overdue the invoice is.
- **Interest & ageing** derived from the invoice date and the MSMED window — never
  entered by hand, so a claim stands on the right number.
- **Claim packet** for an MSEFC / mediation / arbitration filing, printed straight from
  the invoice with every gap named.

## Auth and multi-tenant isolation

- Email + password sign-up (`/signup`), and optional **Google Sign-In** through
  `laravel/socialite` — with no credentials configured the button is not rendered and
  the OAuth routes answer `404`.
- Each user belongs to exactly one **business (tenant)**.
- Isolation is a **global Eloquent scope** (`App\Tenancy\TenantScope`), so
  `Invoice::find($foreignId)` is already `WHERE business_id = <tenant>` and returns
  null: another business's invoice reads as *absent*, never as someone else's data.
  Writes are additionally gated by role — an owner and an accountant may change the
  book, a **viewer** only reads it, and only the owner edits the business's legal and
  bank identity.

## Stack

| Layer | What it is |
|-------|-----------|
| Framework | Laravel 12, PHP 8.3 |
| Database | SQLite by default (`database/paykaro.sqlite`), any Laravel driver via `.env` |
| Views | Blade components in `resources/views`, styling from `public/assets/app.css` |
| Auth | Laravel's session guard + `laravel/socialite` for Google |
| Money rules | `App\Services\Receivables`, configured by `config/paykaro.php` |

## Run it

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/paykaro.sqlite
php artisan migrate --seed        # two demo businesses, 18 invoices, 6 buyers
php artisan serve
```

Then sign in at <http://localhost:8000/login>:

| User | Email | Password | Business |
|------|-------|----------|----------|
| Sunita Rao | `sunita@shreeprecision.in` | `demo1234` | Shree Precision Components |
| Farhan Ali | `farhan@metrowceramics.in` | `demo1234` | MetRow Ceramics |

Full instructions, the Google configuration and how to point at MySQL are in
[`RUN.md`](RUN.md). [`MIGRATION.md`](MIGRATION.md) lists what changed on the way off
the flat-PHP implementation and what was deliberately kept.

## Tests

```bash
php artisan test
```

The suite is where the domain rules are pinned down: `tests/Unit/ReceivablesTest.php`
covers the arithmetic (45-day window, 3× bank-rate interest, ageing buckets,
readiness, TReDS eligibility) and `tests/Feature/*` covers the pipeline through HTTP,
tenant isolation, Google provisioning and the marketing pages.

## Layout

```
app/
  Enums/                  invoice status, ageing bucket, TReDS status, evidence type…
  Http/Controllers/       auth, invoices, buyers, workspace pages, marketing pages
  Http/Requests/          validation for every write (format rules for GSTIN/PAN/IFSC)
  Models/                 Business, User, Buyer, Invoice, InvoiceEvidence, Payment,
                          Financing, Dispute, Alert
  Policies/               who may touch what, per role
  Services/               Receivables (the rules), InvoiceWorkflow (the pipeline),
                          Dashboard, ClaimPacket, AccountProvisioner
  Support/                Money formatting, News, Pricing, helpers
  Tenancy/                TenantContext + TenantScope (the isolation layer)
bootstrap/app.php         middleware, guest/user redirects, tenant exception → 403
config/paykaro.php        MSMED numbers, readiness weights, currency, demo flag
database/migrations/      schema (11 migrations)
database/seeders/         DemoWorkspaceSeeder — the two demo tenants
public/assets/app.css     the ported design system
resources/views/          components/layouts (public, app, auth), marketing, workspace
routes/web.php            every URL, including the legacy `/invoice?id=` redirects
tests/
```

## Business rules in one place

`config/paykaro.php` holds the numbers a claim rests on — the `msme_due_days` window,
`default_tax_rate`, the `bank_rate × interest_multiplier` interest formula, the
`finance_ready_score` threshold and the readiness weights. `App\Services\Receivables`
is the only thing that reads them, so the ageing chart, the KPI cards, the finance
queue and the claim packet cannot drift apart.

```
PAYKARO_MSME_DUE_DAYS=60 PAYKARO_FINANCE_READY_SCORE=90 php artisan serve
```
