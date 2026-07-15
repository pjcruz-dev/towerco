# RegistryDataTableView

TowerOS list/registry tables use TanStack Table via `RegistryDataTableView`. Prefer this over plain `Table` for paginated, filterable, column-toggle list pages.

## When to use

| Use `RegistryDataTableView` | Keep plain `Table` |
|-----------------------------|--------------------|
| Paginated registries (Sites, Tickets, Submissions, …) | Expand/collapse timelines |
| Read-only nested lists on detail pages | Inline-edit grids (permits, print layout, helper center) |
| Lists with server sort / column visibility | Complex map+form tabs (SAQ) |
| | Demo/connectivity mocks until API-backed |

Source: `frontend/components/registry/registry-data-table-view.tsx`

## Minimal example

```tsx
<RegistryDataTableView
  columns={sitesTableColumns}
  data={rows}
  getRowId={(row) => row.id}
  isLoading={isFetching && rows.length === 0}
  isEmpty={!isFetching && rows.length === 0}
  emptyMessage="No sites match this filter."
  enableColumnVisibility
  columnVisibilityStorageKey="toweros.table.columns.sites.registry"
  sorting={sorting}
  onSortingChange={onSortingChange}
  manualSorting
/>
```

Wire pagination with `PaginatedListFooter` below the table.

## Column helpers

Prefer shared factories in `frontend/components/ui/data-table-column-helpers.tsx`:

- `createTextColumn` / `createLinkColumn` / `createDateColumn` / `createActionsColumn`
- `enableSorting: true` renders `DataTableColumnHeader`
- Actions columns set `enableHiding: false`

Domain badges/status chips stay as custom cell content.

## Server sort

1. Backend: allowlist with `App\Core\Support\AllowlistedSort` (`field:dir`).
2. Frontend: `useServerTableSort` from `frontend/hooks/use-server-table-sort.ts`.
3. Pass `sort` in API query + React Query key; map column ids ↔ API fields when they differ.
4. Keep `manualSorting` (default `true`). Do **not** client-sort paginated registries.

## Client sort (small catalogs only)

For non-paginated lists (roles, operators, form pickers), set `manualSorting={false}`. Use `createTextColumn(..., { enableSorting: true, sortValue })` when the cell is a React node.

## Column visibility

- `enableColumnVisibility` — shows the Columns popover.
- `columnVisibilityStorageKey` — persists visibility for **uncontrolled** tables (ignored if you pass controlled `columnVisibility` / `onColumnVisibilityChange`).
- Key convention: `toweros.table.columns.<module>.<page>`

## Related UI

- Design system §4 (tables): `docs/design-system/toweros-design-system.md`
- Styling utilities: `RegistryTableScroll` / registry table classes
- Row selection: `createRowSelectionColumn` + `rowSelection` props
