# PayKaro — Product Context

A functional map of the app: **what pages exist, who each is for, what actions
each offers, and what domain functions sit behind them.** No styling, no design
system — that lives in `public/assets/app.css` and is out of scope here.

Everything below was read out of the repository at commit `8f60b122`. If the code
and this file disagree, the code is right.

---

## 1. Who this is for

**Primary user:** an Indian MSME supplier — a small manufacturer or service
business that raises invoices against larger buyers and gets paid late.

The problem the product exists to solve: when a buyer delays payment, the supplier
usually cannot *prove* when the invoice was raised, what was delivered, or which
document proves acceptance. So they write it off. PayKaro keeps the dated evidence
and computes the statutory interest, which is what makes a delayed-payment claim or
a TReDS discount possible.

**Three roles** sit on top of that (`app/Enums/UserRole.php`):

| Role | Can do | Gate in code |
|------|--------|--------------|
| **Owner** | Everything, including the business's GSTIN / PAN / Udyam / bank details | `canManageBusiness()` — owner only |
| **Accountant** | Run the receivables book: raise, edit, move status, record payments/financing/disputes, toggle evidence, dismiss alerts | `canWrite()` — owner **or** accountant |
| **Viewer** | Read everything, change nothing | every mutation is refused |

**Other actors the app knows about but does not authenticate:**

- **Buyers** (`app/Models/Buyer.php`) — the customers who owe money. Recorded, never
  given a login. Typed `cpse | psu | private`, with a TReDS onboarding status
  (`unknown | yes | no`) that drives finance eligibility.
- **Financiers / TReDS exchanges** — named on a financing row, no account.
- **MSEFC / mediators / arbitrators** — the recipients of a printed claim packet.

**Multi-tenancy:** each user belongs to exactly one business. Isolation is a global
Eloquent scope (`app/Tenancy/TenantScope.php`), so a URL for another business's
invoice returns **404 "not found"** — never 403, never data.

---

## 2. Page groups

Four groups, in the order a visitor meets them.

```
A. Public / marketing   →  convince a supplier to sign up
B. Auth                 →  create a business + owner, or sign in
C. Workspace            →  the actual receivables work (the product)
D. Legacy redirects     →  old URLs kept alive
```

---

## 3. Group A — Public pages

Readable signed-out on purpose: pricing, help and news are what convince a supplier
before they sign up. All are `GET`, no auth.

| URL | Route name | What it does | For whom |
|-----|-----------|--------------|----------|
| `/` | `landing` | **Dual-purpose.** Signed-out: the marketing landing page. Signed-in: **redirects to `/dashboard`** | Visitor / member |
| `/pricing` | `pricing` | Three tiers from `app/Support/Pricing.php` — Starter (free, 25 invoices, 1 user), Pro (₹1,499/mo, unlimited, 3 users, TReDS queue + claim builder), Enterprise (custom, CA firms & lenders) | Visitor deciding to buy |
| `/help` | `help` | FAQ grouped as Getting started / TReDS & Financing / Claims & Disputes | Member, visitor |
| `/contact` | `contact` | Contact page | Visitor |
| `/terms` | `terms` | Terms of use, rendered from `app/Support/Legal.php` | Visitor, member |
| `/privacy` | `privacy` | Privacy document | Visitor, member |
| `/security` | `security` | Security document — deliberately lists what the product does **not** do (no CSP/HSTS, no encryption at rest, no MFA) | Visitor, auditor |
| `/news/{slug}` | `news.show` | One of three authored articles from `app/Support/News.php`; unknown slug redirects to `/#news` | Visitor |
| `/news` | `news.index` | Redirect to `/#news` on the landing page | Visitor |

**Actions available on these pages:** none but navigation and the header CTAs
("Sign in" / "Create account" signed-out, "Dashboard" / "+ New invoice" signed-in).
There is no contact form POST — `/contact` is informational.

---

## 4. Group B — Authentication

