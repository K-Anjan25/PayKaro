# Wireframe Package Audit — `stitch_wireframe_design_system/`

What the design package contains, what it agrees with, and what has to be
decided before any of it is built. Every number below was read out of the
package or the code, not estimated.

**Important:** the folder is **not on this branch**. It landed on `main` in commit
`b06c178` ("added"), and `arena/01a09e7e-paykaro` was cut from `8f60b12` — one
commit earlier. Everything here was read via `git show main:…`. Merging `main`
into the branch is step zero of any work.

---

## 1. What's in the package

| | |
|---|---|
| Total files | **131** |
| `code.html` wireframes | **63** (31 desktop, 32 mobile) |
| `screen.png` previews | **64** |
| Spec documents | **4** |
| HTML payload | **2,251,763 bytes (~2.2 MB)** |

**The four documents** — read these before the HTML:

| File | What it is |
|---|---|
| `wireframes.md` | A copy of this repo's `WIREFRAMES.md` — the brief the screens were built from |
| `obsidian_flow/DESIGN.md` | The design system: full token set, typography scale, elevation, shapes, components |
| `obsidian_flow_design_tokens_handoff_spec.md` | v2.4.0 — semantic tokens, status colours with statutory references, gesture spec |
| `paykaro_design_package_export_production_manifest.md` | v3.0.0 — screen registry mapping every canvas to a route, plus sign-off |

**Coverage is genuinely good.** Beyond the 12 core desktop screens it includes
modal/bottom-sheet variants, three empty states, the calendar overlay, the
`403` read-only state for a non-owner on Settings, and a printable claim-packet
dossier — the screen this app most needs and currently styles worst.

---

## 2. Where it correctly followed the brief

Worth recording, because it means the package can be trusted on structure:

- **Landing page: 10 sections** — matches the corrected count, not the original 9.
- **Status tabs verbatim** `All · Raised · Accepted · Financed · Settled · Disputed`,
  with an explicit note that *"'Draft' is strictly forbidden as a status tab."*
- **Due date and Total marked read-only** and "strictly non-editable".
- **Negative constraints honoured** — the manifest's own sign-off says: *"no email
  views, no buyer edit/delete, no invoice delete, no export CSV/PDF buttons, and
  no fake stats on the public landing page."*
- **Customers is create-only**, 5 columns, matching the real table.

---

## 3. ~~Blocker one~~ — the interest formula: RESOLVED, and the code was the wrong one

This is the most important finding, because it is a number that goes on a
statutory filing.

**What the code does** (`app/Services/Receivables.php`, `interest()`):

```php
$rate  = $this->bankRate * $this->interestMultiplier;   // 6.5 × 3
$daily = $rate / 100 / 365;
return round($totalAmount * $daily * $overdueDays, 2);
```

Simple interest, accrued **daily**, on the **bank rate** (6.5, from
`config/paykaro.php:70`).

**What the design package says** — in three separate places:

> `obsidian_flow_design_tokens_handoff_spec.md:93`
> *"Calculated strictly as **3 × RBI Repo Rate (compounded monthly)** under
> Section 16 of MSMED Act."*

> `paykaro_design_package_export_production_manifest.md:16` and `:128`
> *"3 × RBI Repo Rate, compounded monthly"*

Three differences, not one:

| | Code | Design package |
|---|---|---|
| Reference rate | **bank** rate | **repo** rate |
| Accrual | simple | **compound** |
| Period | daily | **monthly** |

The repo's own `SPEC.md:87` and `README.md:76` both say **bank_rate × 3**, so the
design documents contradict the shipped code *and* the shipped specification.

**Why this blocks UI work:** every screen that shows an interest figure — the
dashboard KPI, the invoice detail, the ageing report, the claim packet — renders
whatever `Receivables::interest()` returns. Building the UI against one formula
while the code computes another produces a claim packet whose interest schedule
does not match the statute the packet cites.

