"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { FileStack, Plus, User } from "lucide-react";

import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalListViewToggle } from "@/components/e-approval/e-approval-list-view-toggle";
import { EApprovalSubmissionGalleryCard } from "@/components/e-approval/e-approval-submission-gallery-card";
import { eApprovalSubmissionTableColumns } from "@/components/e-approval/e-approval-submission-table-columns";
import { FilterSelect } from "@/components/forms/filter-select";
import { EApprovalHelpEntryActions } from "@/components/help/e-approval-help-entry-actions";
import { EApprovalTourSubmissionFixtures, EApprovalTourSubmissionTableFixtures } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { formatEApprovalStatusLabel } from "@/modules/e-approval/status-display";
import type { EApprovalSubmissionListRow } from "@/modules/e-approval/types";
import { ModuleListToolbar } from "@/components/registry/module-list-toolbar";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useEApprovalListView } from "@/hooks/use-e-approval-list-view";
import { usePermission } from "@/hooks/use-permission";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import {
  downloadEApprovalSubmissionsExport,
  fetchEApprovalFormsIndex,
  fetchEApprovalMetadata,
  fetchEApprovalSubmissionsExportColumns,
  fetchEApprovalSubmissionsIndex,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { isEApprovalTourActive } from "@/lib/help/e-approval-tour-fixtures";
import {
  LIVE_TOUR_QUERY,
  LIVE_TOUR_STEP_QUERY,
  resolveLiveTour,
} from "@/lib/help/e-approval-live-tour";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { emptyNormalizedData } from "@/lib/ui/dashboard-widget-data";
import {
  applyPageEnhancements,
  eApprovalSubmissionsEnhancements,
} from "@/lib/ui/page-enhancement-bags";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import { printModuleListTable, saveBlob } from "@/lib/ui/module-list-download";
import { printAllOrQueuePrintableExport } from "@/lib/ui/module-list-print-all";
import { parseModuleListSearchLite } from "@/lib/ui/module-list-search-lite";
import {
  applyRowSelectionToSelectedIds,
  selectedIdsList,
  selectedIdsToRowSelection,
} from "@/lib/ui/module-list-selection";
import { readVisibleExportColumnsFromStorage } from "@/lib/ui/module-list-visible-columns";
import { E_APPROVAL_SUBMISSIONS_PAGE_CHROME } from "@/lib/ui/page-chrome-config";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { createRowSelectionColumn } from "@/components/ui/data-table-row-selection";
import { Select } from "@/components/ui/select";

const PER_PAGE = 25;
const VIEW_STORAGE_KEY = "e-approval-submissions-view";
const DEFAULT_SORT = "created_at:desc";
const LAYOUT_KEY = "toweros.e-approval.submissions.layout";
const EA_SUBMISSIONS_COLUMN_KEY = "toweros.table.columns.e-approval.submissions";
const EA_SUBMISSIONS_DEFAULT_COLUMNS = [
  "document_no",
  "form_name",
  "subsidiary",
  "status",
  "requestor",
  "current_step",
  "actions",
] as const;
const EA_SUBMISSIONS_EXPORT_MAP: Record<string, string | string[] | null> = {
  document_no: "document_no",
  form_name: "form_name",
  subsidiary: "subsidiary",
  status: "status",
  requestor: "requestor_name",
  current_step: "current_step",
  actions: null,
  select: null,
};

const STATUS_FILTERS = ["all", "returned", "pending", "approved", "rejected", "cancelled"] as const;

function formatSubmissionFilterLabel(status: (typeof STATUS_FILTERS)[number]): string {
  if (status === "returned") {
    return "Needs revision";
  }
  return formatEApprovalStatusLabel(status);
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
  const [subsidiary, setSubsidiary] = useState(() => searchParams.get("subsidiary") ?? "");
  const [department, setDepartment] = useState(() => searchParams.get("department") ?? "");
  const [from, setFrom] = useState(() => searchParams.get("from") ?? "");
  const [to, setTo] = useState(() => searchParams.get("to") ?? "");
  const [mineOnly, setMineOnly] = useState(() => {
    const mine = searchParams.get("mine");
    return mine === "1" || mine === "true";
  });
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set());
  const [exportLayout, setExportLayout] = useState<"submissions" | "line_items">("submissions");
  const [gridFieldId, setGridFieldId] = useState("");
  const [viewMode, setViewMode] = useEApprovalListView(VIEW_STORAGE_KEY, "gallery");
  const { layout, setLayout, tenantDefault, publishTenantDefault, resetToTenantDefault, flushPersonalPersist } =
    useDashboardLayoutPrefs(LAYOUT_KEY);
  const { editing, setEditing } = useDashboardCustomizeMode({ onExitEdit: flushPersonalPersist });
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document_no", "status", "current_step"],
  });

  const metadataQuery = useQuery({
    queryKey: ["e-approval", "metadata"],
    queryFn: fetchEApprovalMetadata,
    staleTime: 60_000,
  });
  const formsQuery = useQuery({
    queryKey: ["e-approval", "forms", "filter-options"],
    queryFn: () => fetchEApprovalFormsIndex({ per_page: 100, status: "published", sort: "name:asc" }),
    staleTime: 60_000,
  });
  const exportColumnsQuery = useQuery({
    queryKey: ["e-approval", "submissions", "export-columns", formId],
    queryFn: () => fetchEApprovalSubmissionsExportColumns(formId || undefined),
    enabled: Boolean(formId) && canExport,
    staleTime: 60_000,
  });
  const gridFields = exportColumnsQuery.data?.grids ?? [];
  const hasGrids = gridFields.length > 0;

  useEffect(() => {
    const nextStatus = searchParams.get("status");
    if (nextStatus && STATUS_FILTERS.includes(nextStatus as (typeof STATUS_FILTERS)[number])) {
      setStatus(nextStatus);
    }
    setFormId(searchParams.get("form_id") ?? "");
    setSubsidiary(searchParams.get("subsidiary") ?? "");
    setDepartment(searchParams.get("department") ?? "");
    setFrom(searchParams.get("from") ?? "");
    setTo(searchParams.get("to") ?? "");
    const mine = searchParams.get("mine");
    setMineOnly(mine === "1" || mine === "true");
    setPage(1);
  }, [searchParams]);

  useEffect(() => {
    setPage(1);
    setSelectedIds(new Set());
  }, [sort, formId, subsidiary, department, from, to, mineOnly, status]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch]);

  useEffect(() => {
    if (!hasGrids && exportLayout === "line_items") {
      setExportLayout("submissions");
    }
    if (hasGrids && !gridFieldId) {
      setGridFieldId(gridFields[0]?.key ?? "");
    }
  }, [exportLayout, gridFieldId, gridFields, hasGrids]);

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

  const searchLite = useMemo(
    () =>
      parseModuleListSearchLite(debouncedSearch, {
        status: ["all", "returned", "pending", "approved", "rejected", "cancelled", "draft"],
        subsidiary: "*",
        department: "*",
        title: "*",
        created: "*",
        created_at: "*",
      }),
    [debouncedSearch],
  );
  const tokenStatus = searchLite.filters.status;
  const effectiveStatus =
    status !== "all"
      ? status
      : tokenStatus && STATUS_FILTERS.includes(tokenStatus as (typeof STATUS_FILTERS)[number])
        ? tokenStatus
        : "all";
  const effectiveSubsidiary = subsidiary || searchLite.filters.subsidiary || "";
  const effectiveDepartment = department || searchLite.filters.department || "";
  const effectiveSearch = searchLite.search;

  const { data, isFetching, isError, error, refetch } = useQuery({
    queryKey: [
      "e-approval",
      "submissions",
      page,
      effectiveStatus,
      effectiveSearch,
      mineOnly,
      sort,
      formId,
      effectiveSubsidiary,
      effectiveDepartment,
      from,
      to,
    ],
    queryFn: () =>
      fetchEApprovalSubmissionsIndex({
        page,
        per_page: PER_PAGE,
        search: effectiveSearch || undefined,
        status: effectiveStatus === "all" ? undefined : effectiveStatus,
        mine: mineOnly || undefined,
        form_id: formId || undefined,
        subsidiary: effectiveSubsidiary || undefined,
        department: effectiveDepartment || undefined,
        from: from || undefined,
        to: to || undefined,
        sort,
      }),
  });

  // Total returned across all pages (banner count must not be current-page only).
  const returnedCountQuery = useQuery({
    queryKey: [
      "e-approval",
      "submissions",
      "returned-count",
      effectiveSearch,
      mineOnly,
      formId,
      effectiveSubsidiary,
      effectiveDepartment,
      from,
      to,
    ],
    queryFn: () =>
      fetchEApprovalSubmissionsIndex({
        page: 1,
        per_page: 1,
        search: effectiveSearch || undefined,
        status: "returned",
        mine: mineOnly || undefined,
        form_id: formId || undefined,
        subsidiary: effectiveSubsidiary || undefined,
        department: effectiveDepartment || undefined,
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
  const showReturnedBanner = status === "all" && returnedCount > 0;
  const subsidiaryOptions = metadataQuery.data?.subsidiaries ?? [];
  const departmentOptions = metadataQuery.data?.departments ?? [];
  const formOptions = formsQuery.data?.data ?? [];
  const hasAdvancedFilters = Boolean(formId || subsidiary || department || from || to);

  const clearAdvancedFilters = () => {
    setFormId("");
    setSubsidiary("");
    setDepartment("");
    setFrom("");
    setTo("");
    setPage(1);
  };

  const tableColumns = useMemo(
    () => [createRowSelectionColumn<EApprovalSubmissionListRow>(), ...eApprovalSubmissionTableColumns],
    [],
  );

  const normalizedData = useMemo(
    () =>
      applyPageEnhancements(
        emptyNormalizedData(),
        eApprovalSubmissionsEnhancements({ returnedCount }),
      ),
    [returnedCount],
  );

  const layoutMeta = useMemo(
    () => [
      { id: "alerts", label: "Needs revision alert" },
      { id: "filters", label: "Filters" },
      { id: "table", label: "Submissions list", hideable: false, removable: false },
    ],
    [],
  );

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    return [
      {
        id: "alerts",
        label: "Needs revision alert",
        defaultSpan: "full",
        render: () =>
          showReturnedBanner ? (
            <button
              type="button"
              className="w-full rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2 text-left text-sm text-amber-900 transition-colors hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200 dark:hover:bg-amber-950/50"
              onClick={() => {
                setStatus("returned");
                setPage(1);
              }}
            >
              {returnedCount} submission{returnedCount > 1 ? "s" : ""} need revision. Click to open
              the “Needs revision” filter.
            </button>
          ) : null,
      },
      {
        id: "filters",
        label: "Filters",
        defaultSpan: "full",
        render: () => (
          <div className="space-y-3">
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
            <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
              <div className="space-y-3">
                <div className="flex flex-wrap items-end justify-between gap-3">
                  <div data-help="ea-submissions-search" className="min-w-0 flex-1">
                    <label
                      className="mb-1 block text-xs font-medium text-muted-foreground"
                      htmlFor="e-approval-submissions-search"
                    >
                      Search submissions
                    </label>
                    <Input
                      id="e-approval-submissions-search"
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      placeholder="Search or status:pending title~invoice…"
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
                <div className="flex flex-wrap items-end gap-3" data-help="ea-submissions-advanced-filters">
                  <FilterSelect
                    id="submissions-form-filter"
                    label="Form"
                    value={formId}
                    onChange={(value) => {
                      setFormId(value);
                      setPage(1);
                    }}
                    className="w-[14rem]"
                  >
                    <option value="">All forms</option>
                    {formOptions.map((form) => (
                      <option key={form.id} value={form.id}>
                        {form.name}
                      </option>
                    ))}
                  </FilterSelect>
                  <FilterSelect
                    id="submissions-subsidiary-filter"
                    label="Subsidiary"
                    value={subsidiary}
                    onChange={(value) => {
                      setSubsidiary(value);
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
                    id="submissions-department-filter"
                    label="Department"
                    value={department}
                    onChange={(value) => {
                      setDepartment(value);
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
                  <div className="space-y-1.5">
                    <label className="block text-xs font-medium text-muted-foreground" htmlFor="submissions-from">
                      From
                    </label>
                    <DatePicker
                      id="submissions-from"
                      value={from}
                      onChange={(value) => {
                        setFrom(value);
                        setPage(1);
                      }}
                      className="h-9 w-[140px]"
                    />
                  </div>
                  <div className="space-y-1.5">
                    <label className="block text-xs font-medium text-muted-foreground" htmlFor="submissions-to">
                      To
                    </label>
                    <DatePicker
                      id="submissions-to"
                      value={to}
                      onChange={(value) => {
                        setTo(value);
                        setPage(1);
                      }}
                      className="h-9 w-[140px]"
                    />
                  </div>
                  {hasAdvancedFilters ? (
                    <Button type="button" size="sm" variant="ghost" className="h-9" onClick={clearAdvancedFilters}>
                      Clear filters
                    </Button>
                  ) : null}
                </div>
              </div>
            </div>
          </div>
        ),
      },
      {
        id: "table",
        label: "Submissions list",
        defaultSpan: "full",
        hideable: false,
        removable: false,
        render: () => (
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
            footer={
              meta ? (
                <PaginatedListFooter
                  meta={{ ...meta, current_page: page }}
                  onPageChange={setPage}
                  isPending={isFetching}
                />
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
                        <Button
                          className="mt-4"
                          size="sm"
                          onClick={() => window.location.assign("/e-approval/submissions/new")}
                        >
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
                    columns={tableColumns}
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
                    enableRowSelection
                    rowSelection={selectedIdsToRowSelection(selectedIds)}
                    onRowSelectionChange={(updater) =>
                      applyRowSelectionToSelectedIds(updater, selectedIds, setSelectedIds)
                    }
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
        ),
      },
    ];
  }, [
    canCreate,
    canViewAll,
    department,
    departmentOptions,
    error,
    formId,
    formOptions,
    from,
    hasAdvancedFilters,
    isEmpty,
    isError,
    isFetching,
    manualSorting,
    meta,
    mineOnly,
    onSortingChange,
    page,
    refetch,
    returnedCount,
    rows,
    search,
    searchParams,
    selectedIds,
    showReturnedBanner,
    sorting,
    status,
    subsidiary,
    subsidiaryOptions,
    tableColumns,
    to,
    viewMode,
  ]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const catalogBoardWidgets = useMemo(
    () =>
      buildDynamicBoardWidgets({
        moduleId: "e-approval",
        data: normalizedData,
        slots: boardWidgets,
        enabledIds: layout.enabledWidgetIds,
        titleOverrides,
        widgetOptions: layout.widgetOptions,
      }),
    [boardWidgets, layout.enabledWidgetIds, layout.widgetOptions, normalizedData, titleOverrides],
  );

  const bindableCatalog = useMemo(
    () => bindableCatalogEntries("e-approval", boardWidgets, normalizedData),
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
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsView]}>
      <div className="space-y-5">
        <LiveProductTourHost />
        <ConfigurableModulePageHeader
          defaults={E_APPROVAL_SUBMISSIONS_PAGE_CHROME}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout((current) => ({ ...current, pageChrome }))}
          renderActions={({ isVisible }) => (
            <>
              {isVisible("help") || isVisible("tour") ? (
                <EApprovalHelpEntryActions
                  showHelp={isVisible("help")}
                  showTour={isVisible("tour")}
                />
              ) : null}
              {isVisible("customize") ? (
                <DashboardLayoutToolbar
                  widgets={layoutMeta}
                  catalogModule="e-approval"
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
              {isVisible("export") ? (
                <ModuleListToolbar
                  showExport={canExport}
                  selectedCount={selectedIds.size}
                  onClearSelection={() => setSelectedIds(new Set())}
                  start={
                    canExport && formId && hasGrids ? (
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
                  onExport={
                    canExport
                      ? async (format) => {
                          try {
                            const ids = selectedIdsList(selectedIds);
                            const useLineItems =
                              exportLayout === "line_items" && hasGrids && Boolean(formId);
                            if (useLineItems && !formId) {
                              push({
                                level: "error",
                                title: "Form required",
                                message: "Select a form filter to export line items.",
                              });
                              return;
                            }
                            const result = await downloadEApprovalSubmissionsExport({
                              statuses: effectiveStatus === "all" ? undefined : [effectiveStatus],
                              form_id: formId || undefined,
                              from: from || undefined,
                              to: to || undefined,
                              search: effectiveSearch || undefined,
                              format,
                              viewer_scope: mineOnly ? "mine" : undefined,
                              subsidiary: effectiveSubsidiary || undefined,
                              department: effectiveDepartment || undefined,
                              columns: readVisibleExportColumnsFromStorage(
                                EA_SUBMISSIONS_COLUMN_KEY,
                                EA_SUBMISSIONS_DEFAULT_COLUMNS,
                                { map: EA_SUBMISSIONS_EXPORT_MAP },
                              ),
                              layout: useLineItems ? "line_items" : "submissions",
                              grid_field: useLineItems ? gridFieldId || undefined : undefined,
                              ids: ids.length > 0 ? ids : undefined,
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
                              `submissions-${new Date().toISOString().slice(0, 10)}.${format}`,
                            );
                            if (ids.length > 0) setSelectedIds(new Set());
                          } catch (e) {
                            push({
                              level: "error",
                              title: "Export failed",
                              message: getErrorMessage(e),
                            });
                          }
                        }
                      : undefined
                  }
                  onPrint={() => {
                    try {
                      const exportKeys =
                        readVisibleExportColumnsFromStorage(
                          EA_SUBMISSIONS_COLUMN_KEY,
                          EA_SUBMISSIONS_DEFAULT_COLUMNS,
                          { map: EA_SUBMISSIONS_EXPORT_MAP },
                        ) ?? [
                          "document_no",
                          "form_name",
                          "subsidiary",
                          "status",
                          "requestor_name",
                          "current_step",
                        ];
                      const allPrintCols = [
                        {
                          key: "document_no",
                          label: "Document",
                          value: (row: (typeof rows)[number]) => row.document_no,
                        },
                        {
                          key: "form_name",
                          label: "Form",
                          value: (row: (typeof rows)[number]) => row.form_name ?? "—",
                        },
                        {
                          key: "subsidiary",
                          label: "Subsidiary",
                          value: (row: (typeof rows)[number]) => row.subsidiary ?? "—",
                        },
                        {
                          key: "status",
                          label: "Status",
                          value: (row: (typeof rows)[number]) => row.status,
                        },
                        {
                          key: "requestor_name",
                          label: "Requestor",
                          value: (row: (typeof rows)[number]) => row.requestor?.name ?? "—",
                        },
                        {
                          key: "current_step",
                          label: "Step",
                          value: (row: (typeof rows)[number]) => String(row.current_step ?? "—"),
                        },
                      ];
                      const cols = allPrintCols.filter((col) => exportKeys.includes(col.key));
                      const effective = cols.length > 0 ? cols : allPrintCols;
                      printModuleListTable({
                        title: "E-Forms submissions",
                        subtitle: `${rows.length} row(s) on this page`,
                        columns: effective.map((c) => c.label),
                        rows: rows.map((row) => effective.map((c) => c.value(row))),
                      });
                    } catch (e) {
                      push({ level: "error", title: "Print failed", message: getErrorMessage(e) });
                    }
                  }}
                  onPrintAll={async () => {
                    try {
                      const exportKeys =
                        readVisibleExportColumnsFromStorage(
                          EA_SUBMISSIONS_COLUMN_KEY,
                          EA_SUBMISSIONS_DEFAULT_COLUMNS,
                          { map: EA_SUBMISSIONS_EXPORT_MAP },
                        ) ?? [
                          "document_no",
                          "form_name",
                          "subsidiary",
                          "status",
                          "requestor_name",
                          "current_step",
                        ];
                      const allPrintCols = [
                        {
                          key: "document_no",
                          label: "Document",
                          value: (row: (typeof rows)[number]) => row.document_no,
                        },
                        {
                          key: "form_name",
                          label: "Form",
                          value: (row: (typeof rows)[number]) => row.form_name ?? "—",
                        },
                        {
                          key: "subsidiary",
                          label: "Subsidiary",
                          value: (row: (typeof rows)[number]) => row.subsidiary ?? "—",
                        },
                        {
                          key: "status",
                          label: "Status",
                          value: (row: (typeof rows)[number]) => row.status,
                        },
                        {
                          key: "requestor_name",
                          label: "Requestor",
                          value: (row: (typeof rows)[number]) => row.requestor?.name ?? "—",
                        },
                        {
                          key: "current_step",
                          label: "Step",
                          value: (row: (typeof rows)[number]) => String(row.current_step ?? "—"),
                        },
                      ];
                      const cols = allPrintCols.filter((col) => exportKeys.includes(col.key));
                      const effective = cols.length > 0 ? cols : allPrintCols;
                      const result = await printAllOrQueuePrintableExport({
                        fetchPage: async (pageNumber, perPage) => {
                          const response = await fetchEApprovalSubmissionsIndex({
                            page: pageNumber,
                            per_page: perPage,
                            search: effectiveSearch || undefined,
                            status: effectiveStatus === "all" ? undefined : effectiveStatus,
                            form_id: formId || undefined,
                            from: from || undefined,
                            to: to || undefined,
                            mine: mineOnly || undefined,
                            subsidiary: effectiveSubsidiary || undefined,
                            department: effectiveDepartment || undefined,
                            sort,
                          });
                          return { data: response.data, meta: response.meta };
                        },
                        print: (printRows) => {
                          printModuleListTable({
                            title: "E-Forms submissions",
                            subtitle: `${printRows.length} filtered row(s)`,
                            columns: effective.map((c) => c.label),
                            rows: printRows.map((row) => effective.map((c) => c.value(row))),
                          });
                        },
                        queuePrintableExport: async () => {
                          const queued = await downloadEApprovalSubmissionsExport({
                            format: "html",
                            search: effectiveSearch || undefined,
                            statuses:
                              effectiveStatus === "all" ? undefined : [effectiveStatus],
                            form_id: formId || undefined,
                            from: from || undefined,
                            to: to || undefined,
                            viewer_scope: mineOnly ? "mine" : "all",
                            subsidiary: effectiveSubsidiary || undefined,
                            department: effectiveDepartment || undefined,
                            columns: exportKeys,
                            async: true,
                          });
                          if (queued.mode !== "async") {
                            throw new Error("Printable HTML export did not queue as expected.");
                          }
                          return {
                            message: `${queued.message} Open Recent exports when ready, then open the HTML file to print.`,
                          };
                        },
                      });
                      if (result.mode === "queued") {
                        push({
                          level: "info",
                          title: "Printable export queued",
                          message: result.message,
                        });
                      }
                    } catch (e) {
                      push({ level: "error", title: "Print failed", message: getErrorMessage(e) });
                    }
                  }}
                />
              ) : null}
              {isVisible("new") ? (
                canCreate ? (
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
                )
              ) : null}
            </>
          )}
        />

        <DashboardWidgetBoard
          widgets={catalogBoardWidgets}
          order={layout.widgetOrder}
          hiddenIds={layout.hiddenWidgetIds}
          enabledIds={
            layout.enabledWidgetIds.length > 0
              ? layout.enabledWidgetIds
              : layoutMeta.map((widget) => widget.id)
          }
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