| URL | Method | Action | Notes |
|-----|--------|--------|-------|
| `/login` | GET | Show sign-in form | `guest` middleware |
| `/login` | POST | **Sign in** | Rate limited to 5 attempts per email + IP |
| `/signup` | GET | Show sign-up form | `guest` middleware |
| `/signup` | POST | **Create account** → creates a `Business` **and** its owner `User` in one step | Via `AccountProvisioner::register()` |
| `/auth/google` | GET | **Start Google OAuth** | Button is not rendered unless credentials are configured; routes answer 404 otherwise |
| `/auth/google/callback` | GET | Complete OAuth | Deliberately **not** `guest`-guarded, so an expired mid-flow session still completes. `AccountProvisioner::findOrCreateFromGoogle()` |
| `/logout` | POST | Sign out | Inside the `auth` group |

**Sign-up fields** (`RegisterRequest`): `business_name` (optional, defaults),
`name`, `email` (unique), `password` (min 8).

**Sign-in fields** (`LoginRequest`): `email`, `password`, `remember`.

Two behaviours worth knowing:
- A **Google-only account** has a `NULL` password. `LoginRequest` checks
  `hasPassword()` and tells the member to use the Google button rather than handing
  the hasher nothing.
- A **duplicate email** at sign-up is a field error on the form with input preserved,
  not a redirect with a query-string error.

---

## 5. Group C — Workspace

All routes are inside `auth`. Nav order (`components/layouts/app.blade.php`):
**Overview · Invoices · Customers · Finance · Reports · Settings.**

### 5.1 Overview — `/dashboard`

**For:** the owner/accountant opening the app. Read-only except one action.

| Action | Method + route | Who |
|--------|---------------|-----|
| Dismiss the "Needs attention" list | `POST /alerts/read` (`alerts.read`) | can write — dismissing hides it for the whole business, so it counts as a write |
| New invoice | link → `/invoices/create` | — |
| Open an invoice / a report | link → `invoices.show`, `reports` | — |

**What it shows** — `DashboardSummary`, computed by `app/Services/Dashboard.php`:
`total` outstanding, `overdue` + `overdueCount`, `interest` accrued,
`dueIn30Days`, `invoiceCount`, `financeReadyCount`, and `buckets` (the ageing chart).

### 5.2 Invoices — the pipeline

**This is the product.** Six states (`app/Enums/InvoiceStatus.php`):
`draft → raised → accepted → financed → settled`, with `disputed` branching off.
`draft` is excluded from receivables totals on purpose.

| URL | Method | Action | Who |
|-----|--------|--------|-----|
| `/invoices` | GET | **List**, paginated 15/page | all roles |
| `/invoices/create` | GET | New-invoice form | can write |
| `/invoices` | POST | **Raise an invoice** → seeds the 5 evidence rows, computes due date + totals | can write |
| `/invoices/{invoice}` | GET | Invoice detail — the hub every other action hangs off | all roles |
| `/invoices/{invoice}/edit` | GET | Edit form | can write |
| `/invoices/{invoice}` | PUT/PATCH | **Update** — due date and totals recomputed from the invoice date | can write |
| `/invoices/{invoice}/status` | PATCH | **Move the pipeline** — set any status | can write |
| `/invoices/{invoice}/evidence` | PUT | **Toggle one evidence item** present/absent | can write |
| `/invoices/{invoice}/claim` | GET | **Claim packet** — printable filing document | all roles (read) |
| `/invoices/{invoice}/payments` | POST | **Record a payment** — a payment that clears the balance settles the invoice | can write |
| `/invoices/{invoice}/financings` | POST | **Record a TReDS discount** — financier, rate, amount, date | can write |
| `/invoices/{invoice}/disputes` | POST | **Open a claim** — forum + computed filing deadline | can write |

**List filters** (`InvoiceController::index`): `?status=` (tab), `?buyer=`
(dropdown), `?q=` (search on invoice number **or** buyer name, tenant-scoped, LIKE
wildcards treated as literal text). Filters survive the tabs and pagination.

