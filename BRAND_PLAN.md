# PayKaro — Brand Identity Build Plan

A build plan for PayKaro's brand, sequenced on the five-phase process in Alina
Wheeler's *Designing Brand Identity*. Every item below is anchored to a file that
exists in this repository today, so the plan can be worked top-to-bottom without
inventing context.

> **Provenance.** The phase structure (Part 1 *Basics*; Part 2 *Process* —
> conduct research, clarify strategy, design identity, create touchpoints, manage
> assets; Part 3 *Best Practices*) follows the book's published table of contents.
> The book file itself was **not** read — the upload did not reach the sandbox.
> Where this plan uses Wheeler's vocabulary (audit, brand brief, touchpoints, brand
> champions, guidelines), it means what the published contents list those sections
> as covering. Anything below that turns on a specific page of the book is marked
> `[needs the book]`.

---

## 0. Baseline — the brand audit, already run

An audit is Phase 1, but half of it is a repository read. Here is what PayKaro
actually has, with the file that proves it.

### What exists

| Asset | Where | State |
|-------|-------|-------|
| Wordmark | `resources/views/components/brand-wordmark.blade.php` | The two syllables render as separate spans, split at the camelCase boundary so a renamed deployment stays correct |
| Brand mark | `resources/views/components/logo-mark.blade.php` | 34×34 SVG — a rounded tile with a ₹ glyph. Self-described as "the only brand asset in the app" |
| Token system | `public/assets/app.css` `:root` | Every value is a named CSS custom property, defined once for both themes. One accent is reserved for overdue/alerts |
| Typography | `public/assets/app.css` | DM Sans (body) + Fraunces (display), loaded from Google Fonts in all three layouts |
| Theme toggle | `resources/views/partials/theme-toggle.blade.php` | Ships two full token sets, switched via `html.dark` |
| Design system name | `public/assets/app.css` header | "Northstar / institutional editorial" — inspired by university + civic sites |
| Pricing as brand | `app/Support/Pricing.php` | Starter (free) / Pro (₹1,499/mo) / Enterprise (custom) |
| Tagline config key | `config/paykaro.php:16` | `paykaro.tagline` exists and is read in 4 places — but it holds the *descriptor*, and the real headline is not in config (see §1.1) |
| Editorial voice | `app/Support/News.php`, `app/Support/Legal.php` | Three articles; legal pages that admit their own gaps |

### What is missing — the gap list

Every one of these was verified, not assumed:

1. **No email layer at all.** No `resources/views/mail/`, and `grep -rn "Mailable\|Mail::" app config` returns nothing. A receivables product whose whole pitch is the buyer relationship has no invoice-sent, reminder, or overdue email. **This is the single largest touchpoint gap.**
2. **The claim packet is not printable.** `resources/views/invoices/claim.blade.php:41` ships a `window.print()` button, but `grep -rn "@media print"` across all CSS and Blade returns **zero** matches. Printing the packet — the artefact that goes to an MSEFC forum — currently outputs the workspace chrome, nav and buttons.
3. **The favicon is a 0-byte file.** `public/favicon.ico` is `0` bytes, md5 `d41d8cd98f00b204e9800998ecf8427e` (empty). Every browser tab is blank.
4. **No social/shared-link identity.** No `og:`, no `twitter:`, no `rel="icon"` anywhere in `resources/views`. A link to PayKaro in WhatsApp — the channel this market lives on — renders with no title, image or description.
5. **Five wordings of the value proposition, no lock-up.** `paykaro.tagline` exists
   in config and is read in four places, but the actual headline is hard-coded twice
   and the descriptor is duplicated as a literal. See §1.1.
6. **The mark hard-codes its values.** `logo-mark.blade.php` uses literal hex values instead of the tokens, so a token change silently desynchronises the logo from the rest of the system.
7. **No brand governance document.** No brand book, no usage rules, no clear-space or minimum-size spec, no voice-and-tone guide. The only brand knowledge is the comment block at the top of `app.css`.

---

