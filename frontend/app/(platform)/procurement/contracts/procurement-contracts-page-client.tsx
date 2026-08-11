"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus, RefreshCw } from "lucide-react";

import { FilterSelect } from "@/components/forms/filter-select";
import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { createProcurementContractsTableColumns } from "@/components/procurement-one/procurement-table-columns";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DataListCard } from "@/components/ui/data-list-card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useFinanceModulePaths } from "@/hooks/use-finance-module-paths";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchProcurementContracts } from "@/lib/api/modules/procurement-one-api";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "updated_at:desc";
const COLUMN_ID_TO_API_FIELD: Record<string, string> = { document: "document_no" };

export function ProcurementContractsPageClient() {
  const financePaths = useFinanceModulePaths();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document", "title", "status"],
    columnIdToApiField: COLUMN_ID_TO_API_FIELD,
  });

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const columns = useMemo(() => createProcurementContractsTableColumns(financePaths), [financePaths]);

  const query = useQuery({
    queryKey: ["procurement-one", "contracts", { search: debouncedSearch, status, page, sort }],
    queryFn: () =>
      fetchProcurementContracts({
        search: debouncedSearch || undefined,
        status: status || undefined,
        page,
        per_page: 25,
        sort,
      }),
  });

  const contracts = query.data?.data ?? [];
  const meta = query.data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
          }
          title="Vendor contracts"
          description="Long-term vendor agreements with spend ceilings, site scope, and document repository linkage."
          actions={
            <div className="flex flex-wrap gap-2">
              <Button size="sm" variant="outline" type="button" onClick={() => query.refetch()} disabled={query.isFetching}>
                {query.isFetching ? <Spinner className="mr-1.5 size-4" /> : <RefreshCw className="mr-1.5 h-4 w-4" aria-hidden />}
                Refresh
              </Button>
              <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                <Button size="sm" render={<Link href={financePaths.contractNew} />}>
                  <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                  New contract
                </Button>
              </PermissionGate>
            </div>
          }
        />

        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm lg:flex-row lg:items-end">
          <Input placeholder="Search contract, vendor, title…" value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-sm" />
          <FilterSelect
            id="procurement-contract-status"
            label="Status"
            value={status}
            onChange={(value) => {
              setStatus(value);
              setPage(1);
            }}
            className="w-full min-w-[10rem] sm:w-auto"
          >
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="terminated">Terminated</option>
          </FilterSelect>
        </div>

        <DataListCard>
          <RegistryDataTableView
            columns={columns}
            data={contracts}
            getRowId={(row) => row.id}
            isLoading={query.isLoading || (query.isFetching && contracts.length === 0)}
            isEmpty={!query.isLoading && contracts.length === 0}
            emptyMessage="No vendor contracts yet. Create one to track spend against PO totals."
            enableColumnVisibility
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
          />
          {meta && meta.last_page > 1 ? (
            <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={query.isFetching} />
          ) : null}
        </DataListCard>
      </div>
    </PermissionGate>
  );
}
