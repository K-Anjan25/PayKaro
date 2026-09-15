# PayKaro Design System — Obsidian Flow Token Specification & Handoff

**Version:** 2.4.0  
**Design System Identifier:** `{{DATA:DESIGN_SYSTEM:DESIGN_SYSTEM_2}}`  
**Brand:** PayKaro MSME Receivables & Sovereign Liquidity  
**Typography:** Plus Jakarta Sans & Inter monospace fallback  
**Status:** Production Ready · Handoff Approved  

---

## 1. Brand Identity & Visual Geometry

PayKaro bridges enterprise MSME statutory compliance under MSMED Act 2006 (Section 15–24) with institutional fintech liquidity (TReDS - RXIL/M1xchange/Invoicemart).

- **Brand Wordmark:** `PayKaro` (Deep Obsidian Navy `#0b132b` + Fintech Electric Indigo `#1d4ed8`)
- **Subtitle / Tagline:** `RECEIVABLES & LIQUIDITY` (Slate-500 `#64748b`, 9.5px, uppercase, 0.22em letter-spacing)
- **Container Geometry:** Squircle with radius `20px` on 80×80 base, elevation shadow `0 5px 14px rgba(7, 13, 30, 0.22)`
- **Symbolism:** Left vertical ledger spine (verified invoice) transitioning into an upward liquidity flow loop forming the letter 'P' with integrated settlement accelerator node.

---

## 2. Color Palette & Semantic Tokens

### Primary & Brand Accents
| Token | Hex | RGB | Tailwind Class | Usage |
|---|---|---|---|---|
| `primary-900` | `#0b132b` | `rgb(11, 19, 43)` | `bg-slate-950` / `text-[#0b132b]` | Wordmark primary, dark accents, modal scrim |
| `primary-800` | `#0f172a` | `rgb(15, 23, 42)` | `bg-slate-900` / `text-slate-900` | Headings, primary text, dark cards |
| `primary-700` | `#1e293b` | `rgb(30, 41, 59)` | `bg-slate-800` | Secondary dark containers, header borders |
| `brand-blue` | `#1d4ed8` | `rgb(29, 78, 216)` | `bg-blue-700` / `text-blue-700` | Primary interactive buttons, active tabs, brand anchor |
| `brand-sky` | `#0284c7` | `rgb(2, 132, 199)` | `bg-sky-600` / `text-sky-600` | TReDS factoring accent, settlement status |
| `cyan-bright` | `#00f0ff` | `rgb(0, 240, 255)` | `text-[#00f0ff]` | Highlight data pips, flow vector accent |

### Surface & Background Tokens
| Token | Hex | Tailwind Class | Usage |
|---|---|---|---|
| `surface-lowest` | `#ffffff` | `bg-white` | Card surfaces, sheet bodies, modals |
| `surface-low` | `#f8faff` | `bg-[#f8faff]` | App global background, neutral table strips |
| `surface-container` | `#eff4ff` | `bg-blue-50` / `bg-[#eff4ff]` | Input backgrounds, badge tints, pill toggles |
| `surface-dim` | `#e2e8f0` | `bg-slate-200` | Dividers, drag handles, modal borders |
| `surface-highlight` | `#dbeafe` | `bg-blue-100` | Table row hover, selected card border |

### Status & Statutory Intent Colors
| State | Text / Icon | Surface Tint | Border | Statutory Reference |
|---|---|---|---|---|
| **Raised / Current** | `#0284c7` (`sky-700`) | `#f0f9ff` (`sky-50`) | `#bae6fd` (`sky-200`) | Section 15 45-day window |
| **Accepted** | `#2563eb` (`blue-600`) | `#eff6ff` (`blue-50`) | `#bfdbfe` (`blue-200`) | Buyer GRN countersigned |
| **Financed (TReDS)** | `#7c3aed` (`violet-600`) | `#f5f3ff` (`violet-50`) | `#ddd6fe` (`violet-200`) | Factoring Act 2011 institutional auction |
| **Settled** | `#16a34a` (`emerald-600`) | `#f0fdf4` (`emerald-50`) | `#bbf7d0` (`emerald-200`) | RTGS CMS clearance |
| **Disputed (MSEFC)** | `#dc2626` (`red-600`) | `#fef2f2` (`red-50`) | `#fecaca` (`red-200`) | MSMED Act Sec 18 reference / Samadhaan |
| **Pending Warning** | `#d97706` (`amber-600`) | `#fffbeb` (`amber-50`) | `#fde68a` (`amber-200`) | Day 40-45 countdown threshold |