## Part 1 — Basics (shared vocabulary, locked decisions)

Wheeler opens with the concepts the whole team has to agree on before anyone
designs anything. For PayKaro these are the decisions that are currently implicit.

### 1.1 Language audit — fix the tagline drift

Wheeler's "language audit" / "staying on message". The situation is *almost*
centralised, which is what makes the drift easy to miss.

**There is already a config key.** `config/paykaro.php:16` defines
`'tagline' => env('PAYKARO_TAGLINE', 'MSME invoice & receivables tracker')`, and four
places read it correctly: `resources/views/components/layouts/app.blade.php:101` (footer),
`resources/views/components/layouts/auth.blade.php:34`, `resources/views/components/layouts/public.blade.php:81`, and — oddly —
`resources/views/buyers/form.blade.php:41`, where it is interpolated into the middle of a sentence
about TReDS onboarding.

The drift is in what sits *around* it:

| Where | Line | Problem |
|-------|------|---------|
| `resources/views/marketing/landing.blade.php:8` | `Make every invoice count.` | The real headline — not in config |
| `resources/views/components/layouts/public.blade.php:37` | `PayKaro — Make every invoice count` | Same headline, hard-coded a second time as the `<title>` default |
| `resources/views/marketing/landing.blade.php:259` | `MSME invoice &amp; receivables tracker` | Duplicates the config value as a literal instead of reading `config('paykaro.tagline')` |
| `resources/views/components/layouts/auth.blade.php:39` | `Turn invoice mess into finance-ready receivables.` | A third value proposition, 5 lines below the one it reads from config |
| `resources/views/marketing/landing.blade.php:292` | `© … Turn invoice mess into finance-ready receivables.` | Same line, third copy |
| `resources/views/marketing/article.blade.php:24` | `Turn your receivables into finance-ready assets.` | Fourth wording |
| `README.md` / `SPEC.md` | `Turn invoice mess into an evidence-complete, finance-ready pipeline.` | Fifth wording |

**Build task 1.1** — Two keys, not one, because the config key is doing the wrong
job:

