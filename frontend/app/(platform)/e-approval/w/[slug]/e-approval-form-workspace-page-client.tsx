"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import type { ColumnDef } from "@tanstack/react-table";
import { useEffect, useMemo, useState } from "react";
import { Plus, Printer } from "lucide-react";

import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { EApprovalWorkspaceAuditLog } from "@/components/e-approval/e-approval-workspace-audit-log";
import { EApprovalWorkspaceRecentActivity } from "@/components/e-approval/e-approval-workspace-recent-activity";
import { EApprovalWorkspaceStatusChart } from "@/components/e-approval/e-approval-workspace-status-chart";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { Input } from "@/components/ui/input";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { usePermission } from "@/hooks/use-permission";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import {
  downloadEApprovalWorkspaceExport,
  fetchEApprovalFormWorkspaceDashboard,
  fetchEApprovalWorkspaceSubmissions,
  type EApprovalWorkspaceSubmissionRow,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { workspaceDisplayTitle } from "@/modules/e-approval/form-workspace";
import {
  DEFAULT_WORKSPACE_DASHBOARD,
  enabledWidgets,
  resolveSavedViewFilters,
  visibleTableColumns,
  type WorkspaceSavedView,
  type WorkspaceTableColumn,
} from "@/modules/e-approval/form-workspace-dashboard-config";
import type { ProjectOneKpi } from "@/modules/project-one/types";

type Props = { slug: string };

const PER_PAGE = 25;
const DEFAULT_SORT = "created_at:desc";
const SORTABLE_SYSTEM_COLUMNS = new Set(["document_no", "status", "current_step", "created_at"]);

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function renderColumnValue(row: EApprovalWorkspaceSubmissionRow, column: WorkspaceTableColumn): string {
  switch (column.key) {
    case "document_no":
      return row.document_no;
    case "form_name":
      return row.form_name ?? "—";
    case "status":
      return row.status;
    case "requestor":
      return row.requestor?.name ?? "—";
    case "current_step":
      return row.current_step != null ? String(row.current_step) : "—";
    case "created_at":
      return row.created_at ? new Date(row.created_at).toLocaleString() : "—";
    default:
      if (column.kind === "field" && column.field_name) {
        return row.field_values?.[column.field_name] ?? "—";
      }
      return "—";
  }
}

export function EApprovalFormWorkspacePageClient({ slug }: Props) {
  const push = useNotificationStore((s) => s.push);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [activeViewId, setActiveViewId] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document_no", "status", "current_step", "created_at"],
  });

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const dashboardQuery = useQuery({
    queryKey: ["e-approval", "workspace", slug],
    queryFn: () => fetchEApprovalFormWorkspaceDashboard(slug),
    retry: 1,
  });

  const dashboard = dashboardQuery.data;
  const listScopeAll = dashboard?.viewer.list_scope === "all";
  const dashboardConfig = dashboard?.dashboard ?? DEFAULT_WORKSPACE_DASHBOARD;
  const savedViews = dashboardConfig.saved_views?.length
    ? dashboardConfig.saved_views
    : DEFAULT_WORKSPACE_DASHBOARD.saved_views;
  const activeView =
    savedViews.find((view) => view.id === activeViewId) ?? savedViews[0] ?? DEFAULT_WORKSPACE_DASHBOARD.saved_views[0];
  const activeFilters = resolveSavedViewFilters(activeView);
  const tableColumns = useMemo(() => {
    const base = visibleTableColumns(
      dashboardConfig.table_columns?.length
        ? dashboardConfig.table_columns
        : DEFAULT_WORKSPACE_DASHBOARD.table_columns,
    );
    if (dashboard?.is_multi_form && !base.some((column) => column.key === "form_name")) {
      return [
        { key: "form_name", label: "Form", kind: "system" as const, visible: true, order: 0 },
        ...base.map((column, index) => ({ ...column, order: index + 1 })),
      ];
    }
    return base;
  }, [dashboard?.is_multi_form, dashboardConfig.table_columns]);
  const widgets = enabledWidgets(
    dashboardConfig.widgets?.length ? dashboardConfig.widgets : DEFAULT_WORKSPACE_DASHBOARD.widgets,
  );

  const submissionsQuery = useQuery({
    queryKey: [
      "e-approval",
      "workspace",
      slug,
      "submissions",
      page,
      activeViewId,
      debouncedSearch,
      listScopeAll,
      sort,
    ],
    queryFn: () =>
      fetchEApprovalWorkspaceSubmissions(slug, {
        page,
        per_page: PER_PAGE,
        search: debouncedSearch.trim() || undefined,
        status: activeFilters.status,
        mine: activeFilters.mine ?? (activeView.mine && listScopeAll ? true : undefined),
        from: activeFilters.from,
        sort,
      }),
    enabled: Boolean(dashboard),
  });

  const title = dashboard ? workspaceDisplayTitle(dashboard.workspace, dashboard.form.name) : "Form workspace";
  const kpis: ProjectOneKpi[] = useMemo(
    () =>
      (dashboard?.kpis ?? []).map((item) => ({
        key: item.key,
        label: item.label,
        value: item.value,
        change: item.change ?? undefined,
        tone: item.tone === "default" ? "neutral" : item.tone,
      })),
    [dashboard?.kpis],
  );

  const rows = submissionsQuery.data?.data ?? [];
  const meta = submissionsQuery.data?.meta;
  const isEmpty = !submissionsQuery.isFetching && rows.length === 0;

  const columns = useMemo((): ColumnDef<EApprovalWorkspaceSubmissionRow>[] => {
    const defs: ColumnDef<EApprovalWorkspaceSubmissionRow>[] = tableColumns.map((column) => {
      const sortable = column.kind === "system" && SORTABLE_SYSTEM_COLUMNS.has(column.key);

      return {
        id: column.key,
        accessorFn: (row) => {
          const value = renderColumnValue(row, column);
          return typeof value === "string" || typeof value === "number" ? value : "";
        },
        header: sortable
          ? ({ column: tableColumn }) => (
              <DataTableColumnHeader column={tableColumn} title={column.label} />
            )
          : column.label,
        enableSorting: sortable,
        cell: ({ row }) => {
          if (column.key === "status") {
            return <EApprovalStatusBadge status={row.original.status} kind="submission" />;
          }
          if (column.key === "document_no") {
            return <span className="font-medium">{row.original.document_no}</span>;
          }
          return <span className="text-muted-foreground">{renderColumnValue(row.original, column)}</span>;
        },
      };
    });

    defs.push({
      id: "open",
      header: () => <span className="block w-full text-right">Open</span>,
      enableSorting: false,
      cell: ({ row }) => (
        <div className="flex justify-end gap-2">
          <Link
            href={`/e-approval/submissions/${row.original.id}/print`}
            title="Print"
            className={buttonVariants({ size: "sm", variant: "ghost" })}
          >
            <Printer className="h-3.5 w-3.5" />
          </Link>
          <Link
            href={`/e-approval/submissions/${row.original.id}`}
            className={buttonVariants({ size: "sm", variant: "outline" })}
          >
            Open
          </Link>
        </div>
      ),
    });

    return defs;
  }, [tableColumns]);

  const applySavedView = (view: WorkspaceSavedView) => {
    setActiveViewId(view.id);
    setPage(1);
  };

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView, permissions.eApprovalSubmissionsView]}>
      <div className="space-y-5">
        <nav className="text-sm text-muted-foreground">
          <Link href="/e-approval" className="hover:text-foreground">
            E-Approval
          </Link>
          <span className="px-2">/</span>
          <span className="text-foreground">Workspaces</span>
          <span className="px-2">/</span>
          <span className="text-foreground">{title}</span>
        </nav>

        <EApprovalPageHeader
          title={title}
          description={
            dashboard?.workspace.description?.trim() ||
            dashboard?.form.description?.trim() ||
            "Operational dashboard for this approval form."
          }
          actions={
            <>
              <Button type="button" size="sm" variant="outline" onClick={() => dashboardQuery.refetch()} disabled={dashboardQuery.isFetching}>
                Refresh
              </Button>
              {dashboard?.viewer.can_export ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={async () => {
                    try {
                      const blob = await downloadEApprovalWorkspaceExport(slug, {
                        status: activeFilters.status,
                        search: debouncedSearch.trim() || undefined,
                        mine: activeFilters.mine ?? undefined,
                      });
                      saveBlob(blob, `${slug}-${new Date().toISOString().slice(0, 10)}.csv`);
                    } catch (e) {
                      push({ level: "error", title: "Export failed", message: getErrorMessage(e) });
                    }
                  }}
                >
                  Export CSV
                </Button>
              ) : null}
              {canApprove ? (
                <Link href="/e-approval/approvals?awaiting_me=1" className={buttonVariants({ size: "sm", variant: "outline" })}>
                  My approvals
                </Link>
              ) : null}
              {dashboard?.viewer.can_submit ? (
                <Link href={dashboard.viewer.new_request_href} className={buttonVariants({ size: "sm" })}>
                  <Plus className="h-3.5 w-3.5" />
                  New request
                </Link>
              ) : null}
            </>
          }
        />

        {dashboardQuery.isError ? (
          <p className="text-sm text-destructive">Could not load workspace. Confirm the form is published and workspace is enabled.</p>
        ) : null}

        {widgets.map((widget) => {
          if (widget.type === "kpis" && dashboard) {
            return <KpiStrip key={widget.id} items={kpis} />;
          }
          if (widget.type === "status_chart" && dashboard) {
            return <EApprovalWorkspaceStatusChart key={widget.id} items={dashboard.status_breakdown} />;
          }
          if (widget.type === "recent_activity" && dashboard) {
            return <EApprovalWorkspaceRecentActivity key={widget.id} items={dashboard.recent_activity} />;
          }
          if (widget.type === "audit_log" && dashboard) {
            return <EApprovalWorkspaceAuditLog key={widget.id} items={dashboard.recent_audit} />;
          }
          if (widget.type === "submissions_table") {
            return (
              <EApprovalListShell
                key={widget.id}
                toolbar={
                  <div className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                      {savedViews.map((view) => (
                        <Button
                          key={view.id}
                          type="button"
                          size="sm"
                          variant={activeViewId === view.id ? "secondary" : "ghost"}
                          className="h-8"
                          onClick={() => applySavedView(view)}
                        >
                          {view.label}
                        </Button>
                      ))}
                    </div>
                    <Input
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      placeholder="Document no or requestor"
                      className="max-w-sm"
                    />
                  </div>
                }
                footer={
                  meta ? (
                    <PaginatedListFooter
                      meta={meta}
                      onPageChange={setPage}
                      isPending={submissionsQuery.isFetching}
                    />
                  ) : null
                }
              >
                <RegistryDataTableView
                  columns={columns}
                  data={rows}
                  getRowId={(row) => row.id}
                  isLoading={submissionsQuery.isFetching && rows.length === 0}
                  isEmpty={isEmpty}
                  emptyMessage="No submissions match this filter."
                  enableColumnVisibility
                  columnVisibilityStorageKey="toweros.table.columns.e-approval.workspace"
                  sorting={sorting}
                  onSortingChange={onSortingChange}
                  manualSorting={manualSorting}
                />
              </EApprovalListShell>
            );
          }
          return null;
        })}
      </div>
    </PermissionGate>
  );
}