**Invoice fields** (`StoreInvoiceRequest` / `UpdateInvoiceRequest`): `number`
(unique per business), `buyer_id`, `invoice_date`, `base_amount`, `tax_amount`
(optional — blank means "work it out", an explicit `0` means "no GST"), `notes`.

**Payment fields:** `amount`, `paid_on` (cannot be in the future), `method`,
`reference`. A payment on a settled invoice is refused with a message, not recorded.

**Financing fields:** `financier`, `discount_rate` (0–100), `amount_disbursed`,
`disbursed_on`.

**Dispute fields:** `forum` — `msefc | mediation | arbitration`. The deadline is
computed from the invoice due date and stamped once; a statutory clock does not
move with later edits.

**The evidence checklist** (`app/Enums/EvidenceType.php`) — 5 rows seeded per
invoice, 4 required:

| Type | Label | Required |
|------|-------|----------|
| `po` | Purchase order | ✅ |
| `delivery_ack` | Delivery acknowledgement | ✅ |
| `grn` | Goods receipt note | ✅ |
| `invoice_copy` | GST-valid invoice copy | ✅ |
| `contract` | Contract | offered, not scored |

A missing document is a recorded `present = false`, never an absent row.

### 5.3 Customers — `/buyers`

**For:** recording who owes money. Buyers are not users.

| URL | Method | Action | Who |
|-----|--------|--------|-----|
| `/buyers` | GET | List buyers | all roles |
| `/buyers/create` | GET | New-buyer form | can write |
| `/buyers` | POST | **Create buyer** | can write |

**Fields** (`StoreBuyerRequest`): `name`, `gstin` (format-validated), `type`
(`cpse | psu | private`), `treds_onboarded` (`unknown | yes | no`).

There is **no edit or delete** for buyers — `BuyerPolicy` defines `update()` but no
route or form exposes it. Creating is the only write.

### 5.4 Finance — `/treds`

**For:** the owner deciding what to discount today. Read-only.

Lists invoices the `Dashboard::financeQueue()` selects — the two entry states that
can be financed — separated into *financeable now* versus *held back*, with the
reason named (usually the buyer is not on TReDS, or an evidence item is missing).
Actions are links out to `invoices.create` and `invoices.show`.

### 5.5 Reports — `/reports`

**For:** the accountant reviewing ageing. Read-only.

Ageing buckets across the book (`current`, `1-30`, `31-60`, `61-90`, `90+`) plus
outstanding by buyer (`Dashboard::outstandingByBuyer()`). Links out to `/treds`.

### 5.6 Settings — `/settings`

**For:** the owner only. This is the one page where role matters visibly.

| URL | Method | Action | Who |
|-----|--------|--------|-----|
| `/settings` | GET | Edit business identity | all roles may view |
| `/settings` | PUT | **Update** name, GSTIN, PAN, Udyam, bank name/account/IFSC, TReDS registration | **owner only** — an accountant or viewer POSTing gets 403 |

`BusinessPolicy::update()` requires `canManageBusiness()`. Rationale in the code:
these are the details settled money is paid against and the ones a claim cites.

---

## 6. Group D — Legacy redirects

The flat-PHP app used query-string URLs that got shared in email and WhatsApp and
keep working. All 302.

| Old URL | Goes to |
|---------|---------|
| `/invoices/new` | `/invoices/create` (registered **before** the resource route, or `{invoice}=new` matches first and 404s) |
| `/invoice?id=N` | `/invoices/N` |
| `/invoice?edit=N` | `/invoices/N/edit` |
| `/invoice` (no id) | `/invoices` |
| `/claim?id=N` | `/invoices/N/claim` |
| `/claim` (no id) | `/invoices` |

---

## 7. Domain functions — what actually computes things

**One rule:** `app/Services/Receivables.php` is the **only** reader of
`config/paykaro.php`. Nothing else may compute interest, ageing or readiness, so the
dashboard, the finance queue, the ageing chart and the claim packet cannot drift
apart.

### `Receivables` — the rules