- `paykaro.descriptor` ← what `tagline` holds today ("MSME invoice & receivables
  tracker"). It is a descriptor, not a tagline, and `resources/views/buyers/form.blade.php:41`
  reads worse for the confusion.
- `paykaro.headline` ← "Make every invoice count", the actual tagline, read by
  `resources/views/marketing/landing.blade.php:8`, the `<title>` default and the footer.

Then fix `resources/views/marketing/landing.blade.php:259` to read the config value instead of re-typing it,
and demote the auth/article lines to supporting copy. Add a test asserting the
headline renders identically on `/`, `/login` and `/pricing` — the same way
`PublicPagesTest` already pins `'Make every'`.

### 1.2 Positioning statement

One sentence, written down, that the rest of the plan is checked against. Draft,
derived from `SPEC.md`'s own framing:

> For **Indian MSME suppliers** who get paid late, **PayKaro** is the **receivables
> workflow** that turns an invoice into a finance-ready asset — unlike
> **spreadsheets and WhatsApp threads**, it keeps the dated evidence and statutory
> interest a delayed-payment or TReDS claim actually stands on.

**Build task 1.2** — Ratify or rewrite this. Everything in Phase 3 that cannot be
justified by it is decoration.

### 1.3 Brand architecture

`config/paykaro.php` and `app/Support/Pricing.php` imply three audiences that the
brand currently treats as one: **MSME owners**, **accountants/CA firms**, and
**lenders** (Enterprise tier). Decide now whether Pro/Enterprise are a *tiered
brand* (one mark, priced differently) or *endorsed sub-brands*. Recommended:
tiered, single mark — the product is one workflow with more seats. Record the
decision; it stops a future "PayKaro Pro" logo.

### 1.4 Name and trademark clearance `[needs the book]`

Wheeler's Part 2 covers the trademark process and intellectual property. For
PayKaro specifically, before any launch spend:

- Search **IP India** for "PayKaro" / "Pay Karo" in Class 9 (software), Class 36
  (financial) and Class 42 (SaaS). A payments-adjacent name in India is a crowded
  field; "karo" is a common Hindi imperative suffix.
- Confirm the `.in` domain and handle set.
- Check the ₹ glyph in the mark: the Indian Rupee sign is a government-adopted
  symbol and combining it with a brand mark has usage questions worth an opinion.

**Build task 1.4** — File the search, record the result in this document. If the
name is contested, renaming is cheapest *now*: `brand-wordmark.blade.php` already
splits on camelCase, so a rename is one `APP_NAME` value.

### 1.5 Brand ideals — grade PayKaro against them

Wheeler's ten ideals. Graded against evidence in this repo, not aspiration:

| Ideal | Grade | Evidence |
|-------|-------|----------|
| Vision | **A** | `SPEC.md` — the 2026 MSMED amendment as the wedge. Genuinely specific |
| Meaning | **A−** | "Turn invoice mess into a finance-ready asset" is concrete |
| **Authenticity** | **C** | See §4. The landing page claims numbers it does not have |
| Coherence | **B** | One design system everywhere — undermined by tagline drift (§1.1) |
| Flexibility | **B+** | Fully token-driven, two themes, rename-safe wordmark |
| Commitment | **B** | `MIGRATION.md` documents 16 deliberate decisions and 3 bugs it found |
| Value | **A−** | Free tier → ₹1,499 → custom is legible |
| Differentiation | **B+** | Evidence checklist + statutory interest is a real, ownable angle |
| Longevity | **B** | "Northstar/institutional editorial" is durable, not trend-chasing |

The two to fix are **authenticity** (§4) and **coherence** (§1.1).

---

## Part 2 — Process

### Phase 1 · Conducting research

| Task | Do this | Output |
|------|---------|--------|
| 1.1 Language audit | Reconcile §1.1's six variants | One tagline + descriptor in `config/paykaro.php` |
| 1.2 Marketing audit | Screenshot every route in `routes/web.php` at 375 / 768 / 1440 px | Touchpoint inventory (§Phase 4) |
| 1.3 Competitive audit | Settle on 4 comparators and one page each. Candidates: **Kyte, Vyapar, Zoho Invoice, Refrens** (MSME invoicing), plus **TReDS platforms** (RXIL, Invoicemart, A.TReDS) as the financing adjacency | Positioning table |
| 1.4 Usability | Walk the 4 flows that *are* the brand: sign-up → first invoice, evidence checklist, finance queue, print claim packet | Defect list |
| 1.5 Findings report | Write it down | `docs/brand-findings.md` |

**Already run for you:** 1.1 (§1.1 above) and the touchpoint half of 1.2 (§0).

### Phase 2 · Clarifying strategy

- **2.1 Brand brief** — one page: audience, the problem, the single proof point,
  the personality in three adjectives, what must never change.
  Recommended personality, drawn from the design system's own stated inspiration
  ("university + civic-site editorial"): **plain-spoken, exact, on your side.**
  The voice benchmark already exists in `app/Support/Legal.php`, which lists what
  the product *doesn't* do (no CSP/HSTS, no encryption at rest, no MFA) rather than
  hiding it. That is the voice. Write it down so marketing copy matches legal copy.
- **2.2 Positioning** — ratify §1.2.
- **2.3 Naming** — §1.4 clearance, then stop revisiting it.

### Phase 3 · Designing identity

Ordered by how much is already decided.

| # | Task | Anchor | Effort |
|---|------|--------|--------|
| 3.1 | **Favicon set.** Generate 16/32/48 ICO + `apple-touch-icon` + SVG from the mark; add `rel="icon"` to all three layouts | `public/favicon.ico` is 0 bytes | S |
| 3.2 | **Mark on tokens.** Replace the literal values in `logo-mark.blade.php` with `currentColor` / CSS vars so the mark follows the theme | lines 10-12 | S |
| 3.3 | **Minimum-size + clear space.** The ₹ glyph at 34 px is legible; decide the floor (recommend 20 px) and the clear space (recommend ½ tile), and write it into the guidelines (§Phase 5) | — | S |
| 3.4 | **Contrast.** The token set is already solid (`:root`, both themes). Record the WCAG contrast pairs as comments next to the tokens — the muted-text token on the page background is the risky one | `public/assets/app.css:17-70` | M |
| 3.5 | **Typography scale.** Fraunces is loaded with `opsz,wght` 9..144; document which optical sizes are used for display vs. body so the two don't drift | layouts | M |
| 3.6 | **Look and feel.** One page: surface texture, `--n-radius:14px`, shadow, the vertical accent bars. So the *next* screen looks like this one | `app.css` | M |

### Phase 4 · Creating touchpoints

Ranked by how much brand is at stake per unit of work. **The top two are the
reason this plan exists.**

#### 4.1 Email — build from zero (highest priority)

No Mailable class and no mail view exist. Ship in this order, each on the existing
`layouts/` vocabulary:

1. `InvoiceSentMail` — the invoice itself, to the buyer. Carries the due date
   computed by `Receivables::dueDate()`, never a hand-typed one.
2. `OverdueReminderMail` — day 1/15/30 past due, quoting the accruing interest
   from `Receivables::interest()`.
3. `EvidenceRequestMail` — "we're missing the GRN for INV-2026-007."
4. Plain-text fallback for all three; MSME buyers are on cheap Android clients.

Acceptance: a `tests/Feature/` test using `Mail::fake()` asserting the reminder
quotes the same interest figure the invoice page shows. That is the coherence
check — brand promise and product number cannot diverge.

#### 4.2 The printed claim packet

`resources/views/invoices/claim.blade.php:41` already calls `window.print()`. Add a `@media print` block to
`public/assets/app.css`:

- hide `.pkg-util`, nav, `.pkg-btn`, the theme toggle;
- force light tokens (`html.dark` must not print as a dark page);
- keep the evidence table, the interest table and the "File before" line together
  (`break-inside:avoid`) — a statutory filing with a page break through the
  interest schedule is a broken artefact;
- page margin + a footer carrying the business's GSTIN and the invoice number,
  because the packet is cited by those strings.

Acceptance: `bridge/overflow-sim.mjs`-style structural check, plus a manual print
preview. This is the one touchpoint where a visual bug has legal consequence.

#### 4.3 Social / shared link

Add to `layouts/public.blade.php` and `layouts/app.blade.php`:
`og:title`, `og:description`, `og:image` (1200×630 from the mark + tagline),
`twitter:card`, and a real `rel="icon"`. Add a `/assets/img/og-default.png`.
PayKaro's distribution channel is a WhatsApp link; today that link has no preview.

#### 4.4 Remaining touchpoints

| Touchpoint | State | Task |
|-----------|-------|------|
| Website (marketing) | Live, `resources/views/marketing/*` | Copy pass against §1.2 |
| Website (workspace) | Live, `components/layouts/app.blade.php` | Empty states and error pages in brand voice (`errors/403`, `errors/404`) |
| Stationery / letterhead | **Missing** | Letterhead + invoice header component — an invoice is the product's own collateral |
| PDF export | **Missing** | Reuse 4.2's print CSS for a server-side PDF later |
| Signage / vehicles / uniforms | N/A | Not applicable to a SaaS v1 |
| Packaging | N/A | Not applicable |

### Phase 5 · Managing assets

- **5.1 Brand book.** One `docs/brand/` page (or a route at `/brand`) covering:
  the mark and its clear space, the token table, type scale, voice, and the
  "never" list. Small enough that people actually read it.
- **5.2 Guidelines as code.** The strongest version of this: `config/paykaro.php`
  already holds every number the domain computes with, and
  `App\Services\Receivables` is the only reader. Do the same for brand — one place
  owns `tagline`, `descriptor`, and the token names, and a test fails if a view
  hard-codes a hex. That test is what prevents the `logo-mark.blade.php` drift
  from recurring.
- **5.3 Change management.** `public/assets/app.css` is a single 63 KB file
  (64,498 bytes) that `MIGRATION.md` says was ported "markup-for-markup" and is
  deliberately kept verbatim. Any brand change is a diff to that one file — so
  require a screenshot of the affected layouts in the PR.
- **5.4 Brand champions.** For an internal tool of this size, the "champions" are
  the two seeded demo tenants' personas (Sunita Rao / Shree Precision, Farhan Ali /
  MetRow Ceramics in `database/seeders/DemoWorkspaceSeeder.php`). Keep the demo
  book realistic; it is the product's own showroom.
