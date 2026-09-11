"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import type { ColumnDef } from "@tanstack/react-table";
import { useEffect, useMemo, useRef, useState } from "react";
import { Plus, Printer } from "lucide-react";

import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import {
  EApprovalWorkflowStepShow,
  buildWorkflowStepShowItems,
} from "@/components/e-approval/e-approval-workflow-step-show";
import {
  EApprovalWorkspaceStatusBreakdownChart,
  EApprovalWorkspaceSubsidiaryChart,
} from "@/components/e-approval/e-approval-workspace-analytics-charts";
import { EApprovalWorkspaceAuditLog } from "@/components/e-approval/e-approval-workspace-audit-log";
import { EApprovalWorkspaceRecentActivity } from "@/components/e-approval/e-approval-workspace-recent-activity";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { FilterSelect } from "@/components/forms/filter-select";
import { ModuleListToolbar } from "@/components/registry/module-list-toolbar";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { Input } from "@/components/ui/input";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import { useLocalStorageJsonState } from "@/hooks/use-local-storage-json-state";
import { usePermission } from "@/hooks/use-permission";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import {
  downloadEApprovalWorkspaceExport,
  fetchEApprovalFormWorkspaceDashboard,
  fetchEApprovalSubmissionsExportColumns,
  fetchEApprovalWorkspaceSubmissions,
  type EApprovalWorkspaceSubmissionRow,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { saveBlob } from "@/lib/ui/module-list-download";
import { Select } from "@/components/ui/select";
import {
  EMPTY_DASHBOARD_LAYOUT_PREFS,
  normalizeDashboardLayoutPrefs,
  type DashboardLayoutPrefs,
  type DashboardWidgetDef,
} from "@/lib/ui/dashboard-widget-registry";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { normalizeEApprovalWorkspace } from "@/lib/ui/normalize-dashboard-data";
import { E_APPROVAL_WORKSPACE_PAGE_CHROME } from "@/lib/ui/page-chrome-config";
import { cn } from "@/lib/utils";
import { useNotificationStore } from "@/stores/notification-store";
import { workspaceDisplayTitle } from "@/modules/e-approval/form-workspace";
import {
  DEFAULT_WORKSPACE_DASHBOARD,
  WORKSPACE_WIDGET_LABELS,
  enabledWidgets,
  expandWorkspaceDashboardWidgets,
  resolveSavedViewFilters,
  visibleTableColumns,
  type WorkspaceSavedView,
  type WorkspaceTableColumn,
  type WorkspaceWidgetType,
} from "@/modules/e-approval/form-workspace-dashboard-config";

const SOFT_HIDEABLE_WIDGET_TYPES = new Set<WorkspaceWidgetType>([
  "kpis",
  "chart_by_status",
  "chart_by_subsidiary",
  "status_chart",
  "recent_activity",
  "audit_log",
]);

type Props = { slug: string };

type WorkspacePrefs = {
  activeViewId: string;
  subsidiary: string;
  department: string;
  statusFilter: string;
  density: "comfortable" | "compact";
} & DashboardLayoutPrefs;

const PER_PAGE = 25;
const DEFAULT_SORT = "created_at:desc";
const SORTABLE_SYSTEM_COLUMNS = new Set(["document_no", "status", "current_step", "created_at"]);
const STATUS_FILTER_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "pending", label: "Pending" },
  { value: "returned", label: "Needs revision" },
  { value: "approved", label: "Approved" },
  { value: "rejected", label: "Rejected" },
  { value: "cancelled", label: "Cancelled" },
  { value: "draft", label: "Draft" },
] as const;

const DEFAULT_PREFS: WorkspacePrefs = {
  activeViewId: "all",
  subsidiary: "",
  department: "",
  statusFilter: "",
  density: "comfortable",
  ...EMPTY_DASHBOARD_LAYOUT_PREFS,
  spans: {},
  widgetOptions: {},
  pageChrome: {},
};

function expandWorkspaceLayoutWidgetIds(ids: string[]): string[] {
  const out: string[] = [];
  for (const id of ids) {
    if (id === "status_chart") {
      for (const next of ["chart_by_status", "chart_by_subsidiary"] as const) {
        if (!out.includes(next)) out.push(next);
      }
      continue;
    }
    if (!out.includes(id)) out.push(id);
  }
  return out;
}

