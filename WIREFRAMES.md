# PayKaro — Wireframe Brief

The layout-and-copy layer for wireframing. **`CONTEXT.md` says what the product
does; this file says what each screen contains.** Labels, section order, table
columns, form fields, button text and empty states are all quoted from the live
Blade views, so wireframes built from this will match the real product.

Structure and copy only — no styling, no CSS. That is the design system's job.

---

## How to read this

Each screen gives:

- **Blocks** — the sections, in the order they render top to bottom
- **Columns / fields** — exact table headers and form labels
- **Buttons** — exact label text
- **Empty state** — what renders when there is no data
- **Role differences** — what a viewer does *not* see

Money is always formatted `₹1,23,456.00` (Indian grouping). Chart axes use the
compact form `₹2.5L` / `₹40K`.

---

## Global chrome

### Workspace shell (every `/dashboard`+ page)

```
┌ utility bar: [business name | "Demo workspace"] · Pricing · Help ······ Contact · Sign out
├ header: [logo + wordmark]  [search]  [+ New invoice]
├ nav: Overview · Invoices · Customers · Finance · Reports · Settings
└ page content
```

Nav labels are exactly: **Overview, Invoices, Customers, Finance, Reports,
Settings** — note "Customers" in nav but "Buyer" in table headers.

### Public shell (marketing, help, legal)

```
┌ utility bar: [MSME receivables tag] · Workflow · Financing · Pricing ······ Contact
├ header: [logo + wordmark]  [nav]  [Sign in] [Create account]
└ page content  →  footer
```

Signed-in members see **Dashboard** / **+ New invoice** instead of Sign in /
Create account.

### Auth shell (login, signup)

Split screen: form on one side, headline panel on the other.
- Panel headline (login/signup): `Turn invoice mess into finance-ready receivables.`
- Panel tag: `MSME invoice & receivables tracker`

---

## Screen 1 · Overview — `/dashboard`

**Blocks, in order:**

1. **KPI row — exactly 4 cards**, in this order:
   | Card label | Value |
   |---|---|
   | Outstanding | money |
   | Overdue | money + overdue count |
   | Interest | money (statutory interest accrued) |
   | Receivable in 30d | money |
2. **Needs attention** — alert list. Each alert links to its invoice.
   Button: **`Dismiss all`** (POST, one button for the whole list).
3. **Ageing Summary** — bar chart over 5 buckets, y-axis in compact money.
4. **Receivables Status Pipeline** — one row per ageing bucket.
5. **Recent Invoices** — short list linking to each invoice.

**Role difference:** a viewer sees everything but **not** the `Dismiss all` button.

---

## Screen 2 · Invoices — `/invoices`

**Blocks, in order:**

1. **Status tabs** — exactly these, in this order:
   `All · Raised · Accepted · Financed · Settled · Disputed`
   (`Draft` is deliberately **not** a tab.)
2. **Filters** — buyer dropdown + search box. Search matches invoice number **or**
   buyer name. Filters persist across tabs and pagination.
3. **Table — exactly 7 columns:**
   `Invoice | Customer | Due Date | Balance | Ageing | Status | Ready`
4. **Pagination** — 15 rows per page.

**Empty states:**
- All tab, no data → heading `No invoices yet`
- A filtered tab, no matches → heading `Nothing in this state`,
  body `No invoices are currently {status}.`

**Button:** `+ New invoice` → `/invoices/create`. Hidden from viewers.

---

## Screen 3 · Invoice detail — `/invoices/{id}`

**The busiest screen.** This is the hub every action hangs off. Blocks in order:

1. **Header** — invoice number, buyer, status badge, balance.
   Links: `Edit`, `View claim packet`.
2. **Move it forward** — status pipeline control.
3. **Evidence checklist** — 5 rows, each a toggle:
   | Row label | Short label | Required |
   |---|---|---|
   | Purchase order | PO | ✅ |
   | Delivery acknowledgement | Delivery ack | ✅ |
   | Goods receipt note | GRN | ✅ |
   | GST-valid invoice copy | GST copy | ✅ |
   | Contract | Contract | optional |
4. **Payments** — list + inline form. Button: **`Record payment`**
   Fields: amount · paid on · method · reference
5. **Financing & TReDS** — Button: **`Finance this`**
   Fields: financier · discount rate · amount disbursed · disbursed on
6. **Dispute / claim** — Button: **`Start claim`**
   Field: forum (MSEFC / Mediation / Arbitration). The filing deadline is computed
   and shown, not entered.
7. **Readiness** — score out of 100 with the reason it is not higher.
8. **Buyer** — buyer card, links to `/buyers`.
9. **Notes** — free text, only if present.

**Role difference:** a viewer sees all of it read-only — no status control, no
toggles, no payment/finance/claim forms.

---

## Screen 4 · New / Edit invoice — `/invoices/create`, `/invoices/{id}/edit`

One form, same fields for both. Labels in this order:

| Label | Control |
|---|---|
| Invoice number | text |
| Buyer | select — with an inline **`Add buyer`** escape hatch |
| Invoice date | date (themed calendar popup) |
| Due date | **read-only** — computed as invoice date + 45 days. Never editable |
| Base amount (₹) | number |
| Tax — GST | number; blank = auto 18%, `0` = no GST |
| Total (auto) | **read-only**, computed |
| Notes | textarea |

**Button:** `Save`.

Two facts worth drawing into the wireframe: the due date and the total are
*outputs*, not inputs. Any wireframe that makes them editable is wrong.

---

## Screen 5 · Customers — `/buyers`

**Table — exactly 5 columns:** `Buyer | GSTIN | Type | TReDS | Outstanding`

Heading shows a count: `4 buyers` / `1 buyer`.
Empty state: `No buyers yet.`
Button: **`Add buyer`**.