**Not decided here.** Which is correct is a legal question and the repo's own
invariant is that exactly one place (`Receivables`, reading `config/paykaro.php`)
may compute it. Whichever way it goes, it is a one-function change plus a test
update in `tests/Unit/ReceivablesTest.php` — but it must be settled first.

---


### Resolution

**Section 16 of the MSMED Act 2006 settles it, and it settles it against the code:**

> *"...the buyer shall, notwithstanding anything contained in any agreement ... be liable
> to pay **compound interest with monthly rests** to the supplier on that amount ...
> at three times of the bank rate notified by the Reserve Bank."*

So the design package was right about the method and wrong about the instrument: the Act
says **the bank rate**, and "three times the **repo** rate" is a different (and larger)
number. The code was wrong about both, and the third difference — daily versus monthly
accrual — is real arithmetic: a rest charges a whole month (1/12th of the annual rate),
not 30/365ths of it.

The errors pulled in opposite directions, which is why the old figures looked plausible:

| | Rate | Method | INV-2026-007 (₹9,67,600, 65 days) |
|---|---|---|---|
| Shipped code | 6.5% (2023–24 **repo** rate — stale, and the wrong instrument) | simple, daily | ₹33,600.90 |
| Correct | **5.5%** (the **bank** rate since Dec 2025) | compound, monthly rests | **₹29,070.75** |

The old figure **overstated the claim by ₹4,530.15** against the buyer. Two bugs that
partly cancelled are the worst kind: the number looked right, so nothing pointed at it.

**What shipped:**

- `App\Services\InterestSchedule` — the month-wise working. A rest every 30 days from
  the due date, each charging the periodic rate on the balance and compounding it;
  days after the last rest accrue pro rata and do not themselves compound. The Act does
  not define the partial month, so the convention is stated on the packet rather than
  left implicit.
- `Receivables::interest()` returns the schedule's total; `Invoice::interestSchedule()`
  exposes the working.
- **The claim packet prints the schedule** — the month-wise chart courts ask for is now
  the document, and the total is its sum, so the two cannot disagree.
- `bank_rate` default corrected **6.5 → 5.5**, and `bank_rate_history` added for the
  rate "notified from time to time": an invoice that sat through a rate change is charged
  each rate for its own month. The history ships **empty** — those dates are the
  operator's records, and inventing them would be the unearned precision this product
  refuses.
- `ReceivablesTest` rewritten around the statute: a rest equals 1/12th of the annual
  rate, the second rest charges interest on the first rest's interest, the schedule sums
  to its own total, and each rest carries its own month's rate.

**A display bug fell out of it.** The schedule's pro-rata line produced
`₹4,112.02`, which the packet printed as **`₹4,112.2`** — `Money::format()` rendered
paise with `number_format($paise, 0)`, so anything under ten paise lost its leading
zero. One digit, wrong by a factor of ten, on a line that then did not reconcile with
the total below it. Fixed, and `MoneyTest` holds it.

## 4. Blocker two — the wireframes are Tailwind CDN; the app has no build step

All **63 of 63** `code.html` files load:

```html
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
```

with an inline `tailwind.config = { … }` block. `cdn.tailwindcss.com` is Tailwind's
Play CDN — a browser-side build tool intended for prototyping, not production.

The app deliberately has **no build step**. `SPEC.md` states: *"No Vite/Tailwind
build step: the stylesheet is committed as a static asset."* Styling is
`public/assets/app.css` — 993 lines, 64,498 bytes, 32 CSS custom properties, 165
distinct component classes.

So the wireframes **cannot be pasted into Blade as-is**. Shipping them would put
a CDN build script in front of every page.

**The realistic options:**

| Option | What it means | Cost |
|---|---|---|
| **A. Adopt a real Tailwind build** | Vite + Tailwind, generate a static CSS file at build time, keep the "committed asset" property by checking in the built file | New build step; CI changes; the ported-verbatim guarantee in `MIGRATION.md` ends |
| **B. Port the design to static CSS** | Translate Obsidian Flow's tokens into the existing custom-property system, rewrite the 165 component classes, keep zero build step | Largest hand effort; no new tooling |
| **C. Hybrid** | Take the tokens, type scale and component specs from `DESIGN.md`; rebuild the markup in existing Blade components | Medium; preserves the architecture, still a real restyle |

