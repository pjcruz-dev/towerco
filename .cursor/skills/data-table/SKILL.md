---
name: data-table
description: Build or modify TowerOS data tables and list screens (server-side paging/sorting/filtering, bulk actions, export, column views, URL state). Use whenever the user mentions a table, grid, list page, listing, filters, bulk actions, export, or large datasets, even if they do not say "data table".
---

# Data table pattern (TowerOS)

TanStack Table v8 is installed. The shared building blocks are in `frontend/components/ui`: `data-table.tsx` with `data-table-column-header.tsx`, `data-table-column-helpers.tsx`, `data-table-row-selection.tsx`, `data-table-view-options.tsx`; also `app-table.tsx`, `row-actions-menu.tsx`, `data-list-card.tsx` (small screens), `page-skeletons.tsx`, `refreshing-hint.tsx`. Supporting code: `lib/table/server-sort.ts`, `lib/api/paginated.ts`, `lib/api/module-list-export-params.ts`, `lib/api/modules/module-list-exports-api.ts`.

Read these and one finished list screen (for example under `app/(platform)/ticketing`) first. Extend the shared pieces. Never fork them per module. Check whether `app-table.tsx` is the higher-level wrapper before using `data-table.tsx` directly.

## Contract
- Server-side paging, sorting and filtering. Client-side only for small static lists.
- Sorting via `lib/table/server-sort.ts`. Responses typed with `lib/api/paginated.ts`.
- Export uses the existing list-export mechanism (`module-list-export-params.ts` + `module-list-exports-api.ts`) with the current filters. Do not build a second exporter.
- State in the URL through Next's `useSearchParams`/router via one shared helper (no URL-state library is installed): `q`, `sort`, `page`, `filter[...]`, `selected`. Restoring a URL restores the table.
- Debounce search 250ms and cancel in-flight queries on filter change (TanStack Query).

## Features checklist
Sticky header | column show/hide (`data-table-view-options`) persisted per user | density | active-filter chips | bulk select with sticky action bar and "select all N results" | row click opens a `sheet.tsx` drawer | virtualization above ~200 rendered rows | right-aligned `tabular-nums` numbers | status chips with icon + text | keyboard navigation | two empty states (no data vs no results) | `page-skeletons` while loading and `refreshing-hint` on refetch | error with Retry | `data-list-card` layout on small screens.

## Realtime
New rows go behind a "N new items" pill via `lib/socket`. Never shift rows under the cursor.

## Verify
Try large data on the backend, throttled network, keyboard-only, dark and light, 390px width.
