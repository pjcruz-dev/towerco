"use client";

import Link from "next/link";
import { useState } from "react";

import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { RegistryListToolbar } from "@/components/registry/registry-list-toolbar";
import { assetsTableColumns } from "@/components/registry/assets-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { useAssetOneAssetsIndex } from "@/hooks/use-asset-one-assets-index";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "asset_code:asc";

export function AssetsRegistryPageClient() {
  const [search, setSearch] = useState("");
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["asset_code", "name", "category", "status", "warranty_expiry"],
  });
  const { setPage, query } = useAssetOneAssetsIndex(search, sort);
  const { data, isFetching, isError } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.assetOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Assets</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Serialized inventory with category, location hints, and warranty metadata.
            </p>
          </div>
          <Link className="text-sm font-medium text-primary underline-offset-4 hover:underline" href="/asset-one">
            Dashboard
          </Link>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryListToolbar
            label="Filter"
            value={search}
            onChange={setSearch}
            placeholder="Code, name, category, RFID"
          />
          <RegistryDataTableView
            columns={assetsTableColumns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={isFetching && rows.length === 0}
            isEmpty={!isFetching && rows.length === 0}
            emptyMessage="No assets match this filter."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.asset-one.assets"
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
          />
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        {isError ? <p className="text-sm text-destructive">Could not load assets.</p> : null}
      </div>
    </PermissionGate>
  );
}
