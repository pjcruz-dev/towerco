"use client";

import Link from "next/link";
import { useState } from "react";

import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { sitesTableColumns } from "@/components/registry/sites-table-columns";
import { RegistryListToolbar } from "@/components/registry/registry-list-toolbar";
import { PermissionGate } from "@/components/layout/permission-gate";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { useSitesIndex } from "@/hooks/use-sites-index";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "site_code:asc";

export function SitesRegistryPageClient() {
  const [search, setSearch] = useState("");
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["site_code", "name", "type", "status"],
  });
  const { setPage, query } = useSitesIndex(search, sort);
  const { data, isFetching, isError } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.sitesView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Site registry</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Canonical sites used by PROJECT-ONE, TOWER-ONE, and FIBER-ONE. Data is tenant-scoped and sourced from the
              live API.
            </p>
          </div>
          <Link className="text-sm font-medium text-primary underline-offset-4 hover:underline" href="/dashboard">
            Back to dashboard
          </Link>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryListToolbar label="Filter" value={search} onChange={setSearch} placeholder="Code, name, type, status" />
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
            manualSorting={manualSorting}
          />
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        {isError ? (
          <p className="text-sm text-destructive">Could not load sites. Confirm you have the sites:view permission.</p>
        ) : null}
      </div>
    </PermissionGate>
  );
}
