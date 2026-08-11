"use client";

import Link from "next/link";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useCallback, useEffect, useMemo, useState } from "react";

import { createProjectOneApprovalsTableColumns } from "@/components/project-one/project-one-approvals-table-columns";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { RegistryListToolbar } from "@/components/registry/registry-list-toolbar";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { usePermission } from "@/hooks/use-permission";
import {
  type ApprovalListStatus,
  useProjectOneApprovalsIndex,
} from "@/hooks/use-project-one-approvals-index";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { getErrorMessage } from "@/lib/api/error";
import { resolveProjectApproval } from "@/lib/api/modules/project-one-api";
import { permissions } from "@/lib/rbac/permissions";
import type { ProjectOneApprovalListRow } from "@/modules/project-one/types";
import { useNotificationStore } from "@/stores/notification-store";

const tabs: { key: ApprovalListStatus; label: string }[] = [
  { key: "pending", label: "Pending" },
  { key: "approved", label: "Approved" },
  { key: "rejected", label: "Rejected" },
  { key: "all", label: "All" },
];

const DEFAULT_SORT = "submitted_at:desc";
const COLUMN_TO_API: Record<string, string> = {
  submittedAt: "submitted_at",
  resolvedAt: "resolved_at",
  type: "approval_type",
};
const API_TO_COLUMN: Record<string, string> = {
  submitted_at: "submittedAt",
  resolved_at: "resolvedAt",
  approval_type: "type",
};

export function ProjectOneApprovalsPageClient() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const canResolve = usePermission([permissions.projectOneManage]);

  const [status, setStatus] = useState<ApprovalListStatus>("pending");
  const [search, setSearch] = useState("");
  const [sheetOpen, setSheetOpen] = useState(false);
  const [active, setActive] = useState<ProjectOneApprovalListRow | null>(null);
  const [rejectNotes, setRejectNotes] = useState("");
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["status", "type", "title", "requester", "submittedAt", "resolvedAt"],
    columnIdToApiField: COLUMN_TO_API,
    apiFieldToColumnId: API_TO_COLUMN,
  });

  const { setPage, query } = useProjectOneApprovalsIndex(search, status, sort);
  const { data, isFetching, isError } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;

  useEffect(() => {
    setPage(1);
  }, [sort, setPage]);

  const resolveMutation = useMutation({
    mutationFn: ({ id, status: next, resolution_notes }: { id: string; status: "approved" | "rejected"; resolution_notes?: string }) =>
      resolveProjectApproval(id, { status: next, resolution_notes }),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "approvals"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      setSheetOpen(false);
      setActive(null);
      setRejectNotes("");
      push({
        level: "success",
        title: variables.status === "approved" ? "Approval granted" : "Approval rejected",
      });
    },
    onError: (error) => {
      push({ level: "error", title: "Could not update approval", message: getErrorMessage(error) });
    },
  });

  const openReview = useCallback((row: ProjectOneApprovalListRow) => {
    setActive(row);
    setRejectNotes("");
    setSheetOpen(true);
  }, []);

  const approvalColumns = useMemo(
    () => createProjectOneApprovalsTableColumns({ canResolve, onReview: openReview }),
    [canResolve, openReview],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Approvals</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Pending queue and decision history for Project One programs (PO, change requests, and similar).
              Timeline gate decisions live under Gate approvals.
            </p>
          </div>
          {canResolve ? (
            <Link href="/project-one/approvals/new" className={buttonVariants({ size: "sm" })}>
              New approval
            </Link>
          ) : null}
        </header>

        <div className="flex flex-wrap gap-2">
          {tabs.map((tab) => (
            <Button
              key={tab.key}
              type="button"
              size="sm"
              variant={status === tab.key ? "default" : "outline"}
              onClick={() => {
                setStatus(tab.key);
                setPage(1);
              }}
            >
              {tab.label}
            </Button>
          ))}
        </div>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryListToolbar label="Filter" value={search} onChange={setSearch} placeholder="Title, requester, type" />
          <RegistryDataTableView
            columns={approvalColumns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={isFetching && rows.length === 0}
            isEmpty={!isFetching && rows.length === 0}
            emptyMessage="No approvals in this view."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.project-one.approvals"
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
          />
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        {isError ? <p className="text-sm text-destructive">Could not load approvals.</p> : null}
      </div>

      <Sheet
        open={sheetOpen}
        onOpenChange={(open) => {
          setSheetOpen(open);
          if (!open) {
            setActive(null);
            setRejectNotes("");
          }
        }}
      >
        <SheetContent side="right" className="flex w-full flex-col sm:max-w-md">
          <SheetHeader>
            <SheetTitle>Review approval</SheetTitle>
            <SheetDescription>
              {active ? (
                <>
                  <span className="font-medium text-foreground">{active.title}</span>
                  <span className="block text-xs text-muted-foreground">
                    {active.type} · {active.requester}
                    {active.rollout?.rollout_ref ? ` · ${active.rollout.rollout_ref}` : ""}
                  </span>
                </>
              ) : null}
            </SheetDescription>
          </SheetHeader>
          <div className="flex flex-1 flex-col gap-4 px-4">
            <label className="text-xs font-medium text-muted-foreground" htmlFor="approval-notes">
              Notes (optional)
            </label>
            <textarea
              id="approval-notes"
              value={rejectNotes}
              onChange={(e) => setRejectNotes(e.target.value)}
              rows={4}
              maxLength={2000}
              className="w-full resize-y rounded-lg border border-input bg-transparent px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
            />
          </div>
          <SheetFooter className="flex-row flex-wrap gap-2 border-t border-border pt-4 sm:justify-end">
            <Button type="button" variant="outline" disabled={resolveMutation.isPending} onClick={() => setSheetOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={resolveMutation.isPending || !active}
              onClick={() => {
                if (!active) return;
                resolveMutation.mutate({
                  id: active.id,
                  status: "rejected",
                  resolution_notes: rejectNotes.trim() || undefined,
                });
              }}
            >
              Reject
            </Button>
            <Button
              type="button"
              disabled={resolveMutation.isPending || !active}
              onClick={() => {
                if (!active) return;
                resolveMutation.mutate({
                  id: active.id,
                  status: "approved",
                  resolution_notes: rejectNotes.trim() || undefined,
                });
              }}
            >
              Approve
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </PermissionGate>
  );
}
