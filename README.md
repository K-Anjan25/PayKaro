# PayKaro — invoice & receivables tracker

**PayKaro** turns an invoice into a finance-ready asset. One pipeline to see what's
owed, what's overdue, and what could be financed today — with the interest maths and
the evidence trail that make a delayed-payment, discounting or TReDS claim stand up.

It is built for Indian B2B businesses: the statutory clock, the compounding interest
and the MSEFC / TReDS paperwork are part of the product rather than a spreadsheet
bolted onto it.

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

## Deploy

Production is the standard Laravel shape — PHP 8.3 + Composer, document root on
`public/`, MySQL/PostgreSQL, SMTP — and nothing is built: the CSS is committed and
there is no queue worker or scheduled task. The three deployment-specific steps are
setting the `PAYKARO_*` statutory numbers, turning `PAYKARO_DEMO=false`, and never
running `migrate --seed` against a real book.

[`DEPLOY.md`](DEPLOY.md) is the runbook: server setup, a production `.env`, the
Nginx/FPM config, the release script, verification, multi-server notes and the
`APP_KEY` / bank-rate traps.

Two more targets are wired up in the repo:

- **Any container platform** — `Dockerfile` (FrankenPHP + Caddy, PHP 8.3, one
  process on `$PORT`), `Caddyfile`, `docker/entrypoint.sh` and a
  `docker-compose.yml` for local parity. Fly.io (Mumbai), Railway, Render,
  DigitalOcean App Platform (Bangalore), Cloud Run, AWS and Azure all deploy the
  same image — see §13.
- **Free, always on** — Oracle Cloud's Always Free tier (regions in Mumbai and
  Hyderabad) or Northflank's free Sandbox; §14 also covers Render + Neon and what
  each free tier really costs you. [`DEPLOY-ORACLE.md`](DEPLOY-ORACLE.md) is the
  step-by-step for the Oracle route: VM → firewall → Nginx → TLS on a free
  `sslip.io` hostname → backups.
- **Vercel** — `vercel.json` + `api/index.php` for the serverless PHP runtime
  (§12). No persistent disk there, so the database must be an external
  Postgres/MySQL; SQLite (the local default) cannot be used.
  [`DEPLOY-VERCEL.md`](DEPLOY-VERCEL.md) is the step-by-step: Neon database,
  migrations from GitHub Actions, env vars, verification, troubleshooting.

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

## Contributing

Bug reports and pull requests are welcome — [`CONTRIBUTING.md`](CONTRIBUTING.md) has
the setup, the checks CI runs (`php artisan test`, `pint --test`, and
`bridge/overflow-sim.mjs` when a grid or `app.css` changes) and the rules the test
suite enforces: derived money, fail-closed tenancy, policy-gated roles, token
colours and no invented figures.

## License

MIT — see [`LICENSE`](LICENSE). `composer.json` already declares the same licence.
