# PayKaro — MSME invoice & receivables tracker

**Tagline:** Turn invoice mess into an evidence-complete, finance-ready pipeline.

## Why this product

India's MSMEs live on receivables, but the money owed against them is a tangle of
spreadsheets, WhatsApp messages and missing purchase orders. When the due date
passes, most small suppliers cannot prove *when* the invoice was raised, *what*
was delivered, or *which* document proves acceptance — so they write the invoice
off or wait months. The 2026 MSME Development (Amendment) Bill tightens TReDS
mandates for CPSE buyers and strengthens the MSEFC delayed-payment forum, which
means the businesses that keep clean, complete, **dated** records now have real
leverage: statutory interest (3× bank rate), a time-bound dispute forum and
discounted invoice financing.

PayKaro is the workflow tool that turns an invoice into a finance-ready asset:

- **One pipeline** for every invoice: raised → accepted → financed → settled
  (or disputed), each with a live balance and accruing interest.
- **Evidence checklist** per invoice (PO, delivery ack, GRN, GST-valid copy) so
  the finance/dispute packet is never missing a document.
- **TReDS / financing queue** that separates what can be financed today from
  the gaps holding it back.
- **Interest & ageing** computed from the invoice (not from a spreadsheet), so a
  claim always stands on the correct number.
- **Guided evidence packet** for an MSEFC/mediation/arbitration claim.

## Scope (v1)

A single-database web app with **real auth + per-business isolation**:

- Users, bcrypt hashing (Laravel's `hashed` cast), cookie-backed sessions.
- Each user belongs to exactly one **business (tenant)**.
- Every query is scoped to the user's business — a user can never see another
  business's invoices, buyers, payments or disputes (enforced by a global Eloquent
  scope, not at the call sites).
- **Email + password sign-up** at `/signup` (creates a business + owner).
- Optional **Google Sign-In** (OAuth 2.0 Authorization Code, via `laravel/socialite`).
- Roles are enforced, not merely stored: `owner` and `accountant` may write the
  book, `viewer` reads it, and only the `owner` changes the business's legal and
  bank identity.

## Stack

- **Backend:** PHP 8.3 on Laravel 12 — routed controllers, Eloquent models, form
  requests, policies, Blade views. No Vite/Tailwind build step: the stylesheet is
  committed as a static asset.
- **Database:** driver-agnostic through `.env`; SQLite for the zero-config demo
  (`database/paykaro.sqlite`), MySQL/Postgres unchanged.
- **Frontend:** server-rendered Blade with the app's own "ink & paper" design system
  (`public/assets/app.css`, DM Sans + Fraunces, moss/gold/coral tokens) — responsive,
  with a light/dark toggle. Ported markup-for-markup from the pre-Laravel app, so the
  product looks the same.
- **Domain layer:** `App\Services\Receivables` is the only reader of
  `config/paykaro.php`; nothing else may compute interest, ageing or readiness.

## Data model (`database/migrations`)

- `businesses` — the tenant root (name, GSTIN, PAN, Udyam, bank details, TReDS flag).
- `users` — `business_id` FK, email UNIQUE, password hash (nullable for OAuth-only
  users), role (`owner|accountant|viewer`), provider (`email|google`), indexed
  `google_id`, `avatar_url`. Laravel's password-reset and session tables ship with
  the base migration but the app uses the file session driver by default.
- `buyers` — `business_id` FK; type + TReDS onboarding status.
- `invoices` — `business_id` FK, `buyer_id` FK, `number` unique per business
  (`unique(business_id, number)`), dates, `DECIMAL(14,2)` amounts, status, and the
  once-stamped `approval_date` / `paid_date`. **No** stored balance, interest, ageing
  or TReDS status: those are derived.
- `invoice_evidences` — one row per document type, seeded when the invoice is raised
  (`po`, `delivery_ack`, `grn`, `contract`, `invoice_copy`), so a missing document is
  a recorded `present = false`.
- `payments`, `financings`, `disputes` — money movement, discounting, claim forums
  (each dispute keeps the `deadline_on` it was filed with; a statutory clock must not
  move with later edits).
- `alerts` — per-business attention items, dismissed by setting `read_at`.

No `oauth_states` and no custom `sessions` table: the OAuth state rides the session,
and sessions are the framework's business.

## Domain rules (`config/paykaro.php`, read by `App\Services\Receivables`)

- MSME due window: **45 days** (`PAYKARO_MSME_DUE_DAYS`), applied to the invoice date —
  a client-sent due date is never trusted.
- Default GST: **18%** (`PAYKARO_DEFAULT_TAX_RATE`); an explicit `0` means "no GST",
  and a blank field means "work it out".
- Statutory interest: **bank_rate (6.5%) × 3** per annum (`PAYKARO_BANK_RATE`,
  `PAYKARO_INTEREST_MULTIPLIER`), accrued daily, zero unless the invoice is overdue
  and not settled.
- Ageing buckets: Current, 1–30, 31–60, 61–90, 90+ days past due.
- Required inbound evidence: `po`, `delivery_ack`, `grn`, `invoice_copy`.
- Readiness: evidence share × 70, +20 for a buyer confirmed on TReDS (+5 for a
  confirmed "no", 0 for unknown), +10 when overdue; **≥ 85**
  (`PAYKARO_FINANCE_READY_SCORE`) means financeable on the TReDS queue.
- Balance never goes negative; a payment that clears the balance settles the invoice.
- Session lifetime: 7 days (`SESSION_LIFETIME=10080`).

## Multi-tenant isolation

`App\Tenancy\TenantContext` resolves the tenant — an explicitly bound `Business`
(seeders, console, tests) or the authenticated user's business, never anything from
the request. `App\Tenancy\TenantScope` is a global Eloquent scope on `Business`,
`Buyer`, `Invoice`, `Alert` and everything hanging off them: every select carries
`WHERE business_id = <tenant>` and every insert is stamped with the resolved tenant,
so a payload cannot write rows into another business by supplying its id. Querying
without a tenant raises `TenantNotResolved` (403, or a JSON error for an API request)
rather than returning the whole table.

A user opening a URL for another business's invoice gets **404 "Invoice not found"**
— not a 403, not data. Policies re-check ownership on top, because a policy is the
last line of defence for queries written outside the scope (queue jobs, console).

## Demo logins (seeded)

| User | Email | Business | Tenant |
|------|-------|----------|--------|
| Sunita Rao | `sunita@shreeprecision.in` | Shree Precision Components | 1 |
| Farhan Ali | `farhan@metrowceramics.in` | MetRow Ceramics | 2 |

Both use password `demo1234`. Sign in at `/login` with one of these test accounts.

## Run

See `RUN.md`.
