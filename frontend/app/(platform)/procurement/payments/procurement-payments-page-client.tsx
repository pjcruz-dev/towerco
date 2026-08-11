"use client";

import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { RefreshCw } from "lucide-react";

import { FilterSelect } from "@/components/forms/filter-select";
import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import {
  createProcurementPaymentBatchesTableColumns,
  createProcurementPaymentRequestsTableColumns,
} from "@/components/procurement-one/procurement-table-columns";
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
  fetchProcurementPaymentBatches,
  fetchProcurementPaymentRequests,
  markProcurementPaymentBatchExported,
  markProcurementPaymentBatchReconciled,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const DEFAULT_SORT = "updated_at:desc";
const COLUMN_ID_TO_API_FIELD: Record<string, string> = { document: "document_no" };

export function ProcurementPaymentsPageClient() {
  const financePaths = useFinanceModulePaths();
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);
  const [tab, setTab] = useState<"requests" | "batches">("requests");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));

  const requestsSort = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document", "status"],
    columnIdToApiField: COLUMN_ID_TO_API_FIELD,
  });
  const batchesSort = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document", "status"],
    columnIdToApiField: COLUMN_ID_TO_API_FIELD,
  });

  useEffect(() => {
    setPage(1);
  }, [requestsSort.sort, batchesSort.sort]);

  const requestsQuery = useQuery({
    queryKey: ["procurement-one", "payment-requests", { search: debouncedSearch, status, page, sort: requestsSort.sort }],
    queryFn: () =>
      fetchProcurementPaymentRequests({
        search: debouncedSearch || undefined,
        status: status || undefined,
        page,
        per_page: 25,
        sort: requestsSort.sort,
      }),
    enabled: tab === "requests",
  });

  const batchesQuery = useQuery({
    queryKey: ["procurement-one", "payment-batches", { status, page, sort: batchesSort.sort }],
    queryFn: () =>
      fetchProcurementPaymentBatches({
        status: status || undefined,
        page,
        per_page: 25,
        sort: batchesSort.sort,
      }),
    enabled: tab === "batches",
  });

  const batchExportMutation = useMutation({
    mutationFn: (id: string) => markProcurementPaymentBatchExported(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "payment-batches"] });
      pushNotification({ title: "Batch marked exported", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const batchReconcileMutation = useMutation({
    mutationFn: (id: string) => markProcurementPaymentBatchReconciled(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "payment-batches"] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "payment-requests"] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "ap-aging"] });
      pushNotification({ title: "Batch reconciled", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const requestColumns = useMemo(
    () => createProcurementPaymentRequestsTableColumns(financePaths),
    [financePaths],
  );

  const batchColumns = useMemo(
    () =>
      createProcurementPaymentBatchesTableColumns({
        batchExportPending: batchExportMutation.isPending,
        batchReconcilePending: batchReconcileMutation.isPending,
        onMarkExported: (id) => batchExportMutation.mutate(id),
        onReconcile: (id) => batchReconcileMutation.mutate(id),
      }),
    [batchExportMutation, batchReconcileMutation],
  );

  const activeQuery = tab === "requests" ? requestsQuery : batchesQuery;
  const requests = requestsQuery.data?.data ?? [];
  const batches = batchesQuery.data?.data ?? [];
  const meta = activeQuery.data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
          }
          title="Payment tracking"
          description="Request, approve, and schedule vendor payments. Export batches for finance — track paid and reconciled status without executing bank transfers."
          actions={
            <Button size="sm" variant="outline" type="button" onClick={() => activeQuery.refetch()} disabled={activeQuery.isFetching}>
              {activeQuery.isFetching ? <Spinner className="mr-1.5 size-4" /> : <RefreshCw className="mr-1.5 h-4 w-4" aria-hidden />}
              Refresh
            </Button>
          }
        />

        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant={tab === "requests" ? "default" : "outline"} onClick={() => { setTab("requests"); setPage(1); setStatus(""); }}>
            Payment requests
          </Button>
          <Button size="sm" variant={tab === "batches" ? "default" : "outline"} onClick={() => { setTab("batches"); setPage(1); setStatus(""); }}>
            Payment batches
          </Button>
        </div>

        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm lg:flex-row lg:items-end">
          {tab === "requests" ? (
            <Input
              placeholder="Search payment no., vendor, invoice…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="max-w-sm"
            />
          ) : null}
          <FilterSelect
            id="procurement-payment-status"
            label="Status"
            value={status}
            onChange={(value) => {
              setStatus(value);
              setPage(1);
            }}
            className="w-full min-w-[10rem] sm:w-auto"
          >
            <option value="">All statuses</option>
            {tab === "requests" ? (
              <>
                <option value="pending_approval">Pending approval</option>
                <option value="approved">Approved</option>
                <option value="scheduled">Scheduled</option>
                <option value="paid">Paid</option>
                <option value="reconciled">Reconciled</option>
              </>
            ) : (
              <>
                <option value="scheduled">Scheduled</option>
                <option value="exported">Exported</option>
                <option value="reconciled">Reconciled</option>
              </>
            )}
          </FilterSelect>
        </div>

        {tab === "requests" ? (
          <DataListCard>
            <RegistryDataTableView
              columns={requestColumns}
              data={requests}
              getRowId={(row) => row.id}
              isLoading={requestsQuery.isLoading || (requestsQuery.isFetching && requests.length === 0)}
              isEmpty={!requestsQuery.isLoading && requests.length === 0}
              emptyMessage="No payment requests yet. Create one from an approved AP invoice."
              enableColumnVisibility
              sorting={requestsSort.sorting}
              onSortingChange={requestsSort.onSortingChange}
              manualSorting={requestsSort.manualSorting}
            />
            {meta && meta.last_page > 1 ? (
              <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={requestsQuery.isFetching} />
            ) : null}
          </DataListCard>
        ) : (
          <DataListCard>
            <RegistryDataTableView
              columns={batchColumns}
              data={batches}
              getRowId={(row) => row.id}
              isLoading={batchesQuery.isLoading || (batchesQuery.isFetching && batches.length === 0)}
              isEmpty={!batchesQuery.isLoading && batches.length === 0}
              emptyMessage="No payment batches yet. Batch approved payment requests from the payment detail page."
              enableColumnVisibility
              sorting={batchesSort.sorting}
              onSortingChange={batchesSort.onSortingChange}
              manualSorting={batchesSort.manualSorting}
            />
            {meta && meta.last_page > 1 ? (
              <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={batchesQuery.isFetching} />
            ) : null}
          </DataListCard>
        )}
      </div>
    </PermissionGate>
  );
}