---

## 3. Typography Hierarchy

Font Family: `'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`  
Monospace (Figures & Codes): `ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace`

| Level | Size | Weight | Line Height | Tracking | Application |
|---|---|---|---|---|---|
| **Display Hero** | `36px` / `2.25rem` | 800 (Bold) | `1.15` | `-0.035em` | Page header metrics, financial totals |
| **H1** | `28px` / `1.75rem` | 800 (Bold) | `1.2` | `-0.025em` | Invoice ID title, screen headers |
| **H2** | `20px` / `1.25rem` | 700 (Bold) | `1.3` | `-0.02em` | Section headings, modal drawer titles |
| **H3 / Card Header** | `15px` / `0.9375rem` | 700 (Bold) | `1.4` | `-0.01em` | Group labels, checklist card headers |
| **Body Large** | `15px` / `0.9375rem` | 500 (Medium) | `1.5` | `normal` | Hero lede, primary descriptions |
| **Body Regular** | `13px` / `0.8125rem` | 500 (Medium) | `1.5` | `normal` | Table cells, field values, drawer copy |
| **Caption / Label** | `11px` / `0.6875rem` | 700 (Bold) | `1.3` | `+0.05em` | Table headers, pill tags, metadata keys |
| **Micro / Subtitle** | `9.5px` / `0.6rem` | 700 (Bold) | `1.2` | `+0.18em` | Brand tagline, legal statutory chips |

---

## 4. Spacing & Elevation Matrix

- **Grid Unit Base:** `4px` (`0.25rem`)
- **Container Padding (Mobile):** `px-4` (`16px`), `py-3` (`12px`)
- **Card Padding:** `p-4` (`16px`) or `p-5` (`20px`)
- **Form Field Gap:** `gap-3.5` (`14px`)
- **Section Stack Gap:** `space-y-4` (`16px`)
- **Corner Radii:**
  - Badges & Micro Pills: `rounded-full` or `rounded-md` (`6px`)
  - Inputs & Dropdowns: `rounded-xl` (`12px`)
  - Cards & Drawers: `rounded-2xl` (`16px` to `24px`)
  - Modal Bottom Sheets: `rounded-t-[28px]`

---

## 5. Component Patterns & Rules

### A. Number & Currency Formatting
- **Standard Format:** Indian groupings (`₹8,42,100.00`, `₹12,65,000.00`).
- **Compact Figures:** Axis and KPI summary (`₹2.5L`, `₹40K`, `₹5.0Cr`).
- **Statutory Compounding Interest:** Calculated strictly as **3 × RBI Repo Rate (compounded monthly)** under Section 16 of MSMED Act.

### B. Status Tabs Order (Verbatim from WIREFRAMES.md)
```
All  ·  Raised  ·  Accepted  ·  Financed  ·  Settled  ·  Disputed
```
*(Note: 'Draft' is strictly forbidden as a status tab).*

### C. Gesture & Motion Standard (Mobile Modal Sheets)
- **Entrance:** `translateY(100%)` → `translateY(0)` with `cubic-bezier(0.32, 0.72, 0, 1)`.
- **Dismissal Threshold:** `80px` vertical drag displacement or high flick velocity.
- **Haptic Feedback:**
  - Crossing threshold down: `navigator.vibrate(15)`
  - Rebounding back up: `navigator.vibrate(8)`
  - Confirmation of dismiss: `navigator.vibrate(20)`
- **Backdrop Scrim:** `bg-slate-950/70` with `backdrop-blur-xs` fading proportionally during drag.