- **5.5 Measuring success.**

| Metric | Where it already lives |
|--------|------------------------|
| Sign-up completion | `RegisteredUserController` |
| First invoice raised | `InvoiceWorkflow` |
| Evidence checklist completed | `InvoiceEvidence.present` |
| Finance-queue action taken | `Workspace\FinanceQueueController` |
| Claim packet printed | instrument the `window.print()` click at `resources/views/invoices/claim.blade.php:41` |
| Reminder email → payment | lands with §4.1 |

---

## 4. The authenticity gate (do this before any launch)

Wheeler grades brands on authenticity, and this repo has a live problem. The
landing page publishes numbers it cannot support:

- `resources/views/marketing/landing.blade.php:46` and `:181` both hard-code
  **₹4.2Cr+ "Receivables tracked"**. Line 181 qualifies it as *"Across active
  tenants in the last 30 days."* The value is a literal string in the Blade file —
  `HomeController` passes no such figure — and the only data the app ships is the
  seeded demo book (2 tenants, 18 invoices, per `README.md`).
- `app/Support/News.php` returns three **authored** articles with dates
  ("02 Sep 2026") and press-release framing ("The new evidence checklist…"),
  presented on the landing page as news.

Note the contrast: `app/Support/Legal.php` is scrupulous about admitting what the
product does *not* do. The marketing pages should meet the standard the legal pages
already set.

