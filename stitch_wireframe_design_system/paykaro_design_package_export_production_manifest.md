# PayKaro Sovereign MSME — Complete Design Package & Production Export

**Project:** PayKaro — MSME Receivables Tracker & TReDS Institutional Liquidity  
**Design System:** Obsidian Flow (`{{DATA:DESIGN_SYSTEM:DESIGN_SYSTEM_2}}`)  
**Brand Identity:** Pure Typographic Wordmark (`PayKaro` — Deep Obsidian `#0b132b` + Fintech Electric Indigo `#1d4ed8`)  
**Document Type:** Production Export Dossier, Design Package Manifest & Handoff Specification  
**Version:** 3.0.0 (Production Master)  

---

## 1. Executive Design Summary & Product Architecture

PayKaro is a dual-engine financial and legal operations platform tailored for Indian MSMEs under the statutory protections of the **Micro, Small and Medium Enterprises Development (MSMED) Act, 2006** (Sections 15–24) paired directly with RBI-governed **TReDS factoring exchanges** (RXIL, M1xchange, Invoicemart).

### Core Pillars:
1. **Statutory Receivables Enforcement:** Tracking Section 15 45-day statutory payment windows and automated Section 16 compounding interest calculations (strictly calculated as **3 × RBI Repo Rate, compounded monthly**).
2. **Institutional Liquidity Floor:** Seamless discounting pipelines into TReDS multi-exchange auctions for eligible accepted supplier invoices.
3. **Formal Dispute & MSEFC Dossier Automation:** Generating court-ready Section 18 legal claim packets with filing countdowns and evidence checklists for the Ministry of MSME's Samadhaan council.
4. **Ergonomic Dual-Viewport Coverage:** Responsive desktop suites paired with mobile touch-optimized (~390px) workflows with gesture-based bottom sheets and haptic simulation.

---

## 2. Design System Architecture: Obsidian Flow

### Semantic Color Matrix
- **Obsidian Dark Anchors:**
  - `primary-950` / Surface Scrim: `#070d1e`
  - `primary-900` / Text Primary: `#0b132b`
  - `primary-800` / Card Headers & Text: `#0f172a`
  - `slate-700` / Secondary Body: `#334155`
  - `slate-500` / Muted Subtitles: `#64748b`
- **Fintech & Action Accents:**
  - `brand-blue` (Primary Interactive): `#1d4ed8` (Hover: `#1e40af`)
  - `brand-sky` (TReDS Liquidity Accent): `#0284c7`
  - `emerald-600` (Settled & Approved): `#16a34a` (Surface: `#f0fdf4`, Border: `#bbf7d0`)
  - `violet-600` (Financed via TReDS): `#7c3aed` (Surface: `#f5f3ff`, Border: `#ddd6fe`)
  - `amber-600` (Pending Warning Day 40-45): `#d97706` (Surface: `#fffbeb`, Border: `#fde68a`)
  - `red-600` (MSEFC Disputed / Overdue): `#dc2626` (Surface: `#fef2f2`, Border: `#fecaca`)
- **Neutral Canvas & Surfaces:**
  - `surface-lowest` (Card / Sheet Surfaces): `#ffffff`
  - `surface-low` (Global Background): `#f8faff`
  - `surface-container` (Input & Table Accent): `#eff4ff`
  - `surface-dim` (Dividers & Drag Handles): `#e2e8f0`

### Typography Hierarchy (Plus Jakarta Sans)
- **Display Hero:** 36px (2.25rem) / 800 Bold / Line Height 1.15 / Tracking -0.035em
- **H1 Header:** 28px (1.75rem) / 800 Bold / Line Height 1.2 / Tracking -0.025em
- **H2 Subheader:** 20px (1.25rem) / 700 Bold / Line Height 1.3 / Tracking -0.02em
- **H3 Card Titles:** 15px (0.9375rem) / 700 Bold / Line Height 1.4 / Tracking -0.01em
- **Body Regular:** 13px (0.8125rem) / 500 Medium / Line Height 1.5
- **Caption / Status Pills:** 11px (0.6875rem) / 700 Bold / Tracking +0.05em
- **Monospace Figures:** `ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace` strictly formatted with Indian currency groupings (`₹1,23,456.00`).

---

## 3. Screen Registry & Manifest (Canvas Inventory)

### A. Desktop Production Suite (12 Core Screens)
1. **Screen 1 · Overview Dashboard (`/dashboard`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_78}}` / `{{DATA:SCREEN:SCREEN_63}}`
   - *Blocks:* 4-KPI row (Outstanding, Overdue, Statutory Interest, 30d Receivable), Needs Attention alert desk, 5-bucket Ageing summary bar chart, Pipeline overview, Recent Invoices ledger.
2. **Screen 2 · Invoices Desk (`/invoices`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_77}}` / `{{DATA:SCREEN:SCREEN_61}}`
   - *Features:* Verbatim status tabs (`All · Raised · Accepted · Financed · Settled · Disputed` - no Draft tab), search box & buyer dropdown, 7-column data table, pagination.
3. **Screen 3 · Invoice Detail Hub (`/invoices/{id}`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_91}}` / `{{DATA:SCREEN:SCREEN_50}}`
   - *Features:* Stage progression tracker, 5-item statutory evidence checklist (PO, Delivery Ack, GRN, GST Invoice copy, Contract), Readiness gauge (85/100), Record Payment trigger, Finance this trigger, Start MSEFC Claim trigger.
4. **Screen 4 · New Invoice Creation (`/invoices/create`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_89}}`
   - *Features:* Read-only auto-computed Due Date (Date + 45d strictly non-editable), read-only computed Total, GST auto-computation (18% default), Add Buyer modal escape hatch.
