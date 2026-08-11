"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { ScrollText } from "lucide-react";

import { EApprovalAuditGalleryCard } from "@/components/e-approval/e-approval-audit-gallery-card";
import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalListViewToggle } from "@/components/e-approval/e-approval-list-view-toggle";
import { EApprovalBackLink, EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { eApprovalAuditTableColumns } from "@/components/e-approval/e-approval-audit-table-columns";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Input } from "@/components/ui/input";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useEApprovalListView } from "@/hooks/use-e-approval-list-view";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchEApprovalAuditIndex } from "@/lib/api/modules/e-approval-api";
import { permissions } from "@/lib/rbac/permissions";

const PER_PAGE = 50;
const VIEW_STORAGE_KEY = "e-approval-audit-view";
const DEFAULT_SORT = "created_at:desc";

export function EApprovalAuditPageClient() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [viewMode, setViewMode] = useEApprovalListView(VIEW_STORAGE_KEY, "table");
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["created_at", "action", "target_id"],
  });

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const { data, isFetching, isError } = useQuery({
    queryKey: ["e-approval", "audit", page, debouncedSearch, sort],
    queryFn: () =>
      fetchEApprovalAuditIndex({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch.trim() || undefined,
        sort,
      }),
  });

  const rows = data?.data ?? [];
  const meta = data?.meta;
  const isEmpty = !isFetching && rows.length === 0;

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalAuditView]}>
      <div className="space-y-5">
        <EApprovalPageHeader
          title="Audit log"
          description={
            <>
              <EApprovalBackLink href="/e-approval">Dashboard</EApprovalBackLink>
              {" · "}Compliance trail for forms, submissions, and approvals.
            </>
          }
        />

        <EApprovalListShell
          error={isError ? <p className="px-4 py-3 text-sm text-destructive">Could not load audit log.</p> : null}
          toolbar={
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div className="min-w-0 flex-1">
                <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="e-approval-audit-search">
                  Search audit log
                </label>
                <Input
                  id="e-approval-audit-search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Action, user, target"
                  className="h-11 w-full text-base sm:h-9 sm:max-w-md sm:text-sm"
                />
              </div>
              <EApprovalListViewToggle value={viewMode} onChange={setViewMode} ariaLabel="Audit log view" />
            </div>
          }
          footer={
            meta ? (
              <PaginatedListFooter meta={{ ...meta, current_page: page }} onPageChange={setPage} isPending={isFetching} />
            ) : null
          }
        >
          {viewMode === "gallery" ? (
            <div className="p-4">
              {isFetching && rows.length === 0 ? (
                <div className="grid gap-4 sm:grid-cols-2">
                  {Array.from({ length: 4 }).map((_, index) => (
                    <div key={index} className="h-32 animate-pulse rounded-xl border border-border bg-muted/40" />
                  ))}
                </div>
              ) : isEmpty ? (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 px-6 py-14 text-center">
                  <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <ScrollText className="h-6 w-6" />
                  </div>
                  <h2 className="mt-4 text-base font-medium">No audit events</h2>
                  <p className="mt-1 text-sm text-muted-foreground">Try a different search term.</p>
                </div>
              ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                  {rows.map((row) => (
                    <EApprovalAuditGalleryCard key={row.id} row={row} />
                  ))}
                </div>
              )}
            </div>
          ) : (
            <RegistryDataTableView
              columns={eApprovalAuditTableColumns}
              data={rows}
              getRowId={(row) => row.id}
              isLoading={isFetching && rows.length === 0}
              isEmpty={isEmpty}
              emptyMessage="No audit events match this search."
              enableColumnVisibility
              columnVisibilityStorageKey="toweros.table.columns.e-approval.audit"
              sorting={sorting}
              onSortingChange={onSortingChange}
              manualSorting={manualSorting}
            />
          )}
        </EApprovalListShell>
      </div>
    </PermissionGate>
  );
}