There is **no edit and no delete** for a buyer. Only create.

---

## Screen 6 · New buyer — `/buyers/create`

Fields: `Buyer name` · `GSTIN` · `Type` (CPSE / PSU / Private) ·
`TReDS onboarded?` (Unknown / Yes / No)

**Button:** `Save`.

---

## Screen 7 · Finance — `/treds`

**Blocks:**

1. **KPI card** — `Finance-ready` with the value `N of M`, and the trend line
   `needs 85/100 readiness`.
2. **Ready list** — invoices that can be discounted today.
3. **Blocked list** — same invoices, each with the reason it is held back
   (usually: buyer not on TReDS, or an evidence item missing).

Empty state: heading `Nothing in the queue`, body
`An invoice enters the queue once it is raised and not yet settled or disputed.`

---

## Screen 8 · Reports — `/reports`

**Blocks:** `Outstanding by buyer` (empty: `No outstanding invoices.`) ·
`Summary` · `Ageing`. Links out to `/treds`. Read-only, no actions.

---

## Screen 9 · Settings — `/settings`

**Blocks:** `Account` and the business identity form.

Fields: `Business name` · `GSTIN` · `PAN` · `Udyam no.` · `Bank name` ·
`Account number` · `IFSC` · `TReDS registered?`

**Button:** `Save`.

**Role difference — the important one:** an accountant or viewer can open this
page but **cannot submit it**. Their save returns 403. If you wireframe a
role-switcher, this is the screen where it shows.

---

## Screen 10 · Claim packet — `/invoices/{id}/claim`

A printable document, not a dashboard. A key/value table with a `Value` column,
the evidence gaps named, the interest schedule, and a **File before** date.

**Button:** **`Print packet`** — the only action on the page.

Note: this page currently has **no print stylesheet**, so a wireframe for the
printed artefact is genuinely new work, not a copy of the screen.

---

## Screen 11 · Login / Signup

**Login:** heading `Welcome back` · Google button · `Email` · `Password` ·
"remember me" checkbox · button **`Sign in →`**

**Signup:** heading `Create your account` · Google button labelled
`Sign up with Google` · `Business name` · `Your name` · `Work email` ·
`Password` · button **`Create account →`**

Sign-up creates the business *and* its owner in one step — there is no separate
"create company" screen.

---

## Screen 12 · Landing page — `/`

Ten `<section>` blocks, in this exact order:

| # | Section | Anchor |
|---|---------|--------|
| 1 | **Hero** — headline + CTA `Try PayKaro free` | — |
| 2 | **Stat bar** — 4 stats | — |
| 3 | **Workflow** — the 4-step pipeline as cards: `01 Raised · 02 Accepted · 03 Financed · 04 Settled` | `#workflow` |
| 4 | **Finance** — 2-up | `#finance` |
| 5 | **Evidence** — 3-up | `#evidence` |
| 6 | **News** — image card grid | `#news` |
| 7 | **Impact** — dark section with stats | `#impact` |
| 8 | **Claims** — split | `#claims` |
| 9 | **Testimonials** | — |
| 10 | **Closing CTA** | — |

Only sections 3–8 carry anchor ids; the nav links to `#workflow`, `#finance`,
`#news`, `#impact` and `#claims`.

Hero headline: `Make every invoice count.`
Hero lede: one sentence about turning an invoice into a finance-ready asset.

⚠️ **Do not wireframe the stat bar with real-looking numbers.** The current
`₹4.2Cr+ Receivables tracked` figure is a hard-coded string with no data behind
it, and the app only ships demo data. See `BRAND_PLAN.md` §4. Wireframe it with
placeholder tokens instead.

---

## Responsive behaviour

The app is responsive. The rules that matter for wireframes:

- Grid tracks shrink to zero rather than overflowing, so long strings
  (`sunita@shreeprecision.in`, `SESSION_SECURE_COOKIE=true`) break inside their
  column instead of widening the page.
- **Tables scroll horizontally** on narrow viewports rather than losing columns.
- The calendar popup is clamped to the viewport width.

So: plan for tables that scroll, not tables that reflow into cards.

---

## What does NOT exist — do not wireframe these

Verified absent from the codebase. If Framer invents them, they become
requirements nobody has agreed to:

| Missing | Consequence |
|---------|-------------|
| **Any email screen** | No "invoice sent", no reminder preview. None exist |
| **Buyer edit / delete** | Create only |
| **Invoice / payment / financing delete** | Nothing in the app deletes a record |
| **User management** | Roles exist and are enforced, but there is no invite/add-member screen |
| **Export (CSV / PDF)** | None on reports or the invoice list |
| **Print stylesheet** | The claim packet prints with nav and buttons on it |
| **Contact form POST** | `/contact` is informational only |
| **Onboarding / empty-account tour** | Sign-up lands straight on the dashboard |

---

## Sample data for wireframes

Real values from the seeded demo workspace — use these instead of lorem ipsum so
the wireframes are checked against plausible content:

| Business | Owner | Buyers | Invoices |
|---|---|---|---|
| Shree Precision Components | Sunita Rao · `sunita@shreeprecision.in` | 4 | 15 |
| MetRow Ceramics | Farhan Ali · `farhan@metrowceramics.in` | 2 | 3 |

- Invoice numbers run `INV-2026-001` … `INV-2026-015` and `INV-2026-101` … `103`.
- `INV-2026-007` is the one with a live MSEFC dispute and a filing deadline — use
  it when wireframing the dispute and claim screens.
- Statuses across the book include settled, financed, disputed and overdue, so a
  single invoice list can show every badge.

**Ageing buckets** (use these five labels, verbatim):
`Current · 1-30 · 31-60 · 61-90 · 90+`
