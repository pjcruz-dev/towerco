"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { Plus, RefreshCw } from "lucide-react";

import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { FilterSelect } from "@/components/forms/filter-select";
import { TicketingHelpEntryActions } from "@/components/help/ticketing-help-entry-actions";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { TicketingTourSampleNotice } from "@/components/help/ticketing-tour-fixtures";
import {
  createTicketingTicketsTableColumns,
  ticketingTicketsTableColumns,
} from "@/components/ticketing/ticketing-tickets-table-columns";
import { ModuleListToolbar } from "@/components/registry/module-list-toolbar";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { createRowSelectionColumn } from "@/components/ui/data-table-row-selection";
import { DataListCard } from "@/components/ui/data-list-card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import {
  downloadTicketingTicketsExport,
  fetchTicketingDashboard,
  fetchTicketingMetadata,
  fetchTicketingTickets,
} from "@/lib/api/modules/ticketing-api";
import { getErrorMessage } from "@/lib/api/error";
import {
  TICKETING_TOUR_SAMPLE_TICKET_ID,
  isTicketingTourActive,
  ticketingTourSampleListRow,
} from "@/lib/help/ticketing-tour-fixtures";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { emptyNormalizedData } from "@/lib/ui/dashboard-widget-data";
import { applyPageEnhancements, ticketingListEnhancements } from "@/lib/ui/page-enhancement-bags";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import { printModuleListTable, saveBlob } from "@/lib/ui/module-list-download";
import { printAllOrQueuePrintableExport } from "@/lib/ui/module-list-print-all";
import { parseModuleListSearchLite } from "@/lib/ui/module-list-search-lite";
import {
  applyRowSelectionToSelectedIds,
  selectedIdsList,
  selectedIdsToRowSelection,
} from "@/lib/ui/module-list-selection";
import { mapVisibleExportColumns } from "@/lib/ui/module-list-visible-columns";
import { TICKETING_TICKETS_PAGE_CHROME } from "@/lib/ui/page-chrome-config";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import type { TicketingTicketListRow } from "@/modules/ticketing/types";
import { useTicketingWorkspacePrefs } from "@/modules/ticketing/workspace-prefs";
import { useNotificationStore } from "@/stores/notification-store";
import { ticketingPrefsFromSearchParams } from "@/lib/ticketing/kpi-deep-links";

const DEFAULT_SORT = "updated_at:desc";
const LAYOUT_KEY = "toweros.ticketing.tickets.layout";