This is the single biggest decision in the upgrade and it is not a styling
question — it is whether the repo grows a build step.

### Decision — option C, taken the slow way: no build step

**The app ships no Tailwind and no build step, and that is now asserted rather
than assumed.** The hybrid won, and by the time the question was asked most of it
had already been done one component at a time:

- the **tokens** are in `app.css` as the `--n-*` custom properties, declared in
  `App\Brand\Palette` with their measured contrast floors (`e77437c`);
- the **type scale** is the Obsidian Flow scale ported into `Type::scale()`;
- the **mark** is the typographic wordmark the manifest demands — no squircle, no
  graphic emblem (§3.2);
- the **screens** are the existing Blade components restyled, which is what the
  design package's own exports end up looking like once the utility classes are
  resolved;
- `/brand` documents all of it, read live from the product.

No Play CDN, no Vite, no PostCSS config, no `package.json`. `SPEC.md` promises
"no build step: the stylesheet is committed as a static asset", and the promise is
kept — so option A stays available but unneeded, and `MIGRATION.md`'s
ported-verbatim guarantee is untouched.

`FrontEndAssetsTest` holds the line, because a CDN script is the single most
copy-pasteable thing in the design package:

- no view may contain `cdn.tailwindcss.com`, `unpkg.com`, `jsdelivr.net` or the
  string `tailwind` at all;
- the only remote host a page may fetch from is the font
  (`fonts.googleapis.com` / `fonts.gstatic.com`) — with `w3.org` and `schema.org`
  excepted as text, not requests;
- all three layouts load the same stylesheet and the same font, so a layout cannot
  quietly grow its own identity;
- every asset the head names is present and non-empty, `favicon.ico` above 1 KB.

**Deviation, recorded:** the design package asks for **Material Symbols Outlined**
for icons; the app keeps **inline SVG**. That is deliberate — a webfont for icons
is a second remote request, a flash of unstyled glyphs, and a styling dependency
loaded at runtime, which is the same objection that ruled out the Play CDN. The
inline set is already used in every screen.

---

## 5. What changes regardless of option

| | Current | Obsidian Flow |
|---|---|---|
| Design system | "Northstar / institutional editorial" | "Obsidian Flow" |
| Typography | DM Sans + Fraunces | Plus Jakarta Sans + JetBrains Mono |
| Icons | inline SVG | Material Symbols Outlined (a webfont) |
| Logo | SVG tile + ₹ glyph, `components/logo-mark.blade.php`, used in **4** views | **Pure typographic wordmark** — the manifest requires *"100% removal of graphic logo marks, squircle icons, and image emblems"* |
| Palette | the `--n-*` token set in `public/assets/app.css` | the Obsidian Flow token set in `DESIGN.md` |

The logo change is not cosmetic: `logo-mark.blade.php` is rendered by all three
layouts plus the landing footer, so it is deleted or reduced to nothing in four
places, and the wordmark component becomes the whole identity.

---

## 6. Screens with nothing behind them

These are designed but have **no route, controller method or view**. Building
them is backend work, not frontend work.

| Screen | Reality |
|---|---|
| `buyer_detail_bharat_heavy_electricals_ltd_bhel` + its mobile twin (**2**) | ~~**No buyer detail route exists.**~~ **Built.** `buyers.show` + `BuyerController@show` + `buyers/show.blade.php`: the invoices behind the outstanding figure, the interest accrued on them, an average readiness, and what the TReDS status means for the money — including the CPSE mandate, which is the one follow-up worth making. `BuyerDetailTest`. The two screens collapsed into one responsive page, as the other mobile twins did. |
| `paykaro_desktop_walkthrough_feature_tour` (**1**) | No tour or onboarding anywhere in `routes/`, `resources/views/` or `app/`. |
| `mobile_interactive_toast_notification_system` (**1**) | The app uses server-rendered flash messages (`components/flash.blade.php`). No toast system. |
| **9** `*_pull_to_refresh_gesture` screens | Server-rendered Blade with plain form POSTs. There is no client data layer to refresh. |

