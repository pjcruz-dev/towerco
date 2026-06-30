# TowerOS Design System

**Version:** 1.0.0  
**Stack:** Next.js 14 · Tailwind CSS v4 · shadcn/ui (Base UI primitives) · Lucide Icons · Geist font  
**Scope:** Platform-wide — tenant workspace, platform console, public pages

> This is the single source of truth for all UI/UX decisions in TowerOS. All new screens, components, and modifications must conform to this document. TowerOS is an enterprise telecom SaaS — the design philosophy is **Operational Minimalism**: clean, fast, data-first, low cognitive load.

---

## Table of Contents

1. [Typography](#1-typography)
2. [Colors](#2-colors)
3. [Spacing](#3-spacing)
4. [Border Radius](#4-border-radius)
5. [Shadows](#5-shadows)
6. [Buttons](#6-buttons)
7. [Forms](#7-forms)
8. [Tables](#8-tables)
9. [Cards](#9-cards)
10. [Modals & Dialogs](#10-modals--dialogs)
11. [Sidebar](#11-sidebar)
12. [Header](#12-header)
13. [Breadcrumbs](#13-breadcrumbs)
14. [Tabs](#14-tabs)
15. [Pagination](#15-pagination)
16. [Badges](#16-badges)
17. [Status Colors](#17-status-colors)
18. [Icons](#18-icons)
19. [Responsive Rules](#19-responsive-rules)
20. [Accessibility](#20-accessibility)

---

## 1. Typography

### Font Families

| Token | Value | Usage |
|---|---|---|
| `--font-sans` | Geist Sans | Body, UI, all prose |
| `--font-mono` | Geist Mono | Code, IDs, document numbers, reference keys |
| `--font-heading` | Geist Sans | Page titles, section titles |

**Never** use system fallback fonts in new UI. Always load via `next/font/local` with Geist.

---

### Font Size Scale

Defined in `app/tokens.css` — use Tailwind utility classes.

| Token | CSS Var | Size | Tailwind | Usage |
|---|---|---|---|---|
| Label | `--font-size-label` | **12px** | `text-xs` | Form labels, table column headers (small), badges, timestamps, meta info |
| Table text | `--font-size-table` | **13px** | `text-[0.8125rem]` | Table cell content |
| Body | `--font-size-body` | **14px** | `text-sm` | Default body text, form inputs, descriptions, list items |
| Card title | `--font-size-card-title` | **16px** | `text-base` | Card headings, drawer titles, modal titles |
| Section title | `--font-size-section-title` | **20px** | `text-xl` | Section headings within a page |
| Page title | `--font-size-page-title` | **24px** | `text-2xl` | `<h1>` page-level title |

---

### Font Weights

| Weight | Tailwind | Usage |
|---|---|---|
| 400 | `font-normal` | Body text, table cell values, descriptions |
| 500 | `font-medium` | Labels, nav items, card titles, button text, secondary headings |
| 600 | `font-semibold` | Page titles (`<h1>`), primary action labels, emphasis |

**Do not use** `font-bold` (700), `font-extrabold` (800), or `font-black` (900) in any platform or tenant UI. Only ever in marketing/landing pages.

---

### Line Heights

| Token | CSS Var | Value | Usage |
|---|---|---|---|
| Label | `--line-height-label` | `1rem` | 12px labels |
| Body | `--line-height-body` | `1.25rem` | Body text |
| Table | `--line-height-table` | `1.125rem` | Table cells |
| Card title | `--line-height-card-title` | `1.5rem` | Card/modal titles |
| Section title | `--line-height-section-title` | `1.75rem` | Section headings |
| Page title | `--line-height-page-title` | `1.75rem` | Page `<h1>` |

---

### Typography Hierarchy — Example

```tsx
// Page title
<h1 className="text-2xl font-semibold text-foreground">Rollout Management</h1>

// Page subtitle / description
<p className="mt-1 text-sm text-muted-foreground">Manage rollout phases and gate approvals.</p>

// Section title
<h2 className="text-xl font-semibold text-foreground">Active Rollouts</h2>

// Card title
<p className="text-base font-medium text-foreground">Phase Summary</p>

// Form label
<label className="text-sm font-medium text-foreground">Project name</label>

// Meta / timestamp
<span className="text-xs text-muted-foreground">Updated 3h ago</span>

// Code / reference number
<code className="font-mono text-sm">ATC-P-SCM-001</code>
```

---

## 2. Colors

All colors are defined as CSS custom properties and consumed via Tailwind semantic tokens. **Never hardcode hex values in component JSX** — always use Tailwind semantic classes.

---

### 2.1 Semantic Color Tokens (Tailwind classes)

#### Light Theme

| Role | CSS Variable | Approx Hex | Tailwind Class |
|---|---|---|---|
| **Page background** | `--background` | `#F8FAFC` | `bg-background` |
| **Card / surface** | `--card` | `#FFFFFF` | `bg-card` |
| **Popover / overlay surface** | `--popover` | `#FFFFFF` | `bg-popover` |
| **Primary action** | `--primary` | `#2563EB` | `bg-primary`, `text-primary` |
| **Primary foreground** | `--primary-foreground` | `#F9FAFB` | `text-primary-foreground` |
| **Secondary** | `--secondary` | `#EFF6FF` | `bg-secondary` |
| **Muted surface** | `--muted` | `#F9FAFB` | `bg-muted` |
| **Text primary** | `--foreground` | `#0F172A` | `text-foreground` |
| **Text secondary** | `--muted-foreground` | `#64748B` | `text-muted-foreground` |
| **Border** | `--border` | `#E2E8F0` | `border-border` |
| **Input border** | `--input` | `#E2E8F0` | `border-input` |
| **Focus ring** | `--ring` | `#2563EB` | (applied via focus-visible utilities) |
| **Destructive** | `--destructive` | `#DC2626` | `text-destructive`, `bg-destructive` |

#### Dark Theme

| Role | CSS Variable | Approx Hex |
|---|---|---|
| **Page background** | `--background` | `#0F172A` |
| **Card / surface** | `--card` | `#111827` |
| **Primary action** | `--primary` | `#3B82F6` |
| **Text primary** | `--foreground** | `#F9FAFB` |
| **Text secondary** | `--muted-foreground` | `#94A3B8` |
| **Border** | `--border` | `rgba(255,255,255,0.10)` |
| **Destructive** | `--destructive` | `#EF4444` |

---

### 2.2 Brand Scale

Full blue scale from Azure-style enterprise palette. Use via `brand-*` Tailwind color classes.

| Token | Light | Dark |
|---|---|---|
| `brand-50` | `#EFF6FF` | `#1E3A8A` |
| `brand-100` | `#DBEAFE` | `#1E40AF` |
| `brand-200` | `#BFDBFE` | `#1D4ED8` |
| `brand-300` | `#93C5FD` | `#2563EB` |
| `brand-400` | `#60A5FA` | `#3B82F6` |
| `brand-500` | `#3B82F6` | `#60A5FA` |
| `brand-600` | `#2563EB` | `#93C5FD` |
| `brand-700` | `#1D4ED8` | `#BFDBFE` |
| `brand-800` | `#1E40AF` | `#DBEAFE` |
| `brand-900` | `#1E3A8A` | `#EFF6FF` |

Use brand scale for: charts, data visualizations, progress indicators, map overlays.

---

### 2.3 Semantic Status Colors

| Status | Light | Dark | Usage |
|---|---|---|---|
| **Success** | `#16A34A` | `#22C55E` | Approved, online, completed, healthy |
| **Warning** | `#D97706` | `#F59E0B` | SLA at risk, pending review, caution |
| **Danger** | `#DC2626` | `#EF4444` | Rejected, error, alarm, critical |
| **Info** | `#0EA5E9` | `#38BDF8` | Informational, FYI, notes |

Access via CSS var: `var(--success)`, `var(--warning)`, `var(--danger)`, `var(--info)`.

---

### 2.4 Sidebar Colors

The sidebar uses a dark slate palette independent of the theme.

| Role | Value | Tailwind |
|---|---|---|
| Background | `oklch(0.145 0.024 266.497)` ≈ `#0F172A` | `bg-sidebar` |
| Text default | `oklch(0.985 0.01 262.881)` ≈ `#F9FAFB` | `text-sidebar-foreground` |
| Nav item inactive | Slate 400 — `#94A3B8` | `text-slate-400` |
| Nav item active / hover | Slate 800 bg + White text | `bg-slate-800 text-white` |
| Section label | Slate 500 — `#64748B` | `text-slate-500` |
| Border | `oklch(0.205 0.024 266.497)` ≈ `#1E293B` | `border-slate-800` |

---

## 3. Spacing

TowerOS uses the standard Tailwind spacing scale (0.25rem / 4px per unit) plus enterprise extensions up to `spacing-90`.

### 3.1 Core Spacing Scale

| Step | px | rem | Tailwind | Usage |
|---|---|---|---|---|
| 0 | 0 | 0 | `p-0` | Reset |
| 0.5 | 2px | 0.125rem | `gap-0.5` | Micro gap |
| 1 | 4px | 0.25rem | `p-1` | Inline icon padding |
| 1.5 | 6px | 0.375rem | `gap-1.5` | Button icon gap |
| 2 | 8px | 0.5rem | `p-2` | Compact cell padding |
| 2.5 | 10px | 0.625rem | `px-2.5` | Input / button horizontal padding |
| 3 | 12px | 0.75rem | `p-3` | Small card padding (sm) |
| 4 | 16px | 1rem | `p-4` | Standard card / panel padding |
| 5 | 20px | 1.25rem | `p-5` | Card section padding |
| 6 | 24px | 1.5rem | `p-6` | Page content gutter (mobile) |
| 8 | 32px | 2rem | `p-8` | Page content gutter (desktop) |

### 3.2 Layout Sizing Tokens

| Token | Value | Usage |
|---|---|---|
| `--shell-header-h` | `3.5rem` (56px) | App header height |
| `--shell-content-gutter` | `1.25rem` | Internal content gap |
| `--shell-content-max-w` | `120rem` (1920px) | Maximum content width |
| `--sidebar-collapsed-w` | `4.5rem` (72px) | Sidebar icon-only mode |
| `--sidebar-default-w` | `16rem` (256px) | Sidebar expanded default |
| `--sidebar-expanded-w` | `18rem` (288px) | Sidebar on mobile overlay |
| `--card-padding-sm` | `0.75rem` | Card padding compact |
| `--card-padding-md` | `1rem` | Card padding default |
| `--card-padding-lg` | `1.25rem` | Card padding large |
| `--dashboard-gap` | `1rem` | Gap between dashboard widgets |
| `--dashboard-section-gap` | `1.25rem` | Gap between dashboard sections |

### 3.3 Page Layout

All platform pages use the AppShell wrapper. Content max-width is `max-w-[min(100%,1920px)]` with `p-6 lg:p-8` padding.

```tsx
// Standard page structure
<div className="space-y-6">
  {/* Page header */}
  <div className="flex items-start justify-between gap-4">
    <div>
      <h1 className="text-2xl font-semibold text-foreground">Page Title</h1>
      <p className="mt-1 text-sm text-muted-foreground">Description.</p>
    </div>
    <Button>Primary action</Button>
  </div>

  {/* Content sections */}
  <div className="rounded-xl border border-border bg-card shadow-sm">
    {/* ... */}
  </div>
</div>
```

---

## 4. Border Radius

All radii are derived from the base `--radius: 0.75rem` token.

| Token | Value | px | Tailwind | Usage |
|---|---|---|---|---|
| `--radius-sm` | `0.45rem` | ~7px | `rounded-md` | Badges, chips, small inner elements |
| `--radius-md` | `0.6rem` | ~10px | `rounded-lg` | Buttons (sm/xs), inputs, form controls |
| `--radius-lg` | `0.75rem` | 12px | `rounded-xl` | Cards, panels, modals, drawers |
| `--radius-xl` | `1.05rem` | ~17px | `rounded-2xl` | Featured cards, hero panels |
| `--radius-card` | `0.75rem` | 12px | Semantic card radius |
| `--radius-panel` | `0.875rem` | 14px | Semantic panel radius |
| `--radius-interactive` | `0.5rem` | 8px | Buttons (default), toggles |
| `4xl` | Full pill | 9999px | `rounded-full` | Badges (pill shape), avatar, status dots |

### Rules

- **Cards and modals:** always `rounded-xl`
- **Buttons:** `rounded-lg` (default), `rounded-md` for sm/xs
- **Form inputs:** `rounded-lg`
- **Badges:** `rounded-full` (pill) or `rounded-4xl`
- **Tooltips:** `rounded-md`
- **Never** use `rounded-none` on visible UI elements except table rows and dividers

---

## 5. Shadows

Three elevation levels. Use CSS variables — do not hardcode box-shadow values.

| Token | CSS Var | Value | Tailwind Usage | When to use |
|---|---|---|---|---|
| **Surface** | `--elevation-surface` | `0 1px 2px 0 rgb(15 23 42 / 0.04)` | `shadow-sm` | Subtle card lift, table container |
| **Card** | `--elevation-card` | `0 4px 14px -8px rgb(15 23 42 / 0.12)` | `shadow-sm` (or semantic `shadow-card`) | Floating cards, detail drawers |
| **Overlay** | `--elevation-overlay` | `0 24px 48px -16px rgb(15 23 42 / 0.35)` | `shadow-lg` | Modals, dialogs, popovers |

### Dark Mode Shadows

Dark mode shadows are deeper to compensate for low contrast surfaces:
- Surface: `0 1px 2px 0 rgb(2 6 23 / 0.5)`
- Card: `0 8px 24px -12px rgb(2 6 23 / 0.65)`
- Overlay: `0 28px 56px -16px rgb(2 6 23 / 0.9)`

### Usage Rules

```tsx
// Standard data card
<div className="rounded-xl border border-border bg-card shadow-sm">

// Modal / dialog
// shadow-lg is applied automatically via the Dialog component

// No shadow (flat surface, already on bg-card)
<div className="rounded-xl border border-border bg-card">
```

---

## 6. Buttons

Component: `components/ui/button.tsx` — built on Base UI `<Button>` primitive with CVA variants.

### 6.1 Variants

| Variant | Class | Usage |
|---|---|---|
| **default** (primary) | `bg-primary text-primary-foreground` | Primary CTA — save, submit, create |
| **outline** | `border-border bg-background hover:bg-muted` | Secondary action, filters, nav buttons |
| **secondary** | `bg-secondary text-secondary-foreground` | Tertiary action |
| **ghost** | `hover:bg-muted hover:text-foreground` | Icon buttons, toolbar actions, close buttons |
| **destructive** | `bg-destructive/10 text-destructive` | Delete, reject, remove |
| **link** | `text-primary underline-offset-4 hover:underline` | Inline text links that behave as buttons |

### 6.2 Sizes

| Size | Height | Padding | Text | Usage |
|---|---|---|---|---|
| `xs` | `h-6` (24px) | `px-2` | `text-xs` | Dense table actions, compact toolbars |
| `sm` | `h-7` (28px) | `px-2.5` | `text-[0.8rem]` | Toolbar buttons, filter actions, secondary |
| `default` | `h-8` (32px) | `px-2.5` | `text-sm` | Standard usage |
| `lg` | `h-9` (36px) | `px-2.5` | `text-sm` | Primary form CTAs |
| `icon-xs` | `size-6` | — | — | Tiny icon-only actions |
| `icon-sm` | `size-7` | — | — | Close buttons, toolbar icons |
| `icon` | `size-8` | — | — | Standard icon button |
| `icon-lg` | `size-9` | — | — | Large icon buttons |

### 6.3 Usage Examples

```tsx
import { Button } from "@/components/ui/button";

// Primary action
<Button>Save changes</Button>

// With icon (gap-1.5 is built into the variant)
<Button><PlusIcon /> New rollout</Button>

// Secondary / outline
<Button variant="outline">Cancel</Button>
<Button variant="outline" size="sm">Export</Button>

// Danger / destructive
<Button variant="destructive">Delete document</Button>

// Ghost icon button
<Button variant="ghost" size="icon-sm" aria-label="Close">
  <XIcon />
</Button>

// Disabled (applies disabled:opacity-50 automatically)
<Button disabled>Processing…</Button>

// As link (render prop pattern)
<Button render={<Link href="/settings" />}>Settings</Button>

// Submit in forms (use type="submit" — bypasses Base UI wrapper)
<Button type="submit">Submit</Button>
```

### 6.4 Button Group

```tsx
// Segmented control / toggle group
<div className="flex items-center rounded-lg border border-border bg-muted/30 p-0.5">
  {options.map((opt) => (
    <button
      key={opt.id}
      className={cn(
        "rounded-md px-2.5 py-1 text-xs font-medium transition-colors",
        active === opt.id
          ? "bg-card text-foreground shadow-sm"
          : "text-muted-foreground hover:text-foreground",
      )}
      onClick={() => setActive(opt.id)}
    >
      {opt.label}
    </button>
  ))}
</div>
```

### 6.5 Rules

- Always provide `type="button"` on non-submit buttons (prevents form submission)
- Always include `aria-label` on icon-only buttons
- Primary button: **maximum one per view section**
- Destructive actions must require confirmation (Dialog or inline confirm)
- Disable buttons during async mutations with `disabled={isPending}`

---

## 7. Forms

### 7.1 Field Control Base

All form controls share the same base style via `fieldControlClassName` (`lib/ui/field-control.ts`):

```
w-full min-w-0 rounded-lg border border-input bg-transparent px-2.5 text-sm
transition-colors outline-none
placeholder:text-muted-foreground
focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50
disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-input/50 disabled:opacity-50
aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20
dark:bg-input/30 dark:disabled:bg-input/80
```

---

### 7.2 Input

Component: `components/ui/input.tsx`

```tsx
import { Input } from "@/components/ui/input";

// Standard input
<Input type="text" placeholder="Enter project name" />

// With label (always pair inputs with labels)
<div className="space-y-1.5">
  <Label htmlFor="project-name">Project name</Label>
  <Input id="project-name" type="text" />
</div>

// Invalid state (use aria-invalid + set ARIA error message)
<Input aria-invalid type="text" />

// Disabled
<Input disabled value="Read-only value" />
```

**Specs:** `h-8`, `rounded-lg`, `border-input`, `text-sm`, `px-2.5`

---

### 7.3 Textarea

Component: `components/ui/textarea.tsx`

```tsx
import { Textarea } from "@/components/ui/textarea";

<Textarea
  rows={4}
  placeholder="Describe the scope of change…"
/>
```

**Specs:** Same as Input + `min-h-[80px]`, `resize-y`, `py-2`

---

### 7.4 Select

Component: `components/ui/select.tsx` — native `<select>` with custom arrow icon.

```tsx
import { Select } from "@/components/ui/select";

<Select value={status} onChange={(e) => setStatus(e.target.value)}>
  <option value="">Select status</option>
  <option value="active">Active</option>
  <option value="inactive">Inactive</option>
</Select>
```

**Specs:** `h-8`, native `<select>`, custom SVG chevron via background-image

---

### 7.5 Label

Component: `components/ui/label.tsx`

```tsx
import { Label } from "@/components/ui/label";

<Label htmlFor="input-id">Field label</Label>
```

**Specs:** `text-sm font-medium text-foreground`

---

### 7.6 Checkbox

No shared component exists — use native `<input type="checkbox">` styled inline:

```tsx
<label className="flex items-center gap-2 text-sm">
  <input
    type="checkbox"
    className="size-4 rounded border-border accent-primary"
    checked={checked}
    onChange={(e) => setChecked(e.target.checked)}
  />
  <span className="text-foreground">Enable email notifications</span>
</label>
```

---

### 7.7 Radio

No shared component exists — use native `<input type="radio">` with consistent styling:

```tsx
<fieldset className="space-y-2">
  <legend className="text-sm font-medium text-foreground">Priority</legend>
  {options.map((opt) => (
    <label key={opt.value} className="flex items-center gap-2 text-sm">
      <input
        type="radio"
        name="priority"
        value={opt.value}
        className="size-4 border-border accent-primary"
        checked={value === opt.value}
        onChange={() => setValue(opt.value)}
      />
      <span className="text-foreground">{opt.label}</span>
    </label>
  ))}
</fieldset>
```

---

### 7.8 Date Picker

Use native `<Input type="date">` for simplicity, or integrate a calendar popover when range selection is needed:

```tsx
// Simple date input
<Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />

// Date-time
<Input type="datetime-local" />
```

---

### 7.9 Form Layout

```tsx
// Standard form section
<div className="space-y-4">
  {/* Group related fields */}
  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div className="space-y-1.5">
      <Label htmlFor="first-name">First name</Label>
      <Input id="first-name" type="text" />
    </div>
    <div className="space-y-1.5">
      <Label htmlFor="last-name">Last name</Label>
      <Input id="last-name" type="text" />
    </div>
  </div>

  <div className="space-y-1.5">
    <Label htmlFor="email">Email address</Label>
    <Input id="email" type="email" />
    {/* Validation error */}
    <p className="text-xs text-destructive">Please enter a valid email.</p>
  </div>
</div>
```

### 7.10 Validation States

| State | Visual | Implementation |
|---|---|---|
| Default | `border-input` | Default |
| Focus | `border-ring ring-3 ring-ring/50` | `focus-visible:` utilities (automatic) |
| Valid | No change (implicit) | — |
| Invalid | `border-destructive ring-3 ring-destructive/20` | Add `aria-invalid` attribute |
| Disabled | `opacity-50 cursor-not-allowed bg-input/50` | `disabled` attribute |

**Always show validation errors below the field, not in alerts at the top of the form.** Use `text-xs text-destructive` for error messages.

---

## 8. Tables

Component: `components/ui/table.tsx`

### 8.1 Structure

```tsx
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from "@/components/ui/table";

<div className="rounded-xl border border-border bg-card shadow-sm">
  {/* Optional toolbar above table */}
  <div className="flex items-center gap-3 border-b border-border px-4 py-3">
    <Input placeholder="Search…" className="h-8 max-w-xs" />
    <Button variant="outline" size="sm">Filter</Button>
    <div className="ml-auto">
      <Button variant="outline" size="sm">Export</Button>
    </div>
  </div>

  <Table>
    <TableHeader>
      <TableRow>
        <TableHead>Project</TableHead>
        <TableHead>Status</TableHead>
        <TableHead>Date</TableHead>
        <TableHead className="text-right">Actions</TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      {rows.map((row) => (
        <TableRow key={row.id}>
          <TableCell className="font-medium">{row.name}</TableCell>
          <TableCell><StatusBadge status={row.status} /></TableCell>
          <TableCell className="text-muted-foreground">{row.date}</TableCell>
          <TableCell className="text-right">
            <Button variant="ghost" size="icon-sm"><MoreHorizontalIcon /></Button>
          </TableCell>
        </TableRow>
      ))}
    </TableBody>
  </Table>

  {/* Pagination */}
  <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} />
</div>
```

### 8.2 Table Specs

| Element | Classes |
|---|---|
| Container | `rounded-xl border border-border bg-card shadow-sm overflow-x-auto` |
| `<table>` | `w-full caption-bottom text-sm` |
| `<thead>` | `[&_tr]:border-b` |
| `<th>` | `h-10 px-2 text-left align-middle font-medium whitespace-nowrap text-foreground` |
| `<tr>` | `border-b transition-colors hover:bg-muted/50` |
| `<td>` | `p-2 align-middle whitespace-nowrap` |
| Footer | `border-t bg-muted/50 font-medium` |

### 8.3 Table Rules

- **Sticky headers** on long tables: `<thead className="sticky top-0 bg-card z-10">`
- All numeric columns: `text-right`
- Action columns: `text-right`, fixed narrow width
- No horizontal scrolling inside cards — use `overflow-x-auto` on the container
- Empty state: replace tbody with centered message inside a `<div>` (not a `<td>`)
- Loading state: skeleton rows (3–6 rows with `animate-pulse`)

### 8.4 Empty State (Table)

```tsx
{rows.length === 0 ? (
  <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
      <InboxIcon className="h-6 w-6 text-muted-foreground/60" />
    </div>
    <div>
      <p className="text-sm font-medium text-foreground">No results found</p>
      <p className="mt-1 text-sm text-muted-foreground">Try adjusting your filters.</p>
    </div>
  </div>
) : null}
```

---

## 9. Cards

Component: `components/ui/card.tsx`

### 9.1 Structure

```tsx
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from "@/components/ui/card";

// Standard card
<Card>
  <CardHeader className="border-b border-border">
    <CardTitle>Card title</CardTitle>
    <CardDescription>Supporting description text.</CardDescription>
  </CardHeader>
  <CardContent>
    {/* Content */}
  </CardContent>
  <CardFooter>
    <Button size="sm">Action</Button>
  </CardFooter>
</Card>

// Compact card
<Card size="sm">
  <CardContent>Compact content</CardContent>
</Card>
```

### 9.2 Card Specs

| Element | Classes |
|---|---|
| Card | `rounded-xl bg-card py-4 text-sm text-card-foreground ring-1 ring-foreground/10` |
| CardHeader | `px-4 py-4 grid auto-rows-min gap-1` |
| CardTitle | `text-base font-medium leading-snug` |
| CardDescription | `text-sm text-muted-foreground` |
| CardContent | `px-4` |
| CardFooter | `border-t bg-muted/50 p-4 rounded-b-xl` |

### 9.3 Raw Card Pattern (most common)

Many pages use a raw `<div>` instead of the Card component for more layout control:

```tsx
<div className="rounded-xl border border-border bg-card shadow-sm">
  {/* Header */}
  <div className="flex items-center justify-between border-b border-border px-5 py-4">
    <div>
      <p className="text-base font-medium text-foreground">Section title</p>
      <p className="mt-0.5 text-xs text-muted-foreground">Description.</p>
    </div>
    <Button size="sm" variant="outline">Action</Button>
  </div>

  {/* Body */}
  <div className="p-5">
    {/* Content */}
  </div>
</div>
```

### 9.4 KPI / Metric Card

```tsx
<div className="rounded-xl border border-border bg-card p-4 shadow-sm">
  <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Active rollouts</p>
  <p className="mt-2 text-3xl font-semibold text-foreground">42</p>
  <p className="mt-1 text-xs text-muted-foreground">↑ 8% this month</p>
</div>
```

---

## 10. Modals & Dialogs

### 10.1 Dialog (centered modal)

Component: `components/ui/dialog.tsx` — Base UI Dialog primitive.

```tsx
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
  DialogFooter,
  DialogClose,
} from "@/components/ui/dialog";

<Dialog>
  <DialogTrigger render={<Button>Open modal</Button>} />
  <DialogContent>
    <DialogHeader>
      <DialogTitle>Confirm deletion</DialogTitle>
      <DialogDescription>This action cannot be undone.</DialogDescription>
    </DialogHeader>
    <DialogBody>
      <p className="text-sm text-muted-foreground">
        You are about to permanently delete this record.
      </p>
    </DialogBody>
    <DialogFooter>
      <DialogClose render={<Button variant="outline">Cancel</Button>} />
      <Button variant="destructive">Delete</Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

**Dialog Specs:**
- Width: `min(calc(100vw-2rem), 640px)`
- Max height: `min(90vh, 880px)`
- Backdrop: `bg-black/20 backdrop-blur-xs`
- Animation: scale + fade (200ms)
- Header: `border-b px-5 py-4`
- Body: `flex-1 overflow-y-auto px-5 py-4`
- Footer: `border-t bg-muted/20 px-5 py-4`

---

### 10.2 Sheet (Side Drawer)

Component: `components/ui/sheet.tsx` — preferred for detail views, record editing, filters.

```tsx
import {
  Sheet,
  SheetTrigger,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
} from "@/components/ui/sheet";

<Sheet>
  <SheetTrigger render={<Button variant="outline">View details</Button>} />
  <SheetContent side="right">
    <SheetHeader>
      <SheetTitle>Record details</SheetTitle>
      <SheetDescription>Review and edit the selected item.</SheetDescription>
    </SheetHeader>
    <div className="flex-1 overflow-y-auto p-4">
      {/* Content */}
    </div>
    <SheetFooter>
      <Button type="submit">Save changes</Button>
    </SheetFooter>
  </SheetContent>
</Sheet>
```

**Sheet Specs:**
- Default side: `right`
- Width: `w-3/4 sm:max-w-sm` (max ~384px)
- Backdrop: `bg-black/10 backdrop-blur-xs`
- Animation: slide in from right (200ms ease-in-out)
- Header: `flex flex-col gap-0.5 p-4`
- Footer: `mt-auto flex flex-col gap-2 p-4`
- Title: `text-base font-medium text-foreground`

**Rule:** Prefer side drawers over centered dialogs for record detail views, filters, and forms that require browsing context to remain visible.

---

### 10.3 Z-index Layers

| Layer | CSS Var | Value | Usage |
|---|---|---|---|
| `base` | `--layer-base` | 0 | Normal content |
| `sticky` | `--layer-sticky` | 20 | Sticky headers |
| `dropdown` | `--layer-dropdown` | 30 | Menus, popovers |
| `drawer` | `--layer-drawer` | 40 | Side sheets |
| `modal` | `--layer-modal` | 50 | Dialogs, modals |
| `toast` | `--layer-toast` | 60 | Toast notifications |
| `max` | `--layer-max` | 999 | Critical overlays |

---

## 11. Sidebar

Component: `components/layout/app-sidebar.tsx`

### 11.1 Layout

- **Position:** Fixed left, full height
- **Behavior:** Collapsible (icon-only) via cookie-persisted state
- **Mobile:** Slides over content as a Sheet overlay

### 11.2 Dimensions

| State | Width |
|---|---|
| Expanded (default) | `16rem` (256px) |
| Collapsed (icon) | `3rem` (48px) |
| Mobile overlay | `18rem` (288px) |

### 11.3 Visual Spec

```
┌─────────────────────┐
│  Logo / Brand       │  ← SidebarHeader: border-b border-slate-800 p-4
├─────────────────────┤
│  OPERATIONS ───     │  ← Section label: text-xs font-medium text-slate-500
│  📊 Dashboard       │  ← Nav item inactive: text-slate-400
│  ▶ PROJECT-ONE      │  ← Active: bg-slate-800 text-white rounded-md
│    ├ Rollouts       │  ← Sub-item
│    └ Gate approvals │
├─────────────────────┤
│  FINANCE ────────   │
│  💰 Procurement     │
└─────────────────────┘
```

### 11.4 Nav Item Classes

```tsx
// Button style (all states)
const navButtonClass = "text-slate-400 transition-all hover:bg-slate-800 hover:text-white data-active:bg-slate-800 data-active:text-white";

// Section label
<div className="px-3 py-2 text-xs font-medium text-slate-500 group-data-[collapsible=icon]:hidden">
  {group.group}
</div>

// Badge (pending count)
<span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-medium text-primary-foreground">
  {count}
</span>
```

### 11.5 Navigation Structure

All nav items are defined in `lib/navigation/workspace-nav-config.ts`. Follow this module hierarchy:

```
Module (top-level group)
  └── Feature (nav item with children)
        └── Action / sub-page (sub-nav item)
```

Maximum navigation depth: **3 levels.**

---

## 12. Header

Component: `components/layout/app-header.tsx`

### 12.1 Visual Spec

```
┌──────────────────────────────────────────────────────────────┐
│ ☰  Procurement › Purchase Orders › PO-2024-001    🔍 🔔 👤  │
└──────────────────────────────────────────────────────────────┘
```

### 12.2 Specs

| Property | Value |
|---|---|
| Height | `h-14` (56px) mobile / `h-16` (64px) desktop |
| Position | `sticky top-0 z-50` |
| Background | `bg-card backdrop-blur-sm` |
| Border | `border-b border-border` |
| Padding | `px-4 sm:px-6 md:px-8` |
| Left slot | Sidebar toggle + breadcrumbs |
| Right slot | Search trigger, notification bell, user profile menu |

### 12.3 Right Slot Order

1. `AppHeaderSearchTrigger` — command palette trigger
2. `TenantNotificationBell` — unread count badge
3. `UserProfileMenu` — avatar dropdown

---

## 13. Breadcrumbs

Component: `components/layout/workspace-breadcrumbs.tsx`

Breadcrumbs are rendered in the header, derived automatically from `lib/navigation/workspace-breadcrumbs.ts` based on the current pathname.

### 13.1 Visual Spec

```
Procurement  /  Purchase Orders  /  PO-2024-001
```

### 13.2 Implementation Pattern

```tsx
// Individual crumb
<span className="text-sm text-muted-foreground hover:text-foreground">
  <Link href="/procurement">Procurement</Link>
</span>

// Separator
<span className="text-muted-foreground/50 mx-1">/</span>

// Current page (last crumb, no link)
<span className="text-sm font-medium text-foreground">PO-2024-001</span>
```

### 13.3 Rules

- Maximum **4 segments** visible; collapse middle segments with `…` on mobile
- Never repeat the module name if already in the sidebar active state
- Last segment is always the current page — non-clickable

---

## 14. Tabs

Component: `components/ui/tabs.tsx` — two variants: `default` (pill) and `line`.

### 14.1 Default (Pill) Variant

Use for primary content switching within a section.

```tsx
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";

<Tabs defaultValue="details">
  <TabsList>
    <TabsTrigger value="details">Details</TabsTrigger>
    <TabsTrigger value="workflow">Workflow</TabsTrigger>
    <TabsTrigger value="comments">Comments</TabsTrigger>
  </TabsList>

  <TabsContent value="details">{/* ... */}</TabsContent>
  <TabsContent value="workflow">{/* ... */}</TabsContent>
  <TabsContent value="comments">{/* ... */}</TabsContent>
</Tabs>
```

**Specs:** Container `bg-muted rounded-lg p-[3px]`, active tab `bg-background shadow-sm rounded-md`

---

### 14.2 Line Variant

Use for page-level section navigation (e.g., Settings pages).

```tsx
<Tabs defaultValue="general">
  <TabsList variant="line">
    <TabsTrigger value="general">General</TabsTrigger>
    <TabsTrigger value="notifications">Notifications</TabsTrigger>
    <TabsTrigger value="security">Security</TabsTrigger>
  </TabsList>
</Tabs>
```

**Specs:** No background, bottom-border underline on active tab

---

### 14.3 Custom Tab Pills (non-component)

For filter tabs with unread counts or dynamic badges:

```tsx
<div className="flex items-center gap-1">
  {TABS.map((item) => (
    <button
      key={item.id}
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
        activeTab === item.id
          ? "bg-primary/10 text-primary"
          : "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
      )}
      onClick={() => setActiveTab(item.id)}
    >
      {item.label}
      {item.count > 0 ? (
        <span className={cn(
          "inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold",
          activeTab === item.id
            ? "bg-primary text-primary-foreground"
            : "bg-muted text-muted-foreground",
        )}>
          {item.count}
        </span>
      ) : null}
    </button>
  ))}
</div>
```

---

## 15. Pagination

Component: `components/registry/paginated-list-footer.tsx`

### 15.1 Implementation

```tsx
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";

// At the bottom of a card containing a table or list
<div className="border-t border-border">
  <PaginatedListFooter
    meta={data.meta}
    onPageChange={setPage}
    isPending={isFetching}
  />
</div>
```

### 15.2 Visual Spec

```
Page 3 of 12 · 287 total          [Previous]  [Next]
```

**Specs:**
- Container: `flex items-center justify-between border-t border-border px-4 py-2.5`
- Text: `text-xs text-muted-foreground`
- Buttons: `variant="outline" size="sm"`

### 15.3 Standard Page Sizes

| Context | Default `per_page` |
|---|---|
| Dense lists (notifications, approvals) | 25 |
| Standard tables (records, documents) | 20 |
| Rich data tables (procurement, finance) | 15 |

---

## 16. Badges

Component: `components/ui/badge.tsx`

### 16.1 Variants

```tsx
import { Badge } from "@/components/ui/badge";

// Default (primary blue)
<Badge>Active</Badge>

// Outline (neutral)
<Badge variant="outline">Pending</Badge>

// Destructive (error/danger)
<Badge variant="destructive">Rejected</Badge>

// Secondary (light)
<Badge variant="secondary">Draft</Badge>

// Ghost
<Badge variant="ghost">Archived</Badge>
```

**Specs:** `h-5 rounded-full px-2 text-xs font-medium` (pill shape)

### 16.2 Status Badges (Common Pattern)

```tsx
// Reusable status badge helper
function StatusBadge({ status }: { status: string }) {
  const config: Record<string, { label: string; className: string }> = {
    active:    { label: "Active",    className: "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400" },
    pending:   { label: "Pending",   className: "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-400" },
    rejected:  { label: "Rejected",  className: "border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400" },
    approved:  { label: "Approved",  className: "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400" },
    draft:     { label: "Draft",     className: "border-border text-muted-foreground" },
    archived:  { label: "Archived",  className: "border-border bg-muted text-muted-foreground" },
  };

  const c = config[status] ?? { label: status, className: "border-border text-muted-foreground" };

  return (
    <Badge variant="outline" className={c.className}>
      {c.label}
    </Badge>
  );
}
```

---

## 17. Status Colors

Use these classes consistently for status representation across the platform.

### 17.1 Status Color Map

| Status | Text | Background | Border | Icon color |
|---|---|---|---|---|
| **Success / Approved / Online** | `text-emerald-700` | `bg-emerald-50` | `border-emerald-200` | `text-emerald-600` |
| **Warning / Pending / At risk** | `text-amber-700` | `bg-amber-50` | `border-amber-200` | `text-amber-600` |
| **Danger / Rejected / Error** | `text-red-700` | `bg-red-50` | `border-red-200` | `text-red-600` |
| **Info / FYI / In progress** | `text-sky-700` | `bg-sky-50` | `border-sky-200` | `text-sky-600` |
| **Neutral / Draft / Inactive** | `text-muted-foreground` | `bg-muted` | `border-border` | `text-muted-foreground` |

Dark mode: use `/950` or `/30` opacity backgrounds, `/400` text.

### 17.2 Semantic Status Dots

```tsx
// Inline status dot
<span className={cn(
  "inline-block h-2 w-2 rounded-full",
  status === "online"   && "bg-emerald-500",
  status === "warning"  && "bg-amber-500",
  status === "offline"  && "bg-red-500",
  status === "unknown"  && "bg-muted-foreground/50",
)} />
```

### 17.3 Inline Alert / Notice Patterns

No shared Alert component exists — use this inline pattern:

```tsx
// Info alert
<div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-900 dark:bg-sky-950/30">
  <InfoIcon className="mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400" />
  <p className="text-sm text-sky-800 dark:text-sky-200">Informational message.</p>
</div>

// Warning alert
<div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/30">
  <AlertTriangleIcon className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
  <p className="text-sm text-amber-800 dark:text-amber-200">Warning message.</p>
</div>

// Error alert
<div className="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900 dark:bg-red-950/30">
  <AlertCircleIcon className="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
  <p className="text-sm text-red-800 dark:text-red-200">Error message.</p>
</div>

// Success alert
<div className="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/30">
  <CheckCircle2Icon className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
  <p className="text-sm text-emerald-800 dark:text-emerald-200">Success message.</p>
</div>
```

---

## 18. Icons

**Library:** [Lucide React](https://lucide.dev) — `lucide-react` package. No other icon libraries.

### 18.1 Sizing

| Context | Size | Tailwind |
|---|---|---|
| Button icon | 16×16 | `h-4 w-4` (auto via `[&_svg:not([class*='size-'])]:size-4`) |
| Button icon sm/xs | 14×14 | `h-3.5 w-3.5` |
| Sidebar nav icon | 16×16 | `h-4 w-4` |
| Section / card icon | 20×20 | `h-5 w-5` |
| Empty state icon | 24×24 | `h-6 w-6` |
| Status indicator icon | 12×12 | `h-3 w-3` |

### 18.2 Common Icons by Context

| Context | Icon |
|---|---|
| Add / Create | `PlusIcon` |
| Edit | `PencilIcon` |
| Delete / Remove | `TrashIcon` |
| Close / Dismiss | `XIcon` |
| Search | `SearchIcon` |
| Filter | `FilterIcon` |
| Download | `DownloadIcon` |
| Upload | `UploadCloudIcon` |
| Refresh | `RefreshCwIcon` |
| Settings | `SettingsIcon` |
| User / Profile | `UserIcon` |
| Notification | `BellIcon` |
| Check / Success | `CheckIcon`, `CheckCircle2Icon` |
| Warning | `AlertTriangleIcon` |
| Error / Info | `AlertCircleIcon`, `InfoIcon` |
| Chevron right | `ChevronRightIcon` |
| Chevron down | `ChevronDownIcon` |
| External link | `ExternalLinkIcon` |
| More actions | `MoreHorizontalIcon` |
| Lock | `LockIcon` |
| File / Document | `FileTextIcon`, `FileIcon` |
| Email | `MailIcon` |
| Calendar | `CalendarIcon` |
| Map pin | `MapPinIcon` |
| Building | `BuildingIcon` |
| Layers / Stack | `LayersIcon` |

### 18.3 Rules

- All SVG icons must include `aria-hidden` when decorative
- Icons in buttons have `pointer-events-none shrink-0` applied automatically by the button component
- Never use emoji as UI icons in enterprise screens
- Never use icon fonts (Font Awesome, Material Icons) — Lucide only

---

## 19. Responsive Rules

### 19.1 Breakpoint Scale

| Name | CSS Var | Threshold | Use case |
|---|---|---|---|
| `xs` | `--breakpoint-xs` | 480px | Small phones |
| `sm` | `--breakpoint-sm` | 640px | Phones, small tablets |
| `md` | `--breakpoint-md` | 768px | Tablets, landscape phones |
| `lg` | `--breakpoint-lg` | 1024px | Laptops, iPads |
| `xl` | `--breakpoint-xl` | 1280px | Desktop |
| `2xl` | `--breakpoint-2xl` | 1536px | Large monitors |
| `3xl` | `--breakpoint-3xl` | 1792px | Ultra-wide |

### 19.2 Layout Breakpoints

| Element | Mobile | Desktop |
|---|---|---|
| Header height | `h-14` (56px) | `h-16` (64px) |
| Header padding | `px-4` | `px-6 md:px-8` |
| Page gutter | `p-6` | `lg:p-8` |
| Sidebar | Hidden (Sheet overlay) | Fixed left `16rem` |
| Card columns | `grid-cols-1` | `sm:grid-cols-2 lg:grid-cols-3` |
| Form columns | `grid-cols-1` | `sm:grid-cols-2` |
| Table | Horizontal scroll | Full width |
| Modals | Full-width - 2rem | Max `640px` centered |
| Side drawers | `w-3/4` | `sm:max-w-sm` |

### 19.3 Mobile-First Rules

- Always write mobile styles first, override with `sm:`, `md:`, `lg:` prefixes
- Sidebar collapses to a Sheet overlay on mobile
- Use `flex-col sm:flex-row` for action bars
- Prefer `gap-*` for spacing between elements (not margin)
- Form grids: always `grid-cols-1 sm:grid-cols-2`
- Never hide critical information on mobile — only de-emphasize

### 19.4 Field Engineer / Mobile UX

For `/sites/[id]`, `/ticketing`, and field-facing pages:

- Touch targets: **minimum 44×44px** (use `min-h-[44px]`)
- Form inputs: `text-base` (16px, prevents iOS zoom)
- Prefer large buttons with icons: `h-12 text-base`
- Camera/file upload buttons must be full-width on mobile
- Offline support messaging: show badge when `navigator.onLine === false`

---

## 20. Accessibility

### 20.1 Focus Management

All interactive elements use:
```
focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50
```

- **Never** remove focus outlines (`outline-none` is paired with `focus-visible:` utilities, not bare)
- Focus ring color matches `--ring` (primary blue)
- All modals/drawers trap focus and return focus on close
- Keyboard shortcut: `Ctrl+K` / `Cmd+K` opens the global command palette

### 20.2 ARIA Requirements

| Element | Required ARIA |
|---|---|
| Icon-only buttons | `aria-label="Description"` |
| Loading states | `aria-busy="true"` |
| Invalid inputs | `aria-invalid="true"` + `aria-describedby` pointing to error message |
| Notification bell | `aria-label="Notifications, 3 unread"` |
| Expanded/collapsed sections | `aria-expanded="true/false"` |
| Modal | `role="dialog"` + `aria-modal="true"` (handled by Base UI) |
| Status indicators | `aria-label="Online"` on decorative dots |
| Tables | `scope="col"` on `<th>` elements |

### 20.3 Color Contrast

All text must meet **WCAG AA** minimum contrast ratios:
- Normal text (< 18px): **4.5:1**
- Large text (≥ 18px or ≥ 14px bold): **3:1**
- UI components and focus indicators: **3:1**

Never convey status through color alone — always pair with an icon or text label.

### 20.4 Screen Reader Patterns

```tsx
// Visually hidden (screen reader only)
<span className="sr-only">Close dialog</span>

// Loading state
<div role="status" aria-live="polite">
  {isLoading ? <span className="sr-only">Loading…</span> : null}
</div>

// Error message linked to input
<div className="space-y-1.5">
  <Label htmlFor="email">Email</Label>
  <Input
    id="email"
    aria-invalid={!!error}
    aria-describedby={error ? "email-error" : undefined}
  />
  {error ? (
    <p id="email-error" className="text-xs text-destructive" role="alert">
      {error}
    </p>
  ) : null}
</div>
```

### 20.5 Semantic HTML Rules

- Use `<nav>` for sidebar and breadcrumbs
- Use `<header>` for the app header
- Use `<main>` for page content
- Use `<h1>` once per page (the page title)
- Use `<h2>` for section titles, `<h3>` for card titles
- Use `<button>` for actions, `<a>` for navigation — never swap them
- Use `<table>` for tabular data, never for layout

---

## Appendix A: Component Quick Reference

| Need | Component / Pattern |
|---|---|
| Page header + action | `<h1>` + `<Button>` in flex container |
| Data list | Card → Table → PaginatedListFooter |
| Record detail | SheetContent (side drawer) |
| Confirm action | DialogContent (centered modal) |
| Status display | StatusBadge with `variant="outline"` |
| Inline alert | Colored `<div>` with icon (see §17.3) |
| Loading row | `<div className="animate-pulse rounded-md bg-muted h-10">` |
| Empty state | Centered icon + text in card |
| Form field | Label + Input/Select/Textarea in `space-y-1.5` |
| Form error | `<p className="text-xs text-destructive">` |
| Metric/KPI | `text-3xl font-semibold` in a card |
| Filter bar | Flex row: search Input + Select + Button groups |
| Module badge count | `ml-auto` span `rounded-full bg-primary text-primary-foreground` |

---

## Appendix B: CSS Custom Properties Reference

```css
/* Spacing / Layout */
--shell-header-h: 3.5rem;
--shell-content-max-w: 120rem;
--sidebar-default-w: 16rem;
--sidebar-collapsed-w: 4.5rem;
--card-padding-sm: 0.75rem;
--card-padding-md: 1rem;

/* Radius */
--radius: 0.75rem;
--radius-card: 0.75rem;
--radius-interactive: 0.5rem;

/* Shadows */
--elevation-surface: 0 1px 2px 0 rgb(15 23 42 / 0.04);
--elevation-card: 0 4px 14px -8px rgb(15 23 42 / 0.12);
--elevation-overlay: 0 24px 48px -16px rgb(15 23 42 / 0.35);

/* Z-index */
--layer-sticky: 20;
--layer-dropdown: 30;
--layer-drawer: 40;
--layer-modal: 50;
--layer-toast: 60;

/* Status */
--success: #16a34a;
--warning: #d97706;
--danger: #dc2626;
--info: #0ea5e9;

/* Brand */
--brand-500: #3b82f6;
--brand-600: #2563eb;
```

---

## Appendix C: Anti-Patterns (Do NOT Do)

| Anti-pattern | Correct approach |
|---|---|
| Hardcoded colors (`text-[#2563eb]`) | Use semantic tokens (`text-primary`) |
| Nested grids deeper than 2 levels | Flatten layout |
| Multiple `<h1>` tags on one page | One `<h1>` per page |
| Alerts at top of form | Inline field-level errors |
| `font-bold` or heavier in data UI | Use `font-medium` or `font-semibold` only |
| `z-index: 9999` inline | Use `--layer-*` tokens |
| Icon-only buttons without `aria-label` | Always label interactive icons |
| `onClick` on `<div>` or `<span>` | Use `<button>` |
| Removing focus ring (`outline-none` alone) | Pair with `focus-visible:` |
| Status conveyed by color alone | Add icon or text label |
| Deep modal stack (modal inside modal) | Use sheets or multi-step flows |
| `margin-top` for spacing between siblings | Use `gap-*` or `space-y-*` on parent |
| Importing from multiple icon libraries | Lucide only |
| `tailwind.config.js` additions | Extend via `tokens.css` `@theme` block |

---

*Last updated: June 2026 — TowerOS Design System v1.0.0*
