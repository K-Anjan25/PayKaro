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

## 3. Blocker one — the interest formula disagrees with the code

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

## 7. Suggested sequence

1. **Merge `main` into this branch** — the package is not present otherwise.
2. **Settle the interest formula** (§3). One function, one test file.
3. **Decide the build-step question** (§4). Nothing else can start.
4. **Token + type + logo swap** — smallest visible win, touches 3 layouts.
5. **Screen-by-screen port** in the order users hit them: Overview → Invoices →
   Invoice detail → Customers → Finance → Reports → Settings → Auth → Landing.
6. **Print stylesheet for the claim packet** — the package includes an A4
   dossier; the app currently has no `@media print` at all.
7. **Decide on the orphan screens** (§6) separately — they are features.

`php artisan test` is the gate at every step. Note this sandbox has no native PHP
and no `vendor/`, so the suite must run locally or in `.github/workflows/ci.yml`.
