"use client";

import Link from "next/link";
import { useState } from "react";

import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { RegistryListToolbar } from "@/components/registry/registry-list-toolbar";
import { fiberRoutesTableColumns } from "@/components/registry/fiber-routes-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { useFiberOneRoutesIndex } from "@/hooks/use-fiber-one-routes-index";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "name:asc";

export function FiberRoutesRegistryPageClient() {
  const [search, setSearch] = useState("");
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["name", "length_km", "status"],
  });
  const { setPage, query } = useFiberOneRoutesIndex(search, sort);
  const { data, isFetching, isError } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.fiberOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Fiber routes</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Logical transport paths between sites. Full OTDR and splice modeling follows in later phases.
            </p>
          </div>
          <div className="flex flex-wrap gap-3 text-sm font-medium">
            <Link className="text-primary underline-offset-4 hover:underline" href="/fiber-one">
              Dashboard
            </Link>
            <Link className="text-primary underline-offset-4 hover:underline" href="/sites">
              Sites
            </Link>
          </div>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryListToolbar label="Filter" value={search} onChange={setSearch} placeholder="Name, status" />
          <RegistryDataTableView
            columns={fiberRoutesTableColumns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={isFetching && rows.length === 0}
            isEmpty={!isFetching && rows.length === 0}
            emptyMessage="No routes match this filter."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.fiber-one.routes"
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
          />
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        {isError ? <p className="text-sm text-destructive">Could not load fiber routes.</p> : null}
      </div>
    </PermissionGate>
  );
}
