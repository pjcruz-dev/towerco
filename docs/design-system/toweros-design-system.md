# TowerOS enterprise SaaS design system

**Audience:** Product, design, and engineering (Next.js 14 + Tailwind + shadcn/ui).  
**Style:** Telecom enterprise SaaS, modern ERP, operational clarity — aligned with Azure Portal, ServiceNow, Atlassian Jira.  
**Philosophy:** Operational minimalism (see `.cursor/rules/uiux-theme.mdc`). Data-first, map-first where relevant, low motion.

**Board presentation alignment:** Add `Rules/TowerOS_Board_Presentation.pdf` (or `docs/Rules/TowerOS_Board_Presentation.pdf`) to the repository to lock module naming and information architecture. Until then, the app follows the **Infrastructure** / **Governance** grouping and module labels defined in `app-sidebar.tsx` and `platform-console-sidebar.tsx`.

---

## 1. Color palette

### 1.1 Core tokens (light)

| Token | Hex | Usage |
|--------|-----|--------|
| `background` | `#F8FAFC` | Page canvas |
| `surface` / `card` | `#FFFFFF` | Cards, panels, modals |
| `border` | `#E2E8F0` | Dividers, table lines, inputs |
| `border-strong` | `#CBD5E1` | Focus hierarchy, selected row |
| `primary` | `#2563EB` | Primary actions, links, key focus |
| `primary-hover` | `#1D4ED8` | Hover state |
| `text-primary` | `#0F172A` | Headings, primary copy |
| `text-secondary` | `#64748B` | Metadata, column descriptions |
| `text-muted` | `#94A3B8` | Placeholders, disabled (use sparingly) |
| `success` | `#16A34A` | Healthy, completed, pass |
| `warning` | `#D97706` | Degraded, attention, SLA risk |
| `danger` | `#DC2626` | Critical, failed, destructive |
| `info` | `#0284C7` | Informational banners (optional) |

### 1.2 Core tokens (dark)

| Token | Hex | Usage |
|--------|-----|--------|
| `background` | `#0F172A` | Page canvas |
| `surface` / `card` | `#111827` | Cards, panels |
| `border` | `#1F2937` | Default borders |
| `border-strong` | `#374151` | Emphasis borders |
| `primary` | `#3B82F6` | Primary actions |
| `primary-hover` | `#2563EB` | Hover |
| `text-primary` | `#F9FAFB` | Primary copy |
| `text-secondary` | `#94A3B8` | Secondary |
| `success` | `#22C55E` | Success |
| `warning` | `#F59E0B` | Warning |
| `danger` | `#EF4444` | Danger |

### 1.3 Semantic overlays (both themes)

- **NOC / alarm severity:** map operational events to `success` → `warning` → `danger`; reserve **purple/indigo** only for a single “informational / third-party” channel if needed — avoid rainbow UIs.
- **Data visualization:** use primary blue + neutrals for series 1–3; extend with controlled tints of success/warning/danger for status series — max **6** distinguishable series without patterns; use **patterns or labels** for accessibility.
- **Focus ring:** `ring-2 ring-primary ring-offset-2 ring-offset-background` (light/dark offset follows canvas).
- **Selection / highlight:** light `bg-blue-50` + `border-primary`; dark `bg-blue-950/40` + `border-primary`.

### 1.4 Theme switching

- Persist preference (`light` | `dark` | `system`) in `localStorage` + `prefers-color-scheme` fallback.
- Avoid flash: inline script or `class` on `html` before paint; use CSS variables (`--background`, `--card`, …) mapped in `globals.css` for shadcn compatibility.

---

## 2. Typography system

**Font stack:** Inter or Geist (single family per product build).

| Role | Size | Weight | Line height | Letter-spacing |
|------|------|--------|---------------|------------------|
| Page title | 24px (`text-2xl`) | 600 | 1.2 | -0.02em |
| Section title | 20px | 600 | 1.3 | -0.01em |
| Card title | 16px | 600 | 1.4 | default |
| Body | 14px | 400 | 1.5 | default |
| Table cell | 13px | 400 | 1.45 | default |
| Label / overline | 12px | 500 | 1.4 | +0.02em for overlines |
| Mono / IDs | 13px | 400 | 1.45 | use `font-variant-numeric: tabular-nums` |

**Rules**

- **KPIs and metrics:** tabular figures; optional `font-feature-settings: "tnum"`.
- **Uppercase:** only for micro-labels (NOC badges, column tags); never for sentences.
- **Truncation:** single-line with tooltip on hover/focus for dense tables; multi-line clamp at 2 in cards.

---

## 3. Spacing system

Base unit **4px**. Align with Tailwind default scale.

