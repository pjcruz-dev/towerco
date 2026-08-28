"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { Inbox } from "lucide-react";

import { EApprovalApprovalGalleryCard } from "@/components/e-approval/e-approval-approval-gallery-card";
import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalListViewToggle } from "@/components/e-approval/e-approval-list-view-toggle";
import { EApprovalBackLink, EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { createEApprovalApprovalsTableColumns } from "@/components/e-approval/e-approval-approvals-table-columns";
import { EApprovalHelpEntryActions } from "@/components/help/e-approval-help-entry-actions";
import { EApprovalTourApprovalFixtures } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { useEApprovalListView } from "@/hooks/use-e-approval-list-view";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchEApprovalApprovalsIndex } from "@/lib/api/modules/e-approval-api";
import { isEApprovalTourActive } from "@/lib/help/e-approval-tour-fixtures";
import { permissions } from "@/lib/rbac/permissions";
import { useSearchParams } from "next/navigation";

const PER_PAGE = 25;
const VIEW_STORAGE_KEY = "e-approval-approvals-view";
const DEFAULT_SORT = "created_at:desc";
const APPROVAL_COLUMN_TO_API: Record<string, string> = {
  document: "document_no",
};
const APPROVAL_API_TO_COLUMN: Record<string, string> = {
  document_no: "document",
};

export function EApprovalApprovalsPageClient() {
  const searchParams = useSearchParams();
  const [page, setPage] = useState(1);
  const [awaitingMe, setAwaitingMe] = useState(true);
  const [viewMode, setViewMode] = useEApprovalListView(VIEW_STORAGE_KEY, "gallery");
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document", "status"],
    columnIdToApiField: APPROVAL_COLUMN_TO_API,
    apiFieldToColumnId: APPROVAL_API_TO_COLUMN,
  });

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const { data, isFetching, isError } = useQuery({
    queryKey: ["e-approval", "approvals", page, awaitingMe, sort],
    queryFn: () =>
      fetchEApprovalApprovalsIndex({
        page,
        per_page: PER_PAGE,
        status: awaitingMe ? "pending" : "all",
        awaiting_me: awaitingMe,
        sort,
      }),
  });

  const rows = data?.data ?? [];
  const meta = data?.meta;
  const isEmpty = !isFetching && rows.length === 0;

  const approvalColumns = useMemo(() => createEApprovalApprovalsTableColumns(), []);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalApprove]}>
      <div className="space-y-5">
        <LiveProductTourHost />
        <EApprovalPageHeader
          title="Approval inbox"
          description={
            <>
              <EApprovalBackLink href="/e-approval">Dashboard</EApprovalBackLink>
              {" · "}One row per document. Open a submission to approve or reject.
            </>
          }
          actions={<EApprovalHelpEntryActions />}
        />

        <div data-help="ea-approvals-tabs" className="flex flex-wrap gap-2">
          <Button size="sm" variant={awaitingMe ? "default" : "outline"} onClick={() => { setAwaitingMe(true); setPage(1); }}>
            Awaiting me
          </Button>
          <Button size="sm" variant={!awaitingMe ? "default" : "outline"} onClick={() => { setAwaitingMe(false); setPage(1); }}>
            All
          </Button>
        </div>

        <EApprovalListShell
          error={isError ? <p className="px-4 py-3 text-sm text-destructive">Could not load approvals.</p> : null}
          toolbar={
            <div className="flex flex-wrap items-center justify-end gap-3">
              <EApprovalListViewToggle value={viewMode} onChange={setViewMode} ariaLabel="Approvals list view" />
            </div>
          }
          footer={
            meta ? (
              <PaginatedListFooter meta={{ ...meta, current_page: page }} onPageChange={setPage} isPending={isFetching} />
            ) : null
          }
        >
          {viewMode === "gallery" ? (
            <div data-help="ea-approvals-queue" className="p-4">
              {isFetching && rows.length === 0 ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                  {Array.from({ length: 3 }).map((_, index) => (
                    <div key={index} className="h-36 animate-pulse rounded-xl border border-border bg-muted/40" />
                  ))}
                </div>
              ) : isEmpty ? (
                isEApprovalTourActive(searchParams) ? (
                  <EApprovalTourApprovalFixtures />
                ) : (
                  <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 px-6 py-14 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                      <Inbox className="h-6 w-6" />
                    </div>
                    <h2 className="mt-4 text-base font-medium">
                      {awaitingMe ? "Nothing awaiting you" : "No approvals found"}
                    </h2>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                      {awaitingMe
                        ? "When a submission needs your sign-off, it will appear here."
                        : "Try the Awaiting me filter for your active queue."}
                    </p>
                  </div>
                )
              ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                  {rows.map((row) => (
                    <EApprovalApprovalGalleryCard key={row.submission?.id ?? row.id} row={row} />
                  ))}
                </div>
              )}
            </div>
          ) : (
            <div data-help="ea-approvals-queue">
              <RegistryDataTableView
                columns={approvalColumns}
                data={rows}
                getRowId={(row) => row.submission?.id ?? row.id}
                isLoading={isFetching && rows.length === 0}
                isEmpty={isEmpty}
                emptyMessage={awaitingMe ? "No approvals awaiting you." : "No approvals found."}
                enableColumnVisibility
                columnVisibilityStorageKey="toweros.table.columns.e-approval.approvals"
                sorting={sorting}
                onSortingChange={onSortingChange}
                manualSorting={manualSorting}
              />
            </div>
          )}
        </EApprovalListShell>
      </div>
    </PermissionGate>
  );
}
