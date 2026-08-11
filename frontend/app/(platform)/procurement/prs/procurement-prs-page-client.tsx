"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus, RefreshCw } from "lucide-react";

import { FilterSelect } from "@/components/forms/filter-select";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { procurementPrTableColumns } from "@/components/procurement-one/procurement-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DataListCard } from "@/components/ui/data-list-card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchProcurementPrs } from "@/lib/api/modules/procurement-one-api";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "updated_at:desc";
const COLUMN_ID_TO_API_FIELD: Record<string, string> = { document: "document_no" };

export function ProcurementPrsPageClient() {
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

  const query = useQuery({
    queryKey: ["procurement-one", "prs", { search: debouncedSearch, status, page, sort }],
    queryFn: () =>
      fetchProcurementPrs({
        search: debouncedSearch || undefined,
        status: status || undefined,
        page,
        per_page: 25,
        sort,
      }),
  });

  const prs = query.data?.data ?? [];
  const meta = query.data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement" className="hover:text-primary">
              Procurement-One
            </Link>
          }
          title="Purchase requisitions"
          description="First-class PR records with E-Approval workflow, project linkage, and budget checks."
          actions={
            <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
              <Button size="sm" render={<Link href="/procurement/prs/new" />}>
                <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                New PR
              </Button>
            </PermissionGate>
          }
        />

        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm lg:flex-row lg:items-end">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search title, document no., department…"
            className="max-w-md"
          />
          <FilterSelect
            id="procurement-pr-status"
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
            <option value="converted">Converted</option>
            <option value="cancelled">Cancelled</option>
          </FilterSelect>
          <Button size="sm" variant="outline" type="button" onClick={() => query.refetch()} disabled={query.isFetching}>
            {query.isFetching ? <Spinner className="mr-1.5 size-4" /> : <RefreshCw className="mr-1.5 h-4 w-4" aria-hidden />}
            Refresh
          </Button>
        </div>

        <DataListCard>
          <RegistryDataTableView
            columns={procurementPrTableColumns}
            data={prs}
            getRowId={(row) => row.id}
            isLoading={query.isLoading || (query.isFetching && prs.length === 0)}
            isEmpty={!query.isLoading && prs.length === 0}
            emptyMessage="No purchase requisitions yet."
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