| Token | px | Typical use |
|-------|-----|----------------|
| `space-1` | 4 | Icon gaps, tight inline |
| `space-2` | 8 | Input padding-y, compact stacks |
| `space-3` | 12 | Form field gaps |
| `space-4` | 16 | Card padding, section internal |
| `space-6` | 24 | Between form groups |
| `space-8` | 32 | Section separation |
| `space-10`–`12` | 40–48 | Page gutters, dashboard grid gaps |

**Layout**

- **Page horizontal padding:** `px-4` mobile → `px-6` tablet → `px-8` desktop.
- **Content max-width:** optional `max-w-[1600px]` for ultra-wide readability on executive dashboards; NOC may use full width with consistent gutters.

---

## 4. Table system

**Density modes**

- **Comfortable (default):** row height ≥ 44px; executive and general work.
- **Compact:** row height ~36px; NOC and data-heavy grids — still meet **minimum 32px** tap row on mobile or switch to card list.

**Structure**

- **Sticky header** with same background as card + bottom border `border-strong`.
- **Zebra:** optional, subtle (`bg-muted/30` every other row); prefer row hover `bg-muted/50` for scanability.
- **Toolbar:** above table — search, filters, bulk actions, export; primary export on the right (LTR).
- **Pagination:** bottom-right; page size selector 10 / 25 / 50 / 100.
- **Column resize:** optional for power users; persist widths in `localStorage` per view key.
- **Empty / loading:** use §11–12 patterns inside table body.

**Responsive**

- Below `md`: horizontal scroll with sticky first column **or** stacked card rows with same data contract.

---

## 5. KPI widgets

**Sizes**

- **Large (executive):** 2× grid span; primary metric 28–32px tabular; delta and sparkline secondary.
- **Standard:** single grid cell; metric 24px; subtitle 12px secondary color.
- **Compact (NOC strip):** metric 18px; trend icon only; no chart unless critical.

**Content**

- Label (12px, secondary) → Value (tabular) → Delta (color: success/danger, icon + %) → optional sparkline.
- **Thresholds:** green / amber / red from design tokens; do not rely on color alone — include icon or “High / OK” text in executive views.

**Interaction**

- Whole card clickable only if navigates somewhere; otherwise actions as links/buttons in footer.

---

## 6. Sidebar

**Desktop**

- Width **260px** expanded; **64px** icon rail when collapsed (tooltips on icons).
- Background: `surface` with `border-r border`.
- **Hierarchy:** Module (section header 12px uppercase muted) → Feature (14px, `text-primary`) → optional nested items (indented `pl-3`).
- **Active item:** `bg-primary/10` + `text-primary` + `border-l-2 border-primary` (or left bar 3px).
- **Collapse:** persist per user; keyboard shortcut documented in command palette (future).

**Mobile**

- Hidden by default; **hamburger** opens **sheet** overlay full-height; close on route change + explicit close.

---

## 7. Header

**Height:** 56px fixed; `border-b border`; background `surface` or same as page (subtle elevation).

**Left:** product mark + optional **environment badge** (DEV / STAGING — `warning` background, never in prod customer tenants unless white-label).

**Center (optional):** global search / command trigger (⌘K pattern).

**Right:** tenant context, notifications, help, **theme toggle**, user menu (avatar + name + role).

**Breadcrumbs:** below header on deep pages (14px secondary) — Module / Feature / Current.

---

## 8. Forms

- **Input height:** 40px default; **44px** on field-engineer mobile flows.
- **Labels:** always visible (not placeholder-only); 12px medium, `text-primary`.
- **Help text:** 12px `text-secondary` under field.
- **Errors:** `danger` text 12px + `border-danger` on input; announce for screen readers.
- **Groups:** card sections with 16px title; `space-6` between groups, `space-3` within.
- **Actions:** primary right-aligned in footer bar on wide forms; sticky footer on long scroll.
- **Keyboard:** logical tab order; `Enter` submits when safe; destructive actions require confirmation pattern.

---

## 9. Modals

- **Default:** max-width `lg` (512px) for confirmations; `xl` for dense forms.
- **Backdrop:** `bg-black/50` light, `bg-black/60` dark.
- **Header:** section title + close; no marketing copy.
- **Prefer drawers** (`Sheet`) for filters, record detail, multi-step edits — right side, width `md:max-w-md` to `lg:max-w-2xl`.
- **Focus trap** and return focus on close (shadcn/Radix defaults).

---

## 10. Toast notifications