5. **Screen 5 · Customers Directory (`/buyers`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_88}}` / `{{DATA:SCREEN:SCREEN_51}}`
   - *Features:* 5 columns (`Buyer | GSTIN | Type | TReDS | Outstanding`), count badge, strictly create-only (no edit/delete).
6. **Screen 6 · New Buyer Registration (`/buyers/create`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_86}}`
   - *Fields:* Buyer name, GSTIN, Type (CPSE / PSU / Private), TReDS onboarded status.
7. **Screen 7 · Finance & TReDS Desk (`/treds`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_84}}` / `{{DATA:SCREEN:SCREEN_49}}`
   - *Features:* Finance-ready KPI (`N of M`), 85/100 threshold alert, Ready Queue list, Blocked Queue list with specific unblocking reasons.
8. **Screen 8 · Reports & Statutory Ageing (`/reports`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_79}}` / `{{DATA:SCREEN:SCREEN_48}}`
   - *Features:* Outstanding by buyer, Summary metrics, statutory ageing distribution.
9. **Screen 9 · Settings & Entity Registry (`/settings`)**
   - *Inventory:* `{{DATA:SCREEN:SCREEN_81}}` / `{{DATA:SCREEN:SCREEN_47}}`
   - *Features:* Enterprise KYC fields (GSTIN, PAN, Udyam registration no, Bank Account, IFSC, TReDS membership ID).
10. **Screen 10 · Printable Claim Packet Dossier (`/invoices/{id}/claim`)**
    - *Inventory:* `{{DATA:SCREEN:SCREEN_82}}` / `{{DATA:SCREEN:SCREEN_31}}`
    - *Features:* High-density legal artifact for MSEFC / Samadhaan council filing, evidence gap table, compounding statutory interest schedule, A4 print-optimized layout.
11. **Screen 11 · Auth Shell (Login & Signup)**
    - *Inventory:* `{{DATA:SCREEN:SCREEN_73}}` (Login), `{{DATA:SCREEN:SCREEN_71}}` (Create Account)
    - *Features:* Split-screen auth layout with headline: *"Turn invoice mess into finance-ready receivables."*
12. **Screen 12 · Public Landing Page (`/`)**
    - *Inventory:* `{{DATA:SCREEN:SCREEN_69}}`
    - *Features:* 10 sequential sections (Hero, Stat Bar with placeholder tokens, 4-step Workflow, Finance, Evidence, News, Impact, Claims, Testimonials, Closing CTA) with verified anchor links.

### B. Mobile Touch-Optimized Suite (~390px Viewports)
1. **Core Workflows:**
   - Overview Dashboard: `{{DATA:SCREEN:SCREEN_45}}`
   - Invoices List: `{{DATA:SCREEN:SCREEN_44}}`
   - Customers & New Buyer: `{{DATA:SCREEN:SCREEN_32}}`
   - Finance / TReDS Queue: `{{DATA:SCREEN:SCREEN_25}}`
   - New Invoice Creation: `{{DATA:SCREEN:SCREEN_24}}`
   - Claim Packet Mobile Viewer: `{{DATA:SCREEN:SCREEN_3}}`
2. **Interactive Modal Sheets & Gesture Drawers:**
   - Invoices Filter & Search Drawer: `{{DATA:SCREEN:SCREEN_7}}`
   - Invoice Detail with Gesture Handlers & Haptics: `{{DATA:SCREEN:SCREEN_10}}`
   - Record Payment Modal Sheet: `{{DATA:SCREEN:SCREEN_22}}`
   - TReDS Financing Modal Sheet: `{{DATA:SCREEN:SCREEN_21}}`
   - MSEFC Claim & Dispute Modal Sheet: `{{DATA:SCREEN:SCREEN_19}}`
3. **Post-Action Confirmation Sheets:**
   - Payment Settled Sheet: `{{DATA:SCREEN:SCREEN_15}}`
   - TReDS Disbursed Sheet: `{{DATA:SCREEN:SCREEN_14}}`
   - MSEFC Claim Submitted Sheet: `{{DATA:SCREEN:SCREEN_13}}`
   - Interactive Toast Notification System: `{{DATA:SCREEN:SCREEN_5}}`

### C. State Variants & Edge Conditions
- **Empty States:** Customers Empty (`{{DATA:SCREEN:SCREEN_36}}`), Invoices Edge States (`{{DATA:SCREEN:SCREEN_41}}`), TReDS Empty (`{{DATA:SCREEN:SCREEN_38}}`).
- **Interactive Calendar Overlay:** New Invoice Calendar Date Picker (`{{DATA:SCREEN:SCREEN_39}}`).
- **Role Permissions (403 State):** Accountant / Viewer Read-Only Forbidden State (`{{DATA:SCREEN:SCREEN_35}}`).
- **Design Tokens Spec & Component Guide:** Spec Guide (`{{DATA:SCREEN:SCREEN_26}}`) & Token Spec (`{{DATA:DOCUMENT:DOCUMENT_9}}`).

---

## 4. Verification & Brand Consistency Sign-Off

- **Typographic Wordmark Enforcement:** All screens have undergone complete multi-round audits to ensure 100% removal of graphic logo marks, squircle icons, and image emblems in favor of the pure, authoritative typographic wordmark **Pay<span style="color:#1d4ed8">Karo</span>**.
- **Negative Constraints Honored:** Strictly no email views, no buyer edit/delete, no invoice delete, no export CSV/PDF buttons, and no fake stats on the public landing page.
- **Statutory Accuracy:** Indian numbering system (`₹XX,XX,XXX.00`), Section 15 45-day calculation, Section 16 3× RBI repo monthly compounding interest formulas, and Samadhaan MSEFC council filing formats.

---
*Signed and Approved for Production Handoff · PayKaro Engineering & Design Teams*
