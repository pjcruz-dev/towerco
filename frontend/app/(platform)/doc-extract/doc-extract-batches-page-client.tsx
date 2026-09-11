"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, FileScan, Plus, ScrollText, Search } from "lucide-react";

import {
  buildDocExtractBatchTableColumns,
  docExtractBatchPrintRows,
} from "@/components/doc-extract/doc-extract-batch-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { DocExtractHelpEntryActions } from "@/components/help/doc-extract-help-entry-actions";
import { DocExtractTourSoftPrompt } from "@/components/help/doc-extract-tour-soft-prompt";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardBoardSkeleton } from "@/components/dashboard/dashboard-board-skeleton";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { ModuleListToolbar } from "@/components/registry/module-list-toolbar";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { createRowSelectionColumn } from "@/components/ui/data-table-row-selection";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import {
  downloadDocExtractBatchExport,
  downloadDocExtractBatchesExport,
  fetchDocExtractBatches,
  requeueDocExtractBatch,
} from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { normalizeDocExtractBatches } from "@/lib/ui/normalize-dashboard-data";
import { printModuleListTable, saveBlob } from "@/lib/ui/module-list-download";
import { printAllOrQueuePrintableExport } from "@/lib/ui/module-list-print-all";
import { parseModuleListSearchLite } from "@/lib/ui/module-list-search-lite";
import {
  applyRowSelectionToSelectedIds,
  selectedIdsList,
  selectedIdsToRowSelection,
} from "@/lib/ui/module-list-selection";
import { mapVisibleExportColumns } from "@/lib/ui/module-list-visible-columns";
import { DOC_EXTRACT_BATCHES_PAGE_CHROME, resolvePageChrome } from "@/lib/ui/page-chrome-config";
import { syncHeroWithPageChrome } from "@/lib/ui/sync-hero-with-page-chrome";
import type { DocExtractBatchListRow } from "@/modules/doc-extract/types";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type StatusFilter = "all" | "processing" | "ready" | "failed";

const PER_PAGE = 20;
const COLUMN_VISIBILITY_KEY = "toweros.table.columns.doc-extract.batches";

const DOC_EXTRACT_EXPORT_COLUMN_MAP: Record<string, string | string[] | null> = {
  created_at: "created_at",
  primary_filename: ["primary_filename", "file_count"],
  template_name: ["template_name", "mode"],
  status: "status",
  progress: ["document_count", "ready_count", "failed_count"],
  message: "message",
  actions: null,
  select: null,
};