export function TicketingTicketsPageClient() {
  const searchParams = useSearchParams();
  const tourActive = isTicketingTourActive(searchParams);
  const push = useNotificationStore((s) => s.push);
  const { prefs, patchPrefs } = useTicketingWorkspacePrefs();
  const { status, category, priority, department, mineOnly, assignedMe, slaStatus, density } = prefs;
  const { layout, setLayout, tenantDefault, publishTenantDefault, resetToTenantDefault, flushPersonalPersist } =
    useDashboardLayoutPrefs(LAYOUT_KEY);
  const { editing, setEditing } = useDashboardCustomizeMode({ onExitEdit: flushPersonalPersist });
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set());
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["ticket_number", "title", "status", "priority", "updated_at"],
  });

  useEffect(() => {
    const fromUrl = ticketingPrefsFromSearchParams(searchParams);
    if (Object.keys(fromUrl).length === 0) return;
    patchPrefs(fromUrl);
  }, [searchParams, patchPrefs]);

  useEffect(() => {
    setPage(1);
    setSelectedIds(new Set());
  }, [sort, status, category, priority, department, mineOnly, assignedMe, slaStatus]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch]);

  const { data: metadata } = useQuery({
    queryKey: ["ticketing", "metadata"],
    queryFn: fetchTicketingMetadata,
    staleTime: 300_000,
  });

  const filterOptionsQuery = useQuery({
    queryKey: ["ticketing", "dashboard", "filter-options"],
    queryFn: () => fetchTicketingDashboard(),
    staleTime: 300_000,
  });
  const departmentOptions = filterOptionsQuery.data?.filter_options?.departments ?? [];

  const searchLite = useMemo(
    () =>
      parseModuleListSearchLite(debouncedSearch, {
        status: "*",
        priority: "*",
        sla_status: "*",
        category: "*",
        department: "*",
        title: "*",
        created: "*",
        created_at: "*",
        updated: "*",
      }),
    [debouncedSearch],
  );
  const effectiveStatus = status || searchLite.filters.status || "";
  const effectivePriority = priority || searchLite.filters.priority || "";
  const effectiveCategory = category || searchLite.filters.category || "";
  const effectiveDepartment = department || searchLite.filters.department || "";
  const effectiveSla = slaStatus || searchLite.filters.sla_status || "";
  const effectiveSearch = searchLite.search;

  const query = useQuery({
    queryKey: [
      "ticketing",
      "tickets",
      {
        search: effectiveSearch,
        status: effectiveStatus,
        category: effectiveCategory,
        priority: effectivePriority,
        department: effectiveDepartment,
        mineOnly,
        assignedMe,
        slaStatus: effectiveSla,
        page,
        sort,
      },
    ],
    queryFn: () =>
      fetchTicketingTickets({
        search: effectiveSearch || undefined,
        status: effectiveStatus || undefined,
        category: effectiveCategory || undefined,
        priority: effectivePriority || undefined,
        department: effectiveDepartment || undefined,
        mine: mineOnly || undefined,
        assigned_me: assignedMe || undefined,
        sla_status: effectiveSla || undefined,
        page,
        per_page: 20,
        sort,
      }),
  });

  const tickets = useMemo(() => {
    const rows = query.data?.data ?? [];
    if (!tourActive) {
      return rows;
    }
    if (rows.some((row) => row.id === TICKETING_TOUR_SAMPLE_TICKET_ID)) {
      return rows;
    }
    return [ticketingTourSampleListRow, ...rows];
  }, [query.data?.data, tourActive]);
  const columns = useMemo(
    () => [
      createRowSelectionColumn<TicketingTicketListRow>(),
      ...(tourActive
        ? createTicketingTicketsTableColumns({ tourQuery: searchParams.toString() })
        : ticketingTicketsTableColumns),
    ],
    [searchParams, tourActive],
  );
  const meta = query.data?.meta;
  const statusOptions = useMemo(() => metadata?.statuses ?? [], [metadata]);
  const priorityOptions = useMemo(() => metadata?.priorities ?? [], [metadata]);
  const categoryOptions = useMemo(
    () => metadata?.category_options ?? (metadata?.categories ?? []).map((id) => ({ id, label: id })),
    [metadata],
  );
  const hasFilters = Boolean(
    debouncedSearch || status || category || priority || department || mineOnly || assignedMe || slaStatus,
  );
  const emptyMessage = hasFilters ? "No tickets match your filters." : "No tickets yet.";

  const clearFilters = () => {
    setSearch("");
    patchPrefs({
      status: "",
      category: "",
      priority: "",
      department: "",
      mineOnly: false,
      assignedMe: false,
      slaStatus: "",
    });
    setPage(1);
  };

  const layoutMeta = useMemo(
    () => [
      { id: "filters", label: "Filters" },
      { id: "table", label: "Tickets table" },
    ],
    [],
  );

  const normalizedData = useMemo(
    () =>
      applyPageEnhancements(
        emptyNormalizedData(),
        ticketingListEnhancements({
          openCount: meta?.total,
        }),
      ),
    [meta?.total],
  );

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    return [
      {
        id: "filters",
        label: "Filters",
        defaultSpan: "full",
        render: () => (
          <div
            data-help="tk-tickets-filters"
            className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm"
          >
            <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search or status:open priority=high title~outage…"
                className="max-w-md"
              />
              <FilterSelect
                id="ticketing-status"
                label="Status"
                value={status}
                onChange={(value) => {
                  patchPrefs({ status: value });
                  setPage(1);
                }}
                className="w-full min-w-[10rem] sm:w-auto"
              >
                <option value="">All statuses</option>
                <option value="open,in_progress">Open / in progress</option>
                {statusOptions.map((item) => (
                  <option key={item} value={item}>
                    {item.replace(/_/g, " ")}
                  </option>
                ))}
              </FilterSelect>
              <FilterSelect
                id="ticketing-priority"
                label="Priority"
                value={priority}
                onChange={(value) => {
                  patchPrefs({ priority: value });
                  setPage(1);
                }}
                className="w-full min-w-[9rem] sm:w-auto"
              >
                <option value="">All priorities</option>
                {priorityOptions.map((item) => (
                  <option key={item} value={item}>
                    {item.replace(/_/g, " ")}
                  </option>
                ))}
              </FilterSelect>
              <FilterSelect
                id="ticketing-sla"
                label="SLA"
                value={slaStatus}
                onChange={(value) => {
                  patchPrefs({ slaStatus: value });
                  setPage(1);
                }}
                className="w-full min-w-[10rem] sm:w-auto"
              >
                <option value="">Any SLA</option>
                <option value="at_risk,breached">At risk / breached</option>
                <option value="at_risk">At risk</option>
                <option value="breached">Breached</option>
                <option value="on_track">On track</option>
              </FilterSelect>
              <FilterSelect
                id="ticketing-category"
                label="Category"
                value={category}
                onChange={(value) => {
                  patchPrefs({ category: value });
                  setPage(1);
                }}
                className="w-full min-w-[12rem] sm:w-auto"
              >
                <option value="">All categories</option>
                {categoryOptions.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.label}
                  </option>
                ))}
              </FilterSelect>
              {departmentOptions.length > 0 ? (
                <FilterSelect
                  id="ticketing-department"
                  label="Department"
                  value={department}
                  onChange={(value) => {
                    patchPrefs({ department: value });
                    setPage(1);
                  }}
                  className="w-full min-w-[11rem] sm:w-auto"
                >
                  <option value="">All departments</option>
                  {departmentOptions.map((code) => (
                    <option key={code} value={code}>
                      {code}
                    </option>
                  ))}
                </FilterSelect>
              ) : null}
              <div className="min-w-0 space-y-1.5" data-help="tk-tickets-scope">
                <p className="text-xs font-medium text-muted-foreground">Scope</p>
                <div className="inline-flex h-9 items-center gap-0.5 rounded-lg border border-input bg-card p-0.5 shadow-xs">
                  <button
                    type="button"
                    className={cn(
                      "h-8 rounded-md px-2.5 text-sm transition-colors",
                      mineOnly
                        ? "bg-muted font-medium text-foreground"
                        : "text-muted-foreground hover:bg-muted/50 hover:text-foreground",
                    )}
                    aria-pressed={mineOnly}
                    onClick={() => {
                      patchPrefs({
                        mineOnly: !mineOnly,
                        assignedMe: !mineOnly ? false : assignedMe,
                      });
                      setPage(1);
                    }}
                  >
                    My tickets
                  </button>
                  <button
                    type="button"
                    className={cn(
                      "h-8 rounded-md px-2.5 text-sm transition-colors",
                      assignedMe
                        ? "bg-muted font-medium text-foreground"
                        : "text-muted-foreground hover:bg-muted/50 hover:text-foreground",
                    )}
                    aria-pressed={assignedMe}
                    onClick={() => {
                      patchPrefs({
                        assignedMe: !assignedMe,
                        mineOnly: !assignedMe ? false : mineOnly,
                      });
                      setPage(1);
                    }}
                  >
                    Assigned to me
                  </button>
                </div>
              </div>
              {hasFilters ? (
                <Button type="button" size="sm" variant="ghost" className="h-8" onClick={clearFilters}>
                  Clear filters
                </Button>
              ) : null}
              <Button
                size="sm"
                variant="outline"
                type="button"
                onClick={() => query.refetch()}
                disabled={query.isFetching}
              >
                {query.isFetching ? <Spinner className="size-4" /> : <RefreshCw className="h-4 w-4" aria-hidden />}
              </Button>
            </div>
          </div>
        ),
      },
      {
        id: "table",
        label: "Tickets table",
        defaultSpan: "full",
        hideable: false,
        removable: false,
        render: () =>
          query.isError ? (
            <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm">
              <p className="font-medium text-destructive">Could not load tickets.</p>
              <Button size="sm" variant="outline" type="button" className="mt-2" onClick={() => query.refetch()}>
                Retry
              </Button>
            </div>
          ) : (
            <div data-help="tk-tickets-table">
              <DataListCard>
                {tourActive ? (
                  <div className="border-b border-border px-4 pt-4">
                    <TicketingTourSampleNotice />
                  </div>
                ) : null}
                <RegistryDataTableView
                  columns={columns}
                  data={tickets}
                  getRowId={(row) => row.id}
                  isLoading={query.isLoading || (query.isFetching && tickets.length === 0)}
                  isEmpty={!query.isLoading && tickets.length === 0}
                  emptyMessage={emptyMessage}
                  enableColumnVisibility
                  columnVisibilityStorageKey="toweros.table.columns.ticketing.tickets"
                  sorting={sorting}
                  onSortingChange={onSortingChange}
                  manualSorting={manualSorting}
                  enableRowSelection
                  rowSelection={selectedIdsToRowSelection(selectedIds)}
                  onRowSelectionChange={(updater) =>
                    applyRowSelectionToSelectedIds(updater, selectedIds, setSelectedIds)
                  }
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
                  toolbarEnd={({ visibleColumnIds }) => {
                    const allPrintCols = [
                      {
                        id: "ticket_number",
                        label: "Ticket",
                        value: (t: (typeof tickets)[number]) => t.ticket_number,
                      },
                      { id: "title", label: "Title", value: (t: (typeof tickets)[number]) => t.title },
                      { id: "status", label: "Status", value: (t: (typeof tickets)[number]) => t.status },
                      {
                        id: "priority",
                        label: "Priority",
                        value: (t: (typeof tickets)[number]) => t.priority,
                      },
                      {
                        id: "requester",
                        label: "Requester",
                        value: (t: (typeof tickets)[number]) => t.requester?.name ?? "—",
                      },
                      {
                        id: "assignee",
                        label: "Assignee",
                        value: (t: (typeof tickets)[number]) => t.assignee?.name ?? "Unassigned",
                      },
                      {
                        id: "updated_at",
                        label: "Updated",
                        value: (t: (typeof tickets)[number]) => t.updated_at ?? "—",
                      },
                    ];
                    const printCols = allPrintCols.filter((col) => visibleColumnIds.includes(col.id));
                    const cols = printCols.length > 0 ? printCols : allPrintCols;

                    return (
                      <ModuleListToolbar
                        selectedCount={selectedIds.size}
                        onClearSelection={() => setSelectedIds(new Set())}
                        onExport={async (format) => {
                          try {
                            const ids = selectedIdsList(selectedIds);
                            const result = await downloadTicketingTicketsExport({
                              search: effectiveSearch || undefined,
                              status: effectiveStatus || undefined,
                              category: effectiveCategory || undefined,
                              priority: effectivePriority || undefined,
                              department: effectiveDepartment || undefined,
                              mine: mineOnly || undefined,
                              assigned_me: assignedMe || undefined,
                              sla_status: effectiveSla || undefined,
                              format,
                              columns: mapVisibleExportColumns(visibleColumnIds),
                              ids: ids.length > 0 ? ids : undefined,
                            });
                            if (result.mode === "async") {
                              push({
                                level: "info",
                                title: "Export queued",
                                message: `${result.message} Open Settings → My exports when ready.`,
                              });
                              return;
                            }
                            saveBlob(
                              result.blob,
                              `ticketing-tickets-${new Date().toISOString().slice(0, 10)}.${format}`,
                            );
                            if (result.truncated) {
                              push({
                                level: "info",
                                title: "Export truncated",
                                message: `Downloaded ${result.maxRows.toLocaleString()} of ${result.totalRows.toLocaleString()} rows. Larger sets queue automatically.`,
                              });
                            }
                            if (ids.length > 0) setSelectedIds(new Set());
                          } catch (e) {
                            push({ level: "error", title: "Export failed", message: getErrorMessage(e) });
                          }
                        }}
                        onPrint={() => {
                          try {
                            printModuleListTable({
                              title: "Ticketing tickets",
                              subtitle: `${tickets.length} row(s) on this page`,
                              columns: cols.map((c) => c.label),
                              rows: tickets.map((ticket) => cols.map((c) => c.value(ticket))),
                            });
                          } catch (e) {
                            push({ level: "error", title: "Print failed", message: getErrorMessage(e) });
                          }
                        }}
                        onPrintAll={async () => {
                          try {
                            const result = await printAllOrQueuePrintableExport({
                              fetchPage: async (pageNumber, perPage) => {
                                const response = await fetchTicketingTickets({
                                  search: effectiveSearch || undefined,
                                  status: effectiveStatus || undefined,
                                  category: effectiveCategory || undefined,
                                  priority: effectivePriority || undefined,
                                  department: effectiveDepartment || undefined,
                                  mine: mineOnly || undefined,
                                  assigned_me: assignedMe || undefined,
                                  sla_status: effectiveSla || undefined,
                                  page: pageNumber,
                                  per_page: perPage,
                                  sort,
                                });
                                return { data: response.data, meta: response.meta };
                              },
                              print: (printRows) => {
                                printModuleListTable({
                                  title: "Ticketing tickets",
                                  subtitle: `${printRows.length} filtered row(s)`,
                                  columns: cols.map((c) => c.label),
                                  rows: printRows.map((ticket) => cols.map((c) => c.value(ticket))),
                                });
                              },
                              queuePrintableExport: async () => {
                                const queued = await downloadTicketingTicketsExport({
                                  search: effectiveSearch || undefined,
                                  status: effectiveStatus || undefined,
                                  category: effectiveCategory || undefined,
                                  priority: effectivePriority || undefined,
                                  department: effectiveDepartment || undefined,
                                  mine: mineOnly || undefined,
                                  assigned_me: assignedMe || undefined,
                                  sla_status: effectiveSla || undefined,
                                  format: "html",
                                  columns: mapVisibleExportColumns(visibleColumnIds),
                                  async: true,
                                });
                                if (queued.mode !== "async") {
                                  throw new Error("Printable HTML export did not queue as expected.");
                                }
                                return { message: queued.message };
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
                    );
                  }}
                />
                {meta && meta.last_page > 1 ? (
                  <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={query.isFetching} />
                ) : null}
              </DataListCard>
            </div>
          ),
      },
    ];
  }, [
    assignedMe,
    category,
    categoryOptions,
    columns,
    density,
    department,
    departmentOptions,
    effectiveCategory,
    effectiveDepartment,
    effectivePriority,
    effectiveSearch,
    effectiveSla,
    effectiveStatus,
    emptyMessage,
    hasFilters,
    meta,
    mineOnly,
    manualSorting,
    onSortingChange,
    patchPrefs,
    priority,
    priorityOptions,
    push,
    query,
    search,
    selectedIds,
    slaStatus,
    sort,
    sorting,
    status,
    statusOptions,
    tickets,
    tourActive,
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
        moduleId: "ticketing",
        data: normalizedData,
        slots: boardWidgets,
        enabledIds: layout.enabledWidgetIds,
        titleOverrides,
        widgetOptions: layout.widgetOptions,
      }),
    [boardWidgets, layout.enabledWidgetIds, layout.widgetOptions, normalizedData, titleOverrides],
  );

  const bindableCatalog = useMemo(
    () => bindableCatalogEntries("ticketing", boardWidgets, normalizedData),
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
    <PermissionGate requiredPermissions={[permissions.ticketingView]}>
      <div className="space-y-6">
        <LiveProductTourHost />
        <ConfigurableModulePageHeader
          defaults={TICKETING_TICKETS_PAGE_CHROME}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout((current) => ({ ...current, pageChrome }))}
          renderActions={({ isVisible }) => (
            <>
              {isVisible("help") || isVisible("tour") ? (
                <TicketingHelpEntryActions showHelp={isVisible("help")} showTour={isVisible("tour")} />
              ) : null}
              {isVisible("customize") ? (
                <DashboardLayoutToolbar
                  widgets={layoutMeta}
                  catalogModule="ticketing"
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
              {isVisible("new") ? (
                <Button
                  size="sm"
                  data-help="tk-tickets-new"
                  data-tour-nav="/ticketing/tickets/new"
                  render={<Link href="/ticketing/tickets/new" />}
                >
                  <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                  New ticket
                </Button>
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