function isWorkspacePrefs(value: unknown): value is WorkspacePrefs {
  if (!value || typeof value !== "object") return false;
  const row = value as Record<string, unknown>;
  return (
    typeof row.activeViewId === "string" &&
    typeof row.subsidiary === "string" &&
    typeof row.department === "string" &&
    typeof row.statusFilter === "string" &&
    (row.density === "comfortable" || row.density === "compact")
  );
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
  const [exportLayout, setExportLayout] = useState<"submissions" | "line_items">("submissions");
  const [gridFieldId, setGridFieldId] = useState("");
  const [prefs, setPrefs] = useLocalStorageJsonState<WorkspacePrefs>(
    `toweros.e-approval.workspace.prefs.${slug}`,
    DEFAULT_PREFS,
    isWorkspacePrefs,
  );
  const {
    layout,
    setLayout,
    tenantDefault,
    publishTenantDefault,
    resetToTenantDefault,
    serverReady,
    flushPersonalPersist,
  } = useDashboardLayoutPrefs(`toweros.e-approval.workspace.layout.${slug}`);
  const { editing, setEditing } = useDashboardCustomizeMode({ onExitEdit: flushPersonalPersist });
  const seededLayoutRef = useRef(false);
  const activeViewId = prefs.activeViewId;
  const subsidiary = prefs.subsidiary;
  const department = prefs.department;
  const statusFilter = prefs.statusFilter;
  const density = prefs.density;
  const legacyLayout = useMemo(() => normalizeDashboardLayoutPrefs(prefs), [prefs]);
  const expandedBundlesRef = useRef(false);
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document_no", "status", "current_step", "created_at"],
  });

  useEffect(() => {
    setPage(1);
  }, [sort, subsidiary, department, statusFilter]);

  useEffect(() => {
    if (!serverReady || seededLayoutRef.current) return;
    seededLayoutRef.current = true;
    const empty =
      layout.enabledWidgetIds.length === 0 &&
      layout.widgetOrder.length === 0 &&
      Object.keys(layout.spans).length === 0 &&
      Object.keys(layout.widgetOptions).length === 0;
    const hasLegacy =
      legacyLayout.enabledWidgetIds.length > 0 ||
      legacyLayout.widgetOrder.length > 0 ||
      Object.keys(legacyLayout.spans).length > 0 ||
      Object.keys(legacyLayout.widgetOptions).length > 0 ||
      Object.keys(legacyLayout.pageChrome ?? {}).length > 0;
    if (empty && hasLegacy) {
      setLayout({
        ...legacyLayout,
        enabledWidgetIds: expandWorkspaceLayoutWidgetIds(legacyLayout.enabledWidgetIds),
        widgetOrder: expandWorkspaceLayoutWidgetIds(legacyLayout.widgetOrder),
      });
    }
  }, [legacyLayout, layout, serverReady, setLayout]);

  useEffect(() => {
    if (!serverReady || expandedBundlesRef.current) return;
    const needsExpand =
      layout.enabledWidgetIds.includes("status_chart") ||
      layout.widgetOrder.includes("status_chart");
    if (!needsExpand) {
      expandedBundlesRef.current = true;
      return;
    }
    expandedBundlesRef.current = true;
    const enabled = expandWorkspaceLayoutWidgetIds(
      layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : [],
    );
    const order = expandWorkspaceLayoutWidgetIds(
      layout.widgetOrder.length > 0 ? layout.widgetOrder : enabled,
    );
    const spans = { ...layout.spans };
    delete spans.status_chart;
    if (!spans.chart_by_status) spans.chart_by_status = "half";
    if (!spans.chart_by_subsidiary) spans.chart_by_subsidiary = "half";
    setLayout({
      ...layout,
      enabledWidgetIds: enabled,
      widgetOrder: order,
      spans,
    });
  }, [layout, serverReady, setLayout]);

  const patchPrefs = (patch: Partial<WorkspacePrefs>) => {
    setPrefs((current: WorkspacePrefs) => ({ ...current, ...patch }));
  };

  const dashboardQuery = useQuery({
    queryKey: ["e-approval", "workspace", slug, "dashboard-shell"],
    queryFn: () => fetchEApprovalFormWorkspaceDashboard(slug),
    retry: 1,
  });

  const dashboardShell = dashboardQuery.data;
  const listScopeAll = dashboardShell?.viewer.list_scope === "all";
  const dashboardConfig = dashboardShell?.dashboard ?? DEFAULT_WORKSPACE_DASHBOARD;
  const savedViews = dashboardConfig.saved_views?.length
    ? dashboardConfig.saved_views
    : DEFAULT_WORKSPACE_DASHBOARD.saved_views;
  const activeView =
    savedViews.find((view) => view.id === activeViewId) ?? savedViews[0] ?? DEFAULT_WORKSPACE_DASHBOARD.saved_views[0];
  const activeFilters = resolveSavedViewFilters(activeView);
  const resolvedStatus = statusFilter || undefined;
  const resolvedMine = activeFilters.mine ?? (activeView.mine && listScopeAll ? true : undefined);

  const filteredDashboardQuery = useQuery({
    queryKey: [
      "e-approval",
      "workspace",
      slug,
      "dashboard",
      resolvedStatus ?? "all",
      activeFilters.from ?? "",
      subsidiary,
      department,
      resolvedMine ? 1 : 0,
    ],
    queryFn: () =>
      fetchEApprovalFormWorkspaceDashboard(slug, {
        status: resolvedStatus,
        from: activeFilters.from,
        subsidiary: subsidiary || undefined,
        department: department || undefined,
        mine: resolvedMine,
      }),
    enabled: Boolean(dashboardShell),
    retry: 1,
  });

  const dashboard = filteredDashboardQuery.data ?? dashboardShell;
  const formId = dashboard?.form?.id ?? "";
  const exportColumnsQuery = useQuery({
    queryKey: ["e-approval", "workspace", slug, "export-columns", formId],
    queryFn: () => fetchEApprovalSubmissionsExportColumns(formId),
    enabled: Boolean(formId) && Boolean(dashboard?.viewer.can_export),
    staleTime: 60_000,
  });
  const gridFields = exportColumnsQuery.data?.grids ?? [];
  const hasGrids = gridFields.length > 0;

  useEffect(() => {
    if (!hasGrids && exportLayout === "line_items") {
      setExportLayout("submissions");
    }
    if (hasGrids && !gridFieldId && gridFields[0]?.key) {
      setGridFieldId(gridFields[0].key);
    }
  }, [exportLayout, gridFieldId, gridFields, hasGrids]);

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
  const adminEnabledWidgets = useMemo(
    () =>
      expandWorkspaceDashboardWidgets(
        enabledWidgets(
          dashboardConfig.widgets?.length ? dashboardConfig.widgets : DEFAULT_WORKSPACE_DASHBOARD.widgets,
        ),
      ),
    [dashboardConfig.widgets],
  );
  const layoutMeta = useMemo(
    () =>
      adminEnabledWidgets.map((widget) => ({
        id: widget.id,
        label: WORKSPACE_WIDGET_LABELS[widget.type],
        hideable: SOFT_HIDEABLE_WIDGET_TYPES.has(widget.type),
      })),
    [adminEnabledWidgets],
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
      resolvedStatus ?? "all",
      activeFilters.from ?? "",
      subsidiary,
      department,
      resolvedMine ? 1 : 0,
    ],
    queryFn: () =>
      fetchEApprovalWorkspaceSubmissions(slug, {
        page,
        per_page: PER_PAGE,
        search: debouncedSearch.trim() || undefined,
        status: resolvedStatus,
        mine: resolvedMine,
        from: activeFilters.from,
        sort,
        subsidiary: subsidiary || undefined,
        department: department || undefined,
      }),
    enabled: Boolean(dashboardShell),
  });

  const title = dashboard ? workspaceDisplayTitle(dashboard.workspace, dashboard.form.name) : "Form workspace";

  const subsidiaryOptions = dashboard?.filter_options?.subsidiaries ?? [];
  const departmentOptions = dashboard?.filter_options?.departments ?? [];

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
          if (column.key === "current_step") {
            return (
              <EApprovalWorkflowStepShow
                variant="compact"
                steps={buildWorkflowStepShowItems({
                  currentStep: row.original.current_step,
                  stepCount: row.original.step_count,
                  status: row.original.status,
                  workflowSteps: row.original.workflow_steps,
                })}
                emptyLabel="—"
              />
            );
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
    patchPrefs({
      activeViewId: view.id,
      statusFilter: view.status && view.status !== "all" ? view.status : "",
    });
    setPage(1);
  };

  const clearColumnFilters = () => {
    patchPrefs({
      subsidiary: "",
      department: "",
      statusFilter: "",
      activeViewId: "all",
    });
    setPage(1);
  };

  const hasColumnFilters = Boolean(subsidiary || department || statusFilter || activeViewId !== "all");

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    return adminEnabledWidgets.flatMap((widget) => {
      const hideable = SOFT_HIDEABLE_WIDGET_TYPES.has(widget.type);
      const label = WORKSPACE_WIDGET_LABELS[widget.type];

      // KPI strip binds via catalog kind (`kpi_metric_row`) so Layout & options kpiCards apply.
      if (widget.type === "kpis") {
        return [];
      }
      if (widget.type === "chart_by_status" || widget.type === "status_chart") {
        return [
          {
            id: widget.type === "status_chart" ? "chart_by_status" : widget.id,
            label: WORKSPACE_WIDGET_LABELS.chart_by_status,
            hideable,
            defaultSpan: "half" as const,
            render: () =>
              dashboard ? (
                <EApprovalWorkspaceStatusBreakdownChart statusItems={dashboard.status_breakdown} />
              ) : null,
          },
        ];
      }
      if (widget.type === "chart_by_subsidiary") {
        return [
          {
            id: widget.id,
            label,
            hideable,
            defaultSpan: "half" as const,
            render: () =>
              dashboard ? (
                <EApprovalWorkspaceSubsidiaryChart
                  subsidiaryItems={dashboard.subsidiary_breakdown ?? []}
                />
              ) : null,
          },
        ];
      }
      if (widget.type === "recent_activity") {
        return [
          {
            id: widget.id,
            label,
            hideable,
            render: () =>
              dashboard ? <EApprovalWorkspaceRecentActivity items={dashboard.recent_activity} /> : null,
          },
        ];
      }
      if (widget.type === "audit_log") {
        return [
          {
            id: widget.id,
            label,
            hideable,
            render: () => (dashboard ? <EApprovalWorkspaceAuditLog items={dashboard.recent_audit} /> : null),
          },
        ];
      }

      return [
        {
          id: widget.id,
          label,
          hideable: false,
          render: () => (
            <EApprovalListShell
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
                <div className="flex flex-wrap items-end gap-3">
                  <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search or tokens: status:pending subsidiary:HQ vendor~acme"
                    className="max-w-md"
                    title="Tokens: status:, document~, requestor~, subsidiary:, department:, plus visible form field names (eq / != / ~ / pipe OR)."
                  />
                  <FilterSelect
                    id="workspace-status-filter"
                    label="Status"
                    value={statusFilter}
                    onChange={(value) => {
                      patchPrefs({ statusFilter: value });
                      setPage(1);
                    }}
                    className="w-[10.5rem]"
                  >
                    {STATUS_FILTER_OPTIONS.map((option) => (
                      <option key={option.value || "all"} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </FilterSelect>
                  <FilterSelect
                    id="workspace-subsidiary-filter"
                    label="Subsidiary"
                    value={subsidiary}
                    onChange={(value) => {
                      patchPrefs({ subsidiary: value });
                      setPage(1);
                    }}
                    className="w-[9.5rem]"
                  >
                    <option value="">All subsidiaries</option>
                    {subsidiaryOptions.map((code) => (
                      <option key={code} value={code}>
                        {code}
                      </option>
                    ))}
                  </FilterSelect>
                  <FilterSelect
                    id="workspace-department-filter"
                    label="Department"
                    value={department}
                    onChange={(value) => {
                      patchPrefs({ department: value });
                      setPage(1);
                    }}
                    className="w-[11rem]"
                  >
                    <option value="">All departments</option>
                    {departmentOptions.map((code) => (
                      <option key={code} value={code}>
                        {code}
                      </option>
                    ))}
                  </FilterSelect>
                  {hasColumnFilters ? (
                    <Button type="button" size="sm" variant="ghost" className="h-9" onClick={clearColumnFilters}>
                      Clear filters
                    </Button>
                  ) : null}
                </div>
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
              scrollClassName={cn(density === "compact" && "[&_td]:py-1.5 [&_th]:h-9 [&_th]:py-1.5")}
              toolbarStart={
                <div className="flex items-center gap-1 rounded-md border border-border bg-muted/30 p-0.5">
                  <Button
                    type="button"
                    size="sm"
                    variant={density === "comfortable" ? "secondary" : "ghost"}
                    className="h-7 px-2 text-xs"
                    onClick={() => patchPrefs({ density: "comfortable" })}
                  >
                    Comfortable
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant={density === "compact" ? "secondary" : "ghost"}
                    className="h-7 px-2 text-xs"
                    onClick={() => patchPrefs({ density: "compact" })}
                  >
                    Compact
                  </Button>
                </div>
              }
            />
          </EApprovalListShell>
        ),
      },
      ];
    });
  }, [
    activeViewId,
    adminEnabledWidgets,
    columns,
    dashboard,
    density,
    department,
    departmentOptions,
    hasColumnFilters,
    isEmpty,
    manualSorting,
    meta,
    onSortingChange,
    rows,
    savedViews,
    search,
    sorting,
    statusFilter,
    submissionsQuery.isFetching,
    subsidiary,
    subsidiaryOptions,
  ]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const normalizedData = useMemo(() => normalizeEApprovalWorkspace(dashboard), [dashboard]);

  const defaultEnabledIds = useMemo(
    () => layoutMeta.map((widget) => widget.id),
    [layoutMeta],
  );

  const catalogBoardWidgets = useMemo(
    () =>
      buildDynamicBoardWidgets({
        moduleId: "e-approval-workspace",
        data: normalizedData,
        slots: boardWidgets,
        enabledIds: layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds,
        titleOverrides,
        widgetOptions: layout.widgetOptions,
      }),
    [
      boardWidgets,
      defaultEnabledIds,
      layout.enabledWidgetIds,
      layout.widgetOptions,
      normalizedData,
      titleOverrides,
    ],
  );

  const bindableCatalog = useMemo(
    () => bindableCatalogEntries("e-approval-workspace", boardWidgets, normalizedData),
    [boardWidgets, normalizedData],
  );

  const addableCatalog = useMemo(() => {
    const enabled = new Set(
      layout.enabledWidgetIds.length > 0
        ? layout.enabledWidgetIds
        : layoutMeta.map((widget) => widget.id),
    );
    return bindableCatalog.filter((entry) => !enabled.has(entry.id));
  }, [bindableCatalog, layout.enabledWidgetIds, layoutMeta]);

  const boardHandlers = useDashboardBoardLayoutHandlers(
    layout,
    setLayout,
    layoutMeta.map((widget) => widget.id),
  );

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView, permissions.eApprovalSubmissionsView]}>
      <div className="space-y-5">
        <nav className="text-sm text-muted-foreground">
          <Link href="/e-approval" className="hover:text-foreground">
            E-Forms
          </Link>
          <span className="px-2">/</span>
          <span className="text-foreground">Workspaces</span>
          <span className="px-2">/</span>
          <span className="text-foreground">{title}</span>
        </nav>

        <ConfigurableModulePageHeader
          defaults={{
            ...E_APPROVAL_WORKSPACE_PAGE_CHROME,
            title,
            description:
              dashboard?.workspace.description?.trim() ||
              dashboard?.form.description?.trim() ||
              E_APPROVAL_WORKSPACE_PAGE_CHROME.description,
          }}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout({ ...layout, pageChrome })}
          renderActions={({ isVisible }) => (
            <>
              {isVisible("customize") ? (
                <DashboardLayoutToolbar
                  widgets={layoutMeta}
                  catalogModule="e-approval-workspace"
                  layout={layout}
                  editing={editing}
                  onEditingChange={setEditing}
                  onChange={setLayout}
                  bindableCatalog={bindableCatalog}
                  hasTenantDefault={Boolean(tenantDefault)}
                  onPublishTenantDefault={publishTenantDefault}
                  onResetToTenantDefault={resetToTenantDefault}
                  data={normalizedData}
                />
              ) : null}
              {isVisible("refresh") ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    void dashboardQuery.refetch();
                    void filteredDashboardQuery.refetch();
                    void submissionsQuery.refetch();
                  }}
                  disabled={dashboardQuery.isFetching || filteredDashboardQuery.isFetching}
                >
                  Refresh
                </Button>
              ) : null}
              {isVisible("export") && dashboard?.viewer.can_export ? (
                <ModuleListToolbar
                  showPrint={false}
                  start={
                    hasGrids ? (
                      <div className="flex flex-wrap items-center gap-1.5">
                        <Select
                          value={exportLayout}
                          onChange={(event) =>
                            setExportLayout(
                              event.target.value === "line_items" ? "line_items" : "submissions",
                            )
                          }
                          className="h-8 w-[9.5rem] text-xs"
                          aria-label="Export layout"
                        >
                          <option value="submissions">Submissions</option>
                          <option value="line_items">Line items</option>
                        </Select>
                        {exportLayout === "line_items" && gridFields.length > 1 ? (
                          <Select
                            value={gridFieldId}
                            onChange={(event) => setGridFieldId(event.target.value)}
                            className="h-8 max-w-[10rem] text-xs"
                            aria-label="Line-item grid field"
                          >
                            {gridFields.map((grid) => (
                              <option key={grid.key} value={grid.key}>
                                {grid.label}
                              </option>
                            ))}
                          </Select>
                        ) : null}
                      </div>
                    ) : null
                  }
                  onExport={async (format) => {
                    try {
                      const useLineItems = exportLayout === "line_items" && hasGrids;
                      const result = await downloadEApprovalWorkspaceExport(slug, {
                        status: resolvedStatus,
                        search: debouncedSearch.trim() || undefined,
                        mine: resolvedMine,
                        from: activeFilters.from,
                        subsidiary: subsidiary || undefined,
                        department: department || undefined,
                        format,
                        columns: tableColumns.map((column) =>
                          column.key === "requestor" ? "requestor_name" : column.key,
                        ),
                        layout: useLineItems ? "line_items" : "submissions",
                        grid_field: useLineItems ? gridFieldId || undefined : undefined,
                      });
                      if (result.mode === "async") {
                        push({
                          level: "info",
                          title: "Export queued",
                          message:
                            result.message ||
                            "Large export started — open Reports → Recent exports when ready.",
                        });
                        return;
                      }
                      saveBlob(
                        result.blob,
                        `${slug}${useLineItems ? "-line-items" : ""}-${new Date().toISOString().slice(0, 10)}.${format}`,
                      );
                    } catch (e) {
                      push({ level: "error", title: "Export failed", message: getErrorMessage(e) });
                    }
                  }}
                />
              ) : null}
              {isVisible("approvals") && canApprove ? (
                <Link href="/e-approval/approvals?awaiting_me=1" className={buttonVariants({ size: "sm", variant: "outline" })}>
                  My approvals
                </Link>
              ) : null}
              {isVisible("new") && dashboard?.viewer.can_submit ? (
                <Link href={dashboard.viewer.new_request_href} className={buttonVariants({ size: "sm" })}>
                  <Plus className="h-3.5 w-3.5" />
                  New request
                </Link>
              ) : null}
            </>
          )}
        />

        {dashboardQuery.isError ? (
          <p className="text-sm text-destructive">Could not load workspace. Confirm the form is published and workspace is enabled.</p>
        ) : null}

        <DashboardWidgetBoard
          widgets={catalogBoardWidgets}
          order={layout.widgetOrder}
          hiddenIds={layout.hiddenWidgetIds}
          enabledIds={layout.enabledWidgetIds}
          layoutPrefs={layout}
          editing={editing}
          addableCatalog={addableCatalog}
          data={normalizedData}
          {...boardHandlers}
          onAddWidget={(entry, insertAt) => {
            boardHandlers.onAddWidget(entry, insertAt);
            setEditing(true);
          }}
        />
      </div>
    </PermissionGate>
  );
}