That is **13 of the 63** screens (9 + 2 + 1 + 1). The mobile bottom sheets with
`navigator.vibrate()` haptics are *not* counted here — record payment, finance and
claim are all real actions, and a sheet is a legitimate way to present them.

**Two of the thirteen are now built** (the buyer detail pair, above). The other
eleven are the remainder of this section, and they divide cleanly:

- **Built, in the form that fits:** the feature tour. `WIREFRAMES.md`'s "sign-up
  lands straight on the dashboard" is now answered by a first-run checklist on the
  dashboard — four steps read from the workspace's own state (add a buyer, raise an
  invoice, complete an evidence trail, confirm each buyer's TReDS status), with the
  card leaving once there is nothing left to say and staying away from read-only
  members, who cannot do any of it. `App\Brand\Onboarding`, `OnboardingTest`. A
  slide-deck tour would have been the wrong shape for a server-rendered app; this
  states the next step and is checkable.
- **Not a fit for this architecture, recommended declined:** the nine
  `*_pull_to_refresh_gesture` screens and the toast system. The app is
  server-rendered Blade over plain form POSTs — every action is a navigation, so
  there is nothing to refresh in place, and feedback already arrives as the
  server-rendered flash in `components/flash.blade.php`. Building either would mean
  a client data layer that the rest of the product does not have. They should be
  recorded as declined rather than left on a list as though they were pending.

---

## 7. The sequence — as written, and as it went

The order below was written before any of it was done. Kept as it was proposed,
with what happened beside it, because the two disagreements are the interesting
part: the plan assumed the blockers came first, and they did not.

| | Step | Outcome |
|---|---|---|
| 1 | Merge `main` into the branch | Done (`5948301`). Without it the design package is not present at all — and the merge was what put the CI failures on this branch rather than in a review of someone else's PR |
| 2 | Settle the interest formula | **Deferred to the end, and that was right.** It was the item with a legal answer (`web_search` → the Act) rather than a preference, and it turned out the *code* was wrong, not the mocks. Doing it first would have meant re-deciding it once the claim packet existed to disagree with (§3, resolved) |
| 3 | Decide the build step | **Deferred, and it answered itself.** By the time the tokens, type scale and screens were ported one at a time, the hybrid had happened and there was no question left (§4, decided) |
| 4 | Token + type + mark swap | Done (`e77437c`, `3710d87`). Six contrast failures found and fixed; the mark is the wordmark, as the manifest demands |
| 5 | Screen-by-screen port | Done in the order users hit them, with the **claim packet treated as a screen** — it is the artefact a customer files, and the design package treats it as one (A4 dossier) |
| 6 | Print stylesheet for the packet | Done (`f8faaac`). The app had no `@media print` block at all; the packet now prints as a filing, with the client's letterhead (`d541683`) |
| 7 | Decide on the orphan screens | Done (§6). The buyer detail pair built; the rest recorded as worth-building-or-declined rather than left pending |

**The gate held.** `php artisan test` — run in the sandbox as `node bridge/test.mjs`,
and on CI with real PHP — was the gate at every step, which is exactly what the
previous branch had not done. Where the sequence was wrong it was wrong in the same
direction both times: it put the *decisions* first, and the decisions were better
made with the work in front of them. The blockers were real; they were just not
blocking.

**What is left, and who owns it:**

| Item | Owner |
|---|---|
| §5.5 measurement — the print-packet click and the reminder→payment funnel | Awaiting a sink decision: the privacy page is explicit that the code embeds no third-party data flow |
| §2.2 positioning sentence ratified, §2.3 naming/trademark check | The user; §2.3 is a light web search plus a look at the mark |
| `bank_rate_history` entries | The operator: RBI notifications are records to keep, not defaults to guess |
| Closing PR #12 unmerged | Done |
