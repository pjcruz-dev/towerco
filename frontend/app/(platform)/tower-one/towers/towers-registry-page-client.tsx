"use client";

import Link from "next/link";
import { useState } from "react";

import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { RegistryListToolbar } from "@/components/registry/registry-list-toolbar";
import { towersTableColumns } from "@/components/registry/towers-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { useTowerOneTowersIndex } from "@/hooks/use-tower-one-towers-index";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "updated_at:desc";

export function TowersRegistryPageClient() {
  const [search, setSearch] = useState("");
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["tower_type", "height_m", "capacity_kg", "max_tenants", "status"],
  });
  const { setPage, query } = useTowerOneTowersIndex(search, sort);
  const { data, isFetching, isError } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.towerOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Towers</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Structural inventory linked to sites. Paginated list from TOWER-ONE.
            </p>
          </div>
          <div className="flex flex-wrap gap-3 text-sm font-medium">
            <Link className="text-primary underline-offset-4 hover:underline" href="/tower-one">
              Dashboard
            </Link>
            <Link className="text-primary underline-offset-4 hover:underline" href="/sites">
              Sites
            </Link>
          </div>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryListToolbar label="Filter" value={search} onChange={setSearch} placeholder="Type, status, site" />
          <RegistryDataTableView
            columns={towersTableColumns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={isFetching && rows.length === 0}
            isEmpty={!isFetching && rows.length === 0}
            emptyMessage="No towers match this filter."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.tower-one.towers"
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
          />
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        {isError ? <p className="text-sm text-destructive">Could not load towers.</p> : null}
      </div>
    </PermissionGate>
  );
}