| Function | What it answers |
|----------|-----------------|
| `dueDate($invoiceDate)` | Due date = invoice date + 45 days. **A client-sent due date is never trusted** |
| `taxOn($base, $explicitTax)` | GST — 18% default, `0` means none, blank means compute |
| `total($base, $tax)` | Invoice total |
| `overdueDays($due, $status, $today)` | Days past due; zero unless overdue and not settled |
| `interest($total, $overdueDays)` | Statutory interest — bank rate 6.5% × 3, accrued daily |
| `ageing($overdueDays)` | Which bucket (`AgeingBucket`) |
| `balance($total, $paid, $status)` | Outstanding — never negative |
| `readiness(...)` | 0–100 score: evidence share × 70, + up to 25 for buyer TReDS onboarding, +10 if overdue |
| `isFinanceReady($score)` | Score ≥ 85 (`paykaro.finance_ready_score`) |
| `tredsStatus(...)` | `na \| ready \| pending_buyer_onboard \| financed \| ineligible` — computed, never stored |
| `fallsDueWithin()`, `daysUntil()` | Deadline maths for alerts and the "File before" line |

**Derived, never stored:** balance, interest, ageing, readiness and TReDS status are
all computed on read. `invoices` stores only base, tax and total.

### `InvoiceWorkflow` — every write goes through here

`create()` · `update()` · `setStatus()` · `setEvidence()` · `recordPayment()` ·
`recordFinancing()` · `startDispute()`

The demo tenants are seeded **through this class**, not by inserting rows — so demo
data satisfies the same rules as real data.

### `Dashboard` — the read side

`overview()` → `DashboardSummary` · `summarise($invoices)` ·
`outstandingByBuyer()` · `financeQueue()`

### `ClaimPacket` — the filing document