export function DocExtractBatchesPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const scopedUser = user ? { ...user, permissions: effectivePermissions() } : null;
  const canManageTemplates = hasPermission(scopedUser, [permissions.docExtractTemplatesManage]);
  const canRun = hasPermission(scopedUser, [permissions.docExtractRun]);
  const canExport = hasPermission(scopedUser, [permissions.docExtractExport]);

  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [requeueBatchId, setRequeueBatchId] = useState<string | null>(null);
  const [downloadBatchId, setDownloadBatchId] = useState<string | null>(null);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set());
  const { editing, setEditing } = useDashboardCustomizeMode();
  const { layout, setLayout, tenantDefault, publishTenantDefault, resetToTenantDefault } = useDashboardLayoutPrefs("toweros.doc-extract.dashboard.layout");
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: "created_at:desc",
    sortableColumnIds: ["created_at", "primary_filename", "template_name", "status"],
  });

  useEffect(() => {
    setPage(1);
    setSelectedIds(new Set());
  }, [statusFilter, sort]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch]);

  const searchLite = useMemo(
    () =>
      parseModuleListSearchLite(debouncedSearch, {
        status: ["all", "processing", "ready", "failed", "pending"],
        filename: "*",
        file: "*",
        created: "*",
        created_at: "*",
      }),
    [debouncedSearch],
  );
  const tokenStatus = searchLite.filters.status;
  const effectiveStatus: StatusFilter =
    statusFilter !== "all"
      ? statusFilter
      : tokenStatus === "processing" ||
          tokenStatus === "ready" ||
          tokenStatus === "failed" ||
          tokenStatus === "all"
        ? tokenStatus
        : tokenStatus === "pending"
          ? "processing"
          : "all";
  const effectiveSearch = searchLite.search;

  const query = useQuery({
    queryKey: ["doc-extract", "batches", { page, statusFilter: effectiveStatus, search: effectiveSearch, sort }],
    queryFn: () =>
      fetchDocExtractBatches({
        page,
        per_page: PER_PAGE,
        status: effectiveStatus,
        search: effectiveSearch || undefined,
        sort,
      }),
    refetchInterval: (state) => {
      const list = state.state.data?.data ?? [];
      return list.some((row) => row.status === "processing" || row.status === "pending") ? 4000 : false;
    },
  });

  const requeueMutation = useMutation({
    mutationFn: requeueDocExtractBatch,
    onMutate: (batchId) => setRequeueBatchId(batchId),
    onSuccess: async (result) => {
      notify({
        level: "success",
        title: "Scans requeued",
        message:
          result.requeued > 0
            ? `${result.requeued} document(s) sent back to the OCR queue.`
            : "No pending documents needed a retry.",
      });
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batches"] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not requeue", message: getErrorMessage(error) });
    },
    onSettled: () => setRequeueBatchId(null),
  });

  const downloadMutation = useMutation({
    mutationFn: ({ batchId, format }: { batchId: string; format: "csv" | "xlsx" }) =>
      downloadDocExtractBatchExport(batchId, format),
    onMutate: ({ batchId }) => setDownloadBatchId(batchId),
    onSuccess: (blob, { batchId, format }) => {
      saveBlob(blob, `extraction-results-${batchId}.${format}`);
      notify({ level: "success", title: "Download started" });
    },
    onError: (error) => {
      notify({ level: "error", title: "Download failed", message: getErrorMessage(error) });
    },
    onSettled: () => setDownloadBatchId(null),
  });

  const rows = query.data?.data ?? [];
  const meta = query.data?.meta;
  const counts = meta?.status_counts;
  const summary = {
    total: counts?.all ?? meta?.total ?? rows.length,
    processing: counts?.processing ?? 0,
    ready: counts?.ready ?? 0,
    failed: counts?.failed ?? 0,
  };

  const filters: { id: StatusFilter; label: string; count: number }[] = [
    { id: "all", label: "All", count: summary.total },
    { id: "processing", label: "Scanning", count: summary.processing },
    { id: "ready", label: "Ready", count: summary.ready },
    { id: "failed", label: "Failed", count: summary.failed },
  ];

  const layoutMeta = useMemo(
    () => [
      { id: "alerts", label: "Status alerts" },
      { id: "filters", label: "Filters" },
      { id: "batch_list", label: "Extracted batches" },
    ],
    [],
  );

  const batchColumns = useMemo(
    () => [
      createRowSelectionColumn<DocExtractBatchListRow>(),
      ...buildDocExtractBatchTableColumns({
        canRun,
        canExport,
        requeueBatchId,
        requeuePending: requeueMutation.isPending,
        downloadBatchId,
        onRequeue: (batchId) => requeueMutation.mutate(batchId),
        onDownload: (batchId, format) => downloadMutation.mutate({ batchId, format }),
      }),
    ],
    [canExport, canRun, downloadBatchId, downloadMutation, requeueBatchId, requeueMutation],
  );

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    return [
      {
        id: "alerts",
        label: "Status alerts",
        render: () => (
          <div className="space-y-3">
            {summary.processing > 0 ? (
              <button
                type="button"
                onClick={() => {
                  setStatusFilter("processing");
                  setPage(1);
                }}
                className="flex w-full items-center gap-3 rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 text-left text-sm text-sky-900 shadow-sm transition-colors hover:bg-sky-50 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100 dark:hover:bg-sky-950/50"
              >
                <Spinner className="size-4 shrink-0" />
                <span className="font-medium">
                  {summary.processing} extraction{summary.processing === 1 ? "" : "s"} scanning
                </span>
                <span className="text-sky-700/80 dark:text-sky-300/80">Click to filter · auto-refreshes</span>
              </button>
            ) : null}

            {summary.failed > 0 && statusFilter !== "failed" ? (
              <button
                type="button"
                onClick={() => {
                  setStatusFilter("failed");
                  setPage(1);
                }}
                className="flex w-full items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-left text-sm text-amber-950 shadow-sm transition-colors hover:bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100 dark:hover:bg-amber-950/50"
              >
                <AlertTriangle className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <span className="font-medium">
                  {summary.failed} extraction{summary.failed === 1 ? "" : "s"} failed
                </span>
                <span className="text-amber-800/70 dark:text-amber-200/70">Review and retry from the batch</span>
              </button>
            ) : null}

            {summary.processing === 0 && (summary.failed === 0 || statusFilter === "failed") ? (
              <p className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground">
                No active scan or failure alerts right now.
              </p>
            ) : null}
          </div>
        ),
      },
      {
        id: "filters",
        label: "Filters",
        render: () => (
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap gap-1.5">
              {filters.map((filter) => {
                const active = statusFilter === filter.id;
                return (
                  <Button
                    key={filter.id}
                    type="button"
                    size="sm"
                    variant={active ? "default" : "outline"}
                    className={cn("h-8 gap-1.5", !active && "bg-card")}
                    onClick={() => {
                      setStatusFilter(filter.id);
                      setPage(1);
                    }}
                  >
                    {filter.label}
                    <span
                      className={cn(
                        "rounded px-1.5 py-0 text-[11px] tabular-nums",
                        active ? "bg-primary-foreground/15 text-primary-foreground" : "bg-muted text-muted-foreground",
                      )}
                    >
                      {filter.count}
                    </span>
                  </Button>
                );
              })}
            </div>
            <div className="relative w-full sm:max-w-xs">
              <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search or status:ready …"
                className="h-8 pl-8"
                aria-label="Search extractions"
              />
            </div>
          </div>
        ),
      },
      {
        id: "batch_list",
        label: "Extracted batches",
        render: () => (
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm" data-help="dx-list-toolkit">
            <RegistryDataTableView
              columns={batchColumns}
              data={rows}
              getRowId={(row) => row.id}
              isLoading={query.isLoading || (query.isFetching && rows.length === 0)}
              isEmpty={!query.isLoading && rows.length === 0}
              emptyMessage="No extractions match this filter."
              enableColumnVisibility
              columnVisibilityStorageKey={COLUMN_VISIBILITY_KEY}
              sorting={sorting}
              onSortingChange={onSortingChange}
              manualSorting={manualSorting}
              enableRowSelection
              rowSelection={selectedIdsToRowSelection(selectedIds)}
              onRowSelectionChange={(updater) =>
                applyRowSelectionToSelectedIds(updater, selectedIds, setSelectedIds)
              }
              getRowClassName={(row) =>
                row.original.status === "processing" || row.original.status === "pending"
                  ? "bg-sky-50/40 dark:bg-sky-950/20"
                  : undefined
              }
              toolbarEnd={({ visibleColumnIds }) => (
                <ModuleListToolbar
                  selectedCount={selectedIds.size}
                  onClearSelection={() => setSelectedIds(new Set())}
                  onExport={async (format) => {
                    try {
                      const ids = selectedIdsList(selectedIds);
                      const result = await downloadDocExtractBatchesExport({
                        format,
                        status: effectiveStatus,
                        search: effectiveSearch,
                        sort,
                        columns: mapVisibleExportColumns(visibleColumnIds, {
                          map: DOC_EXTRACT_EXPORT_COLUMN_MAP,
                        }),
                        ids: ids.length > 0 ? ids : undefined,
                      });
                      if (result.mode === "async") {
                        notify({
                          level: "info",
                          title: "Export queued",
                          message: `${result.message} Open Settings → My exports when ready.`,
                        });
                        return;
                      }
                      saveBlob(
                        result.blob,
                        `doc-extract-batches-${new Date().toISOString().slice(0, 10)}.${format}`,
                      );
                      if (result.truncated) {
                        notify({
                          level: "info",
                          title: "Export truncated",
                          message: `Downloaded ${result.maxRows.toLocaleString()} of ${result.totalRows.toLocaleString()} rows. Re-export will queue when over the sync limit.`,
                        });
                      }
                      if (ids.length > 0) setSelectedIds(new Set());
                    } catch (error) {
                      notify({
                        level: "error",
                        title: "Export failed",
                        message: getErrorMessage(error),
                      });
                    }
                  }}
                  onPrint={() => {
                    try {
                      const printable = docExtractBatchPrintRows(rows, visibleColumnIds);
                      printModuleListTable({
                        title: "DocExtract batches",
                        subtitle: `${rows.length} row(s) on this page · ${meta?.total ?? rows.length} filtered`,
                        columns: printable.columns,
                        rows: printable.rows,
                      });
                    } catch (error) {
                      notify({
                        level: "error",
                        title: "Print failed",
                        message: getErrorMessage(error),
                      });
                    }
                  }}
                  onPrintAll={async () => {
                    try {
                      const result = await printAllOrQueuePrintableExport({
                        fetchPage: async (pageNumber, perPage) => {
                          const response = await fetchDocExtractBatches({
                            page: pageNumber,
                            per_page: perPage,
                            status: effectiveStatus === "all" ? undefined : effectiveStatus,
                            search: effectiveSearch || undefined,
                            sort,
                          });
                          return { data: response.data, meta: response.meta };
                        },
                        print: (printRows) => {
                          const printable = docExtractBatchPrintRows(printRows, visibleColumnIds);
                          printModuleListTable({
                            title: "DocExtract batches",
                            subtitle: `${printRows.length} filtered row(s)`,
                            columns: printable.columns,
                            rows: printable.rows,
                          });
                        },
                        queuePrintableExport: async () => {
                          const queued = await downloadDocExtractBatchesExport({
                            format: "html",
                            status: effectiveStatus === "all" ? undefined : effectiveStatus,
                            search: effectiveSearch || undefined,
                            sort,
                            columns: mapVisibleExportColumns(visibleColumnIds, {
                              map: DOC_EXTRACT_EXPORT_COLUMN_MAP,
                            }),
                            async: true,
                          });
                          if (queued.mode !== "async") {
                            throw new Error("Printable HTML export did not queue as expected.");
                          }
                          return { message: queued.message };
                        },
                      });
                      if (result.mode === "queued") {
                        notify({
                          level: "info",
                          title: "Printable export queued",
                          message: result.message,
                        });
                      }
                    } catch (error) {
                      notify({
                        level: "error",
                        title: "Print failed",
                        message: getErrorMessage(error),
                      });
                    }
                  }}
                />
              )}
            />
            {meta && meta.last_page > 1 ? (
              <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={query.isFetching} />
            ) : (
              <div className="border-t border-border px-4 py-2 text-right text-[11px] text-muted-foreground">
                {meta?.total ?? rows.length} batch{(meta?.total ?? rows.length) === 1 ? "" : "es"}
              </div>
            )}
          </div>
        ),
      },
    ];
  }, [
    batchColumns,
    debouncedSearch,
    filters,
    meta,
    notify,
    page,
    query.isFetching,
    query.isLoading,
    rows,
    sort,
    sorting,
    onSortingChange,
    manualSorting,
    statusFilter,
    summary.failed,
    summary.processing,
  ]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const normalizedData = useMemo(() => {
    const base = normalizeDocExtractBatches(rows);
    const chrome = resolvePageChrome(DOC_EXTRACT_BATCHES_PAGE_CHROME, layout.pageChrome);
    return syncHeroWithPageChrome(base, chrome);
  }, [layout.pageChrome, rows]);

  const catalogBoardWidgets = useMemo(
    () =>
      buildDynamicBoardWidgets({
        moduleId: "doc-extract",
        data: normalizedData,
        slots: boardWidgets,
        enabledIds: layout.enabledWidgetIds,
        titleOverrides,
        widgetOptions: layout.widgetOptions,
      }),
    [boardWidgets, layout.enabledWidgetIds, layout.widgetOptions, normalizedData, titleOverrides],
  );

  const bindableCatalog = useMemo(
    () => bindableCatalogEntries("doc-extract", boardWidgets, normalizedData),
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
    <PermissionGate requiredPermissions={[permissions.docExtractView]}>
      <div className="w-full space-y-5" data-help="dx-batches-page">
        <LiveProductTourHost />

        <ConfigurableModulePageHeader
          defaults={DOC_EXTRACT_BATCHES_PAGE_CHROME}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout({ ...layout, pageChrome })}
          actionsById={{
            help: <DocExtractHelpEntryActions showHelp showTour={false} />,
            tour: <DocExtractHelpEntryActions showHelp={false} showTour />,
            customize: (
              <DashboardLayoutToolbar
                widgets={layoutMeta}
                catalogModule="doc-extract"
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
            ),
            templates: canManageTemplates ? (
              <Button size="sm" variant="outline" render={<Link href="/doc-extract/templates" />}>
                <ScrollText className="size-4" />
                Templates
              </Button>
            ) : null,
            new: canRun ? (
              <Button size="sm" data-help="dx-new-batch" render={<Link href="/doc-extract/new" />}>
                <Plus className="size-4" />
                New extraction
              </Button>
            ) : null,
          }}
        />

        <DocExtractTourSoftPrompt />

        {query.isLoading ? (
          <DashboardBoardSkeleton
            layout={layout}
            defaultEnabledIds={layoutMeta.map((widget) => widget.id)}
          />
        ) : query.isError ? (
          <div className="rounded-xl border border-border bg-card p-6 text-sm text-destructive shadow-sm">
            {getErrorMessage(query.error)}
            <Button type="button" variant="outline" size="sm" className="mt-3" onClick={() => query.refetch()}>
              Retry
            </Button>
          </div>
        ) : summary.total === 0 && statusFilter === "all" && !debouncedSearch.trim() ? (
          <div className="rounded-xl border border-dashed border-border bg-card p-10 text-center shadow-sm">
            <FileScan className="mx-auto size-8 text-muted-foreground" />
            <p className="mt-3 text-sm font-medium text-foreground">No extractions yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
              Start with PDFs or images, arrange pages in Consolidate, then review and export.
            </p>
            {canRun ? (
              <Button size="sm" className="mt-4" render={<Link href="/doc-extract/new" />}>
                <Plus className="size-4" />
                New extraction
              </Button>
            ) : null}
          </div>
        ) : (
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
        )}
      </div>
    </PermissionGate>
  );
}
