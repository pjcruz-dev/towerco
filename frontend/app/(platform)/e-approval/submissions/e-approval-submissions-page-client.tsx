"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { FileStack, Plus } from "lucide-react";

import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalListViewToggle } from "@/components/e-approval/e-approval-list-view-toggle";
import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSubmissionGalleryCard } from "@/components/e-approval/e-approval-submission-gallery-card";
import { eApprovalSubmissionTableColumns } from "@/components/e-approval/e-approval-submission-table-columns";
import { EApprovalHelpEntryActions } from "@/components/help/e-approval-help-entry-actions";
import { EApprovalTourSubmissionFixtures, EApprovalTourSubmissionTableFixtures } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { formatEApprovalStatusLabel } from "@/modules/e-approval/status-display";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useEApprovalListView } from "@/hooks/use-e-approval-list-view";
import { usePermission } from "@/hooks/use-permission";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { downloadEApprovalSubmissionsExport, fetchEApprovalSubmissionsIndex } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { isEApprovalTourActive } from "@/lib/help/e-approval-tour-fixtures";
import {
  LIVE_TOUR_QUERY,
  LIVE_TOUR_STEP_QUERY,
  resolveLiveTour,
} from "@/lib/help/e-approval-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { User } from "lucide-react";

const PER_PAGE = 25;
const VIEW_STORAGE_KEY = "e-approval-submissions-view";
const DEFAULT_SORT = "created_at:desc";

const STATUS_FILTERS = ["all", "returned", "pending", "approved", "rejected", "cancelled"] as const;

function formatSubmissionFilterLabel(status: (typeof STATUS_FILTERS)[number]): string {
  if (status === "returned") {
    return "Needs revision";
  }
  return formatEApprovalStatusLabel(status);
}

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