`rows($invoice)` (the packet's line items) · `missingCount($invoice)` ·
`interestLabel()`

### `AccountProvisioner` — identity

`register($data)` (business + owner) · `findOrCreateFromGoogle($profile)`

### Validation rules (`app/Rules/`)

`Gstin`, `Pan`, `Ifsc`, `UdyamRegistration` — format-checked and upper-cased before
storage, because those exact strings are what a claim cites.

---

## 8. Data model

| Table | What it holds |
|-------|---------------|
| `businesses` | Tenant root — name, GSTIN, PAN, Udyam, bank details, TReDS flag |
| `users` | `business_id` FK, unique email, nullable password hash (OAuth-only users), `role`, `provider`, `google_id`, `avatar_url` |
| `buyers` | `business_id` FK, `type`, TReDS onboarding |
| `invoices` | `business_id` + `buyer_id`, number unique **per business**, dates, `DECIMAL(14,2)` amounts, status, once-stamped `approval_date` / `paid_date` |
| `invoice_evidences` | One row per document type, seeded when the invoice is raised |
| `payments` | Money in |
| `financings` | TReDS discounting |
| `disputes` | Claim forum + the `deadline_on` it was filed with |
| `alerts` | Per-business attention items, dismissed by setting `read_at` |

No `oauth_states` table (state rides the session) and no custom `sessions` table.

---

## 9. Frontend structure

Three Blade layouts, one per context:

| Layout | Used by |
|--------|---------|
| `components/layouts/public.blade.php` | Marketing, help, contact, legal, news |
| `components/layouts/app.blade.php` | The whole workspace |
| `components/layouts/auth.blade.php` | Login / sign-up split screen |

Views are grouped by area — `auth/`, `buyers/`, `invoices/`, `workspace/`,
`marketing/`, `errors/` — with shared UI in `components/` (badges, KPI card,
progress, status timeline, pagination, page header) and `partials/` (a themed
calendar that decorates every `<input type="date">`, and the theme toggle).

**There is no build step.** No Vite, no Tailwind, no bundler. The stylesheet is a
committed static asset, and the calendar popup is inline script.

**There is no JavaScript-driven state.** Every action is a plain HTML form POST to
a route above; the server re-renders. Search, filters and pagination are query
strings.

### What does **not** exist yet

Worth knowing before planning any new work — these were verified absent:

- **No email of any kind.** No `resources/views/mail/`, no `Mailable` class, no
  `Mail::` call anywhere. There is no "invoice sent to buyer", no overdue reminder,
  no evidence request.
- **The claim packet is not print-styled.** `invoices/claim.blade.php` has a
  `window.print()` button but there is no `@media print` rule anywhere, so printing
  includes the nav and buttons.
- **The favicon is a 0-byte file.**
- **No `og:` / `twitter:` meta**, so a shared link renders with no preview.
- **No buyer edit or delete**, and no delete for invoices, payments or financings.
- **No export** (CSV/PDF) on reports or the invoice list.
- **No user management UI** — roles exist and are enforced, but there is no page to
  invite an accountant or add a viewer.

---

## 10. Demo data

Seeded by `database/seeders/DemoWorkspaceSeeder.php`, through `InvoiceWorkflow`:

| Business | Owner | Buyers | Invoices |
|----------|-------|--------|----------|
| Shree Precision Components | Sunita Rao (`sunita@shreeprecision.in`) | 4 | 15 — settled, financed, disputed, overdue |
| MetRow Ceramics | Farhan Ali (`farhan@metrowceramics.in`) | 2 | 3 |

Both passwords `demo1234`. `INV-2026-007` carries a real MSEFC dispute row with its
filing deadline, which is what the claim packet's "File before" line serves.

Seeding is idempotent — it skips itself the moment any user exists.

---

## 11. Route-name reference

Every URL with its Laravel route name, for building forms and links. Names come
from `routes/web.php`; the invoice ones are generated by `Route::resource`.

| URL | Method | Route name |
|-----|--------|-----------|
| `/` | GET | `landing` |
| `/pricing` | GET | `pricing` |
| `/help` | GET | `help` |
| `/contact` | GET | `contact` |
| `/terms` | GET | `terms` |
| `/privacy` | GET | `privacy` |
| `/security` | GET | `security` |
| `/news` | GET | `news.index` |
| `/news/{slug}` | GET | `news.show` |
| `/login` | GET | `login` |
| `/login` | POST | *(unnamed)* |
| `/signup` | GET | `register` |
| `/signup` | POST | *(unnamed)* |
| `/auth/google` | GET | `auth.google` |
| `/auth/google/callback` | GET | `auth.google.callback` |
| `/logout` | POST | `logout` |
| `/dashboard` | GET | `dashboard` |
| `/alerts/read` | POST | `alerts.read` |
| `/invoices` | GET | `invoices.index` |
| `/invoices/create` | GET | `invoices.create` |
| `/invoices` | POST | `invoices.store` |
| `/invoices/{invoice}` | GET | `invoices.show` |
| `/invoices/{invoice}/edit` | GET | `invoices.edit` |
| `/invoices/{invoice}` | PUT/PATCH | `invoices.update` |
| `/invoices/{invoice}/status` | PATCH | `invoices.status` |
| `/invoices/{invoice}/evidence` | PUT | `invoices.evidence` |
| `/invoices/{invoice}/claim` | GET | `invoices.claim` |
| `/invoices/{invoice}/payments` | POST | `invoices.payments.store` |
| `/invoices/{invoice}/financings` | POST | `invoices.financings.store` |
| `/invoices/{invoice}/disputes` | POST | `invoices.disputes.store` |
| `/buyers` | GET | `buyers.index` |
| `/buyers/create` | GET | `buyers.create` |
| `/buyers` | POST | `buyers.store` |
| `/treds` | GET | `treds` |
| `/reports` | GET | `reports` |
| `/settings` | GET | `settings.edit` |
| `/settings` | PUT | `settings.update` |
| `/invoices/new` | GET | *(redirect → `/invoices/create`)* |
| `/invoice` | GET | *(legacy redirect, unnamed)* |
| `/claim` | GET | *(legacy redirect, unnamed)* |

Note there are **no `destroy` routes** — nothing in the app deletes a record.
`AuthenticatedSessionController::destroy` is the method behind `logout`, not a
resource deletion.

---