- **Position:** bottom-right desktop; top center mobile (thumb reach optional bottom).
- **Duration:** success 4s; info 5s; warning/danger sticky until dismissed or 8s minimum.
- **Content:** one line title + optional second line; icon aligned severity color.
- **Stacking:** max 3 visible; queue remainder.
- **NOC:** consider a **persistent alert strip** above header for Sev1 — not only toast.

---

## 11. Loading states

- **Initial page:** skeleton blocks matching final layout (sidebar + header + content blocks).
- **Table refresh:** overlay opacity 30% + small spinner on table corner **or** row skeletons for first load only.
- **Buttons:** inline spinner + disabled state; preserve width to avoid layout shift.
- **Maps:** dark placeholder with subtle pulse; load attribution after tiles.

**Forbidden:** blocking full-screen spinners except first app shell load.

---

## 12. Empty states

- **Icon:** simple line icon (Lucide), muted — no illustration clutter.
- **Title:** 16px semibold; **Body:** 14px secondary, max 2 lines.
- **Primary CTA:** one button (e.g. “Create asset”); secondary “Learn more” link optional.
- **Permissions:** if empty because user cannot create, explain lack of access generically (no permission names in UI copy unless internal admin).

---

## 13. Error states

- **Inline:** field + banner under section.
- **Page-level 404/403:** centered card, short message, one primary action (Go back / Home).
- **API / partial failure:** banner at top of workspace with **Retry**; do not clear user’s filters.
- **Copy:** operational tone (“Could not load towers”) not stack traces; support reference ID in monospace 12px.

---

## 14. Card layouts

- **Radius:** `rounded-xl` (12px); **Shadow:** soft `shadow-sm`, hover `shadow-md` for clickable cards only.
- **Padding:** `p-4` default; `p-6` for hero KPI cards.
- **Border:** `border` default; no double borders when nested — use `bg-muted/30` inner sections instead.
- **Header row:** title left, actions right (`gap-2`).

---

## 15. Dashboard layouts

### 15.1 Executive dashboard

- **Top row:** 3–4 KPI large widgets + optional single “portfolio health” mini chart.
- **Second:** two columns — left “Risks / SLA” list (table-light), right “Regional summary” or bar chart (one chart only unless truly needed).
- **Density:** comfortable; whitespace prioritized.

### 15.2 NOC dashboard

- **Top:** full-width **alert ticker** or severity summary chips (Critical / Major / Minor counts).
- **Main:** asymmetric grid — **60% map or topology**, **40%** incident table (compact density, live sort).
- **Bottom:** optional event stream (mono 13px, time first).
- **Refresh:** visible “last updated” + manual refresh; respect WebSocket for live without spinner spam.

### 15.3 Grid mechanics

- CSS grid `gap-4` / `gap-6`; widgets `min-h` to prevent jump; avoid more than **8** primary tiles above fold on 1440px.

---

## 16. GIS map container design

- **Chrome:** dark toolbar (`bg-card` / elevated) **over** dark basemap; icons 16–20px, `text-secondary` default, `text-primary` on active tool.
- **Padding:** map fills panel; toolbar **floating** top-left with `rounded-lg border shadow-sm` or docked top bar — pick one pattern per product area and reuse.
- **Legend:** bottom-left or collapsible drawer; compact rows, color swatch 12×12 + label 12px.
- **Layers panel:** drawer or left stack; checkboxes + opacity slider where needed.
- **Markers:** shape + color by severity; cluster with count badge; selected marker ring `ring-2 ring-primary`.
- **Performance:** throttle hover tooltips; virtualize side lists tied to map selection.

---

## 17. Mobile responsiveness standards

**Breakpoints (Tailwind-aligned)**

- `sm` 640px — begin two-column where safe.
- `md` 768px — sidebar → drawer; table scroll or cards.
- `lg` 1024px — full shell with sidebar expanded default.
- `xl` 1280px — comfortable dashboard columns.

**Touch**

- Minimum **44×44px** touch targets for field workflows; **8px** between adjacent actions.
- **Forms:** single column; sticky primary action bar at bottom safe area.

**Navigation**

- Bottom tab bar **only** if product has ≤4 primary modules (optional); otherwise hamburger + sidebar sheet.

**Maps mobile**

- Full viewport map; FAB for layers; bottom sheet for object details (drag handle).

**Accessibility**

- Contrast: WCAG AA minimum for text and interactive states.
- Focus visible on all themes; respect `prefers-reduced-motion` (disable non-essential transitions).

---

## Implementation notes (engineering)