export function EApprovalSubmissionsPageClient() {
  const searchParams = useSearchParams();
  const push = useNotificationStore((s) => s.push);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canExport = usePermission([permissions.eApprovalAuditView]);
  // Auditors can see all submissions; show the "Mine" scope toggle for them.
  const canViewAll = usePermission([permissions.eApprovalAuditView]);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState(() => {
    const initial = searchParams.get("status");
    return initial && STATUS_FILTERS.includes(initial as (typeof STATUS_FILTERS)[number])
      ? initial
      : "all";
  });
  const [formId, setFormId] = useState(() => searchParams.get("form_id") ?? "");
  const [from, setFrom] = useState(() => searchParams.get("from") ?? "");
  const [to, setTo] = useState(() => searchParams.get("to") ?? "");
  const [mineOnly, setMineOnly] = useState(() => {
    const mine = searchParams.get("mine");
    return mine === "1" || mine === "true";
  });
  const [viewMode, setViewMode] = useEApprovalListView(VIEW_STORAGE_KEY, "gallery");
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document_no", "status", "current_step"],
  });

  useEffect(() => {
    const nextStatus = searchParams.get("status");
    if (nextStatus && STATUS_FILTERS.includes(nextStatus as (typeof STATUS_FILTERS)[number])) {
      setStatus(nextStatus);
    }
    setFormId(searchParams.get("form_id") ?? "");
    setFrom(searchParams.get("from") ?? "");
    setTo(searchParams.get("to") ?? "");
    const mine = searchParams.get("mine");
    setMineOnly(mine === "1" || mine === "true");
    setPage(1);
  }, [searchParams]);

  useEffect(() => {
    setPage(1);
  }, [sort, formId, from, to, mineOnly]);

  // Live tour steps can force Gallery or Table so both layouts are demonstrated.
  useEffect(() => {
    if (searchParams.get(LIVE_TOUR_QUERY) !== "e-approval") {
      return;
    }
    const tour = resolveLiveTour("e-approval", { canApprove, canCreate });
    const stepIndex = Number.parseInt(searchParams.get(LIVE_TOUR_STEP_QUERY) ?? "", 10);
    const step = tour?.steps[stepIndex];
    const mode = step?.listViewMode;
    if (mode && viewMode !== mode) {
      setViewMode(mode);
    }
  }, [canApprove, canCreate, searchParams, setViewMode, viewMode]);

  const { data, isFetching, isError, error, refetch } = useQuery({
    queryKey: ["e-approval", "submissions", page, status, debouncedSearch, mineOnly, sort, formId, from, to],
    queryFn: () =>
      fetchEApprovalSubmissionsIndex({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch.trim() || undefined,
        status: status === "all" ? undefined : status,
        mine: mineOnly || undefined,
        form_id: formId || undefined,
        from: from || undefined,
        to: to || undefined,
        sort,
      }),
  });

  // Total returned across all pages (banner count must not be current-page only).
  const returnedCountQuery = useQuery({
    queryKey: ["e-approval", "submissions", "returned-count", debouncedSearch, mineOnly, formId, from, to],
    queryFn: () =>
      fetchEApprovalSubmissionsIndex({
        page: 1,
        per_page: 1,
        search: debouncedSearch.trim() || undefined,
        status: "returned",
        mine: mineOnly || undefined,
        form_id: formId || undefined,
        from: from || undefined,
        to: to || undefined,
      }),
    enabled: status === "all",
    select: (payload) => payload.meta.total,
  });

  const rows = data?.data ?? [];
  const meta = data?.meta;
  const isEmpty = !isFetching && rows.length === 0;
  const returnedCount = returnedCountQuery.data ?? 0;

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsView]}>
      <div className="space-y-5">
        <LiveProductTourHost />
        <EApprovalPageHeader
          title="Submissions"
          description="Track requests in workflow. Switch between gallery and table to match how you work."
          actions={
            <>
              <EApprovalHelpEntryActions />
              {canExport ? (
                <Button
                  size="sm"
                  variant="outline"
                  type="button"
                  onClick={async () => {
                    try {
                      const result = await downloadEApprovalSubmissionsExport({
                        status: status === "all" ? undefined : status,
                        form_id: formId || undefined,
                        from: from || undefined,
                        to: to || undefined,
                      });
                      saveBlob(result.blob, `submissions-${new Date().toISOString().slice(0, 10)}.csv`);
                    } catch (e) {
                      push({ level: "error", title: "Export failed", message: getErrorMessage(e) });
                    }
                  }}
                >
                  Export CSV
                </Button>
              ) : null}
              {canCreate ? (
                <Button
                  size="sm"
                  type="button"
                  data-help="ea-submissions-new"
                  data-tour-nav="/e-approval/submissions/new"
                  onClick={() => window.location.assign("/e-approval/submissions/new")}
                >
                  <Plus className="h-3.5 w-3.5" />
                  New submission
                </Button>
              ) : (
                <span
                  data-help="ea-submissions-new"
                  data-tour-nav="/e-approval/submissions/new"
                  className="sr-only"
                >
                  New submission (not available)
                </span>
              )}
            </>
          }
        />

        <div data-help="ea-submissions-filters" className="flex flex-wrap items-center gap-2">
          {STATUS_FILTERS.map((s) => (
            <Button
              key={s}
              size="sm"
              variant={status === s ? "default" : "outline"}
              onClick={() => {
                setStatus(s);
                setPage(1);
              }}
            >
              {formatSubmissionFilterLabel(s)}
            </Button>
          ))}
          {canViewAll ? (
            <Button
              size="sm"
              variant={mineOnly ? "secondary" : "ghost"}
              className="ml-auto gap-1.5"
              onClick={() => {
                setMineOnly((v) => !v);
                setPage(1);
              }}
            >
              <User className="h-3.5 w-3.5" />
              {mineOnly ? "Mine" : "All users"}
            </Button>
          ) : null}
        </div>

        {status === "all" && returnedCount > 0 ? (
          <button
            type="button"
            className="w-full rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2 text-left text-sm text-amber-900 transition-colors hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200 dark:hover:bg-amber-950/50"
            onClick={() => {
              setStatus("returned");
              setPage(1);
            }}
          >
            {returnedCount} submission{returnedCount > 1 ? "s" : ""} need revision. Click to open the “Needs revision” filter.
          </button>
        ) : null}

        <EApprovalListShell
          error={
            isError ? (
              <div className="space-y-2 px-4 py-3">
                <p className="text-sm text-destructive">
                  Could not load submissions. {getErrorMessage(error)}
                </p>
                <Button type="button" size="sm" variant="outline" onClick={() => void refetch()}>
                  Retry
                </Button>
              </div>
            ) : null
          }
          toolbar={
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div data-help="ea-submissions-search" className="min-w-0 flex-1">
                <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="e-approval-submissions-search">
                  Search submissions
                </label>
                <Input
                  id="e-approval-submissions-search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Document no, form, requestor"
                  className="h-11 w-full text-base sm:h-9 sm:max-w-md sm:text-sm"
                />
              </div>
              <EApprovalListViewToggle
                value={viewMode}
                onChange={setViewMode}
                ariaLabel="Submissions list view"
                dataHelp="ea-submissions-view"
              />
            </div>
          }
          footer={
            meta ? (
              <PaginatedListFooter meta={{ ...meta, current_page: page }} onPageChange={setPage} isPending={isFetching} />
            ) : null
          }
        >
          {viewMode === "gallery" ? (
            <div className="p-4" data-help="ea-submissions-gallery">
              {isFetching && rows.length === 0 ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                  {Array.from({ length: 6 }).map((_, index) => (
                    <div key={index} className="h-40 animate-pulse rounded-xl border border-border bg-muted/40" />
                  ))}
                </div>
              ) : isEmpty ? (
                isEApprovalTourActive(searchParams) ? (
                  <EApprovalTourSubmissionFixtures />
                ) : (
                  <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 px-6 py-14 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                      <FileStack className="h-6 w-6" />
                    </div>
                    <h2 className="mt-4 text-base font-medium">No submissions</h2>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                      {canCreate
                        ? "No requests yet. Start a new submission from a published form."
                        : "Nothing matches this filter. You need the requestor role (e_approval:submissions:create) to start requests — ask an administrator."}
                    </p>
                    {canCreate ? (
                      <Button className="mt-4" size="sm" onClick={() => window.location.assign("/e-approval/submissions/new")}>
                        <Plus className="h-3.5 w-3.5" />
                        New submission
                      </Button>
                    ) : null}
                  </div>
                )
              ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                  {rows.map((row, index) => (
                    <EApprovalSubmissionGalleryCard
                      key={row.id}
                      submission={row}
                      helpStatus={index === 0}
                      helpActions={index === 0}
                    />
                  ))}
                </div>
              )}
            </div>
          ) : (
            <div data-help="ea-submissions-table">
              {isEmpty && isEApprovalTourActive(searchParams) ? (
                <EApprovalTourSubmissionTableFixtures />
              ) : (
                <RegistryDataTableView
                  columns={eApprovalSubmissionTableColumns}
                  data={rows}
                  getRowId={(row) => row.id}
                  isLoading={isFetching}
                  isEmpty={isEmpty}
                  emptyMessage="No submissions match this filter."
                  enableColumnVisibility
                  columnVisibilityStorageKey="toweros.table.columns.e-approval.submissions"
                  sorting={sorting}
                  onSortingChange={onSortingChange}
                  manualSorting={manualSorting}
                  getRowClassName={(row) =>
                    row.original.status === "returned"
                      ? "bg-amber-50/50 hover:bg-amber-50 dark:bg-amber-950/20 dark:hover:bg-amber-950/30"
                      : undefined
                  }
                />
              )}
            </div>
          )}
        </EApprovalListShell>
      </div>
    </PermissionGate>
  );
}