**Build task 4.1** — Pick one, do not skip:
- **(a)** Compute the figure for real from the tenants and label it honestly
  (e.g. "Tracked in this workspace"), or
- **(b)** Replace the stat bar with claims the product can prove — the 45-day
  window and 3× multiplier are already true and are the stronger story anyway.

**Build task 4.2** — Label the authored articles as "From the PayKaro team" /
"Product notes" rather than a news feed, or mark them clearly as illustrative while
the product is pre-launch.

---

## 5. Sequencing

| Sprint | Contents | Why this order |
|--------|----------|----------------|
| 1 | §1.1 tagline lock-up · §4 authenticity gate · 3.1 favicon · 3.2 mark on tokens | All are small, all are correctness bugs in the current brand, none need a designer |
| 2 | 4.2 print CSS for the claim packet · 4.3 social meta | Highest-stakes touchpoints, both are missing infrastructure rather than new design |
| 3 | 4.1 email (3 templates) | The biggest build; needs the voice from §2.1 written first |
| 4 | §1.2-1.4 positioning, architecture, trademark · 3.4-3.6 contrast/type/feel | Strategy and system polish |
| 5 | 5.1–5.5 brand book, guidelines-as-code, metrics | Governance, once there is something to govern |

### How each task gets verified

The repo already pins brand copy in tests — `tests/Feature/PublicPagesTest.php`
asserts `'Make every'`, `'Raised → accepted → financed → settled'`, `'₹1,499'`,
`'Most popular'`. Every task above should land the same way: a Feature test that
fails when the brand promise and the rendered page disagree. `php artisan test` is
the gate; note that this sandbox has **no native PHP and no `vendor/`**, so the
suite must be run locally or in `.github/workflows/ci.yml`.

---

*Plan built from the repository at commit `8f60b12249f82f5d841d75aa27f1eca9d1ab4b45`.
Framework structure from the published table of contents of Alina Wheeler,
*Designing Brand Identity* — not from the book's text.*