- Map tokens to **shadcn** CSS variables (`--background`, `--foreground`, `--primary`, `--destructive`, `--border`, `--muted`, `--card`).
- Reuse **Radix** primitives for Dialog, Sheet, Toast, Dropdown — behavior matches enterprise expectations.
- **Storybook** (optional): stories for Table densities, KPI, MapToolbar, ThemeSwitch.
- **Tenant branding:** follow §18 (full theme overrides), **charts:** §19 (**Recharts**), **maps:** §20 (**MapLibre**).
- **Shipped in repo:** `GET /api/v1/public/tenant-branding?domain=` (central), `theme_tokens` on `tenants` + platform `PATCH /platform/tenants/{tenant}`, frontend `TenantThemeBridge` + `OperationalMapPanel`, dashboard chart colors read from CSS variables.

---

## 18. Full theme overrides (tenant white-label)

**Decision:** Tenants may override the **full semantic token set**, not only primary accent.

### 18.1 Contract

- Store a **versioned JSON** document per tenant (e.g. `theme_version`, `tokens`) mapping **semantic keys** to hex / hsl — same keys as shadcn-style CSS variables: `background`, `foreground`, `card`, `card-foreground`, `popover`, `primary`, `primary-foreground`, `secondary`, `muted`, `muted-foreground`, `accent`, `destructive`, `border`, `input`, `ring`, chart series keys if needed.
- **Never** accept arbitrary CSS strings from tenants (no `background: url()`, no `expression()`), only validated token values and optional **logo URL** + **favicon URL** (HTTPS allowlist, size limits).

### 18.2 Application order

1. Load TowerOS **default** theme (light/dark base).
2. Merge tenant overrides for the active mode (`light` / `dark` keys in JSON or flat with mode suffix).
3. Apply via **`style` on `html` or a single injected `<style id="tenant-theme">`** setting CSS variables — avoid flash with inline critical variables on first paint when possible.

### 18.3 Validation & safety

- **Server-side:** validate hex/hsl format, length, and allowlist of keys; reject unknown keys.
- **Contrast:** automated check against WCAG AA for `foreground` on `background`, `primary-foreground` on `primary`, and table text on `card`; if fail — clamp to nearest safe value or reject save with actionable errors.
- **Fallback:** if merge fails at runtime, revert to default theme and log (no broken UI).

### 18.4 Product rules

- **Platform console** (central): always default TowerOS branding — no tenant theme merge on operator tools unless explicitly scoped “preview as tenant.”
- **Documentation:** tenant admin UI explains that extreme contrast choices may be adjusted for accessibility.

---

## 19. Charting standard (Recharts)

**Decision:** **Recharts** is the canonical chart library for TowerOS (Next.js 14, React tree, composable with design tokens).

### 19.1 Usage

- Read colors from **CSS variables** (`hsl(var(--primary))`, muted series from `--muted-foreground` / chart tokens) so **light, dark, and tenant overrides** apply without duplicate palettes in JS.
- Prefer **sparse defaults:** line + area (subtle fill), bar, horizontal bar for rankings, small **sparkline** wrapper for KPI cards — avoid chart junk (3D, heavy animation).

### 19.2 Enterprise patterns

- **Tooltips:** single column, 12–13px, tabular numbers for values; show units.
- **Axes:** minimal ticks; NOC views may use denser ticks with `font-size: 11px` only inside chart canvas.
- **Animation:** default Recharts duration reduced or disabled when `prefers-reduced-motion` is set.
- **Performance:** virtualize off-screen dashboard charts; debounce resize; keep dataset transforms in `useMemo`.

### 19.3 Escalation path

- If a screen requires **geospatial charting** or heavy correlation matrices, propose **ECharts** or canvas-native layer in a **separate ADR** — do not mix chart libraries in one dashboard tile without design review.

---

## 20. GIS standard (MapLibre GL JS)

**Decision:** **MapLibre GL JS** is the canonical 2D map runtime for TowerOS (open stack, vector tiles, style JSON, aligns with operational NOC/GIS layouts in §16).

### 20.1 Usage

- **Basemap:** vector style JSON (hosted or bundled); prefer **dark operational** styles for NOC; tenant-branded maps may swap style URL if provided URLs pass CSP and HTTPS checks.
- **Controls:** custom TowerOS toolbar (§16) calling MapLibre API — avoid default control clutter where product UX requires density.
- **Layers:** GeoJSON sources + symbol/circle/fill layers; use **clustering** for point density; severity from data-driven styling where possible.

### 20.2 Mapbox compatibility

- **Mapbox GL** API is largely compatible; if a deployment uses **Mapbox** tiles or Studio styles, keep abstractions behind a `MapRuntime` interface so documentation and training stay MapLibre-first.

### 20.3 3D / globe

- **Cesium** or MapLibre 3D is **out of scope** for the default design system; if introduced later, add toolbar height, pitch control, and motion guidelines in a dedicated appendix (field-of-view and motion sickness considerations).
