"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus, RefreshCw } from "lucide-react";

import { FilterSelect } from "@/components/forms/filter-select";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { procurementVendorTableColumns } from "@/components/procurement-one/procurement-table-columns";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DataListCard } from "@/components/ui/data-list-card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchProcurementVendorFormSchema, fetchProcurementVendors } from "@/lib/api/modules/procurement-one-api";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "company_name:asc";
const COLUMN_ID_TO_API_FIELD: Record<string, string> = {
  vendor: "company_name",
  accreditation: "accreditation_status",
};
const API_FIELD_TO_COLUMN_ID: Record<string, string> = {
  company_name: "vendor",
  accreditation_status: "accreditation",
};

export function ProcurementVendorsPageClient() {
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["vendor", "vendor_code", "accreditation"],
    columnIdToApiField: COLUMN_ID_TO_API_FIELD,
    apiFieldToColumnId: API_FIELD_TO_COLUMN_ID,
  });

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const query = useQuery({
    queryKey: ["procurement-one", "vendors", { search: debouncedSearch, status, page, sort }],
    queryFn: () =>
      fetchProcurementVendors({
        search: debouncedSearch || undefined,
        status: status || undefined,
        page,
        per_page: 25,
        sort,
      }),
  });

  const formSchemaQuery = useQuery({
    queryKey: ["procurement-one", "vendors", "form-schema"],
    queryFn: () => fetchProcurementVendorFormSchema(),
    staleTime: 60_000,
  });

  const vendors = query.data?.data ?? [];
  const meta = query.data?.meta;
  const canRegisterVendor = !!formSchemaQuery.data?.form?.id;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneVendorsView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement" className="hover:text-primary">
              Procurement-One
            </Link>
          }
          title="Vendors"
          description="Accredited supplier registry for purchase orders, contracts, and vendor performance follow-ups."
          actions={
            <div className="flex flex-wrap gap-2">
              <PermissionGate requiredPermissions={[permissions.procurementOneVendorsManage]}>
                {canRegisterVendor ? (
                  <Button size="sm" render={<Link href="/procurement/vendors/new" />}>
                    <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                    Register vendor
                  </Button>
                ) : null}
              </PermissionGate>
              <Button size="sm" variant="outline" type="button" onClick={() => query.refetch()} disabled={query.isFetching}>
                {query.isFetching ? <Spinner className="mr-1.5 size-4" /> : <RefreshCw className="mr-1.5 h-4 w-4" aria-hidden />}
                Refresh
              </Button>
            </div>
          }
        />

        {!formSchemaQuery.isLoading && !canRegisterVendor ? (
          <OperationalAlert
            level="info"
            title="Vendor registration form not ready"
            description="Publish your vendor registration form in E-Approval to enable Register vendor from this page."
            actions={
              <Button size="sm" variant="outline" render={<Link href="/e-approval/forms" />}>
                Open E-Approval forms
              </Button>
            }
          />
        ) : null}

        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm lg:flex-row lg:items-end">
          <Input placeholder="Search vendor code, company, tax ID…" value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-sm" />
          <FilterSelect
            id="procurement-vendor-status"
            label="Status"
            value={status}
            onChange={(value) => {
              setStatus(value);
              setPage(1);
            }}
            className="w-full min-w-[10rem] sm:w-auto"
          >
            <option value="">All vendors</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="accredited">Accredited</option>
            <option value="pending">Pending accreditation</option>
          </FilterSelect>
        </div>

        <DataListCard>
          <RegistryDataTableView
            columns={procurementVendorTableColumns}
            data={vendors}
            getRowId={(row) => row.id}
            isLoading={query.isLoading || (query.isFetching && vendors.length === 0)}
            isEmpty={!query.isLoading && vendors.length === 0}
            emptyMessage="No vendors found."
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
