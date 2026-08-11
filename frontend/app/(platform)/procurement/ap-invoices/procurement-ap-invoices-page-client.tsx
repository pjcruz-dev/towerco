"use client";

import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Download, FileSpreadsheet, RefreshCw } from "lucide-react";

import { FilterSelect } from "@/components/forms/filter-select";
import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { createProcurementApInvoicesTableColumns } from "@/components/procurement-one/procurement-table-columns";
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
import {
  fetchProcurementApAging,
  fetchProcurementApInvoices,
  procurementApGlExportUrl,
} from "@/lib/api/modules/procurement-one-api";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "updated_at:desc";
const COLUMN_ID_TO_API_FIELD: Record<string, string> = { document: "document_no" };

export function ProcurementApInvoicesPageClient() {
  const financePaths = useFinanceModulePaths();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document", "status"],
    columnIdToApiField: COLUMN_ID_TO_API_FIELD,
  });

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const columns = useMemo(() => createProcurementApInvoicesTableColumns(financePaths), [financePaths]);

  const query = useQuery({
    queryKey: ["procurement-one", "ap-invoices", { search: debouncedSearch, status, page, sort }],
    queryFn: () =>
      fetchProcurementApInvoices({
        search: debouncedSearch || undefined,
        status: status || undefined,
        page,
        per_page: 25,
        sort,
      }),
  });

  const agingQuery = useQuery({
    queryKey: ["procurement-one", "ap-aging"],
    queryFn: fetchProcurementApAging,
  });

  const invoices = query.data?.data ?? [];
  const meta = query.data?.meta;
  const aging = agingQuery.data;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
          }
          title="AP invoices"
          description="Match supplier invoices to purchase orders and goods receipts, approve for payment, and export to finance."
          actions={
            <div className="flex gap-2">
              <Button
                size="sm"
                variant="outline"
                render={<a href={procurementApGlExportUrl()} download />}
              >
                <Download className="mr-1.5 h-4 w-4" aria-hidden />
                GL export
              </Button>
              <Button size="sm" variant="outline" type="button" onClick={() => query.refetch()} disabled={query.isFetching}>
                {query.isFetching ? <Spinner className="mr-1.5 size-4" /> : <RefreshCw className="mr-1.5 h-4 w-4" aria-hidden />}
                Refresh
              </Button>
            </div>
          }
        />

        {aging ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-center gap-2 text-sm font-medium text-foreground">
              <FileSpreadsheet className="h-4 w-4 text-primary" aria-hidden />
              AP aging — open balance {aging.total_open.toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              {aging.buckets.map((bucket) => (
                <div key={bucket.key} className="rounded-lg border border-border px-3 py-2">
                  <p className="text-xs text-muted-foreground">{bucket.label}</p>
                  <p className="mt-1 text-base font-medium text-foreground">
                    {bucket.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </p>
                  <p className="text-xs text-muted-foreground">{bucket.count} invoice(s)</p>
                </div>
              ))}
            </div>
          </section>
        ) : null}

        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm lg:flex-row lg:items-end">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search AP no., vendor invoice, PO…"
            className="max-w-md"
          />
          <FilterSelect
            id="procurement-ap-invoice-status"
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
            <option value="pending_approval">Pending approval</option>
            <option value="approved">Approved</option>
          </FilterSelect>
        </div>

        <DataListCard>
          <RegistryDataTableView
            columns={columns}
            data={invoices}
            getRowId={(row) => row.id}
            isLoading={query.isLoading || (query.isFetching && invoices.length === 0)}
            isEmpty={!query.isLoading && invoices.length === 0}
            emptyMessage="No AP invoices yet. Create one from an approved PO with a posted GRN."
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
