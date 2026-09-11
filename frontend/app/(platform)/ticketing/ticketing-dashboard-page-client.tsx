"use client";

import Link from "next/link";
import { useEffect, useMemo, useRef } from "react";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { ArrowRight, LifeBuoy, Plus, Ticket } from "lucide-react";

import { DashboardBoardSkeleton } from "@/components/dashboard/dashboard-board-skeleton";
import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { countBy, DASHBOARD_CHART, kpiSeries } from "@/components/dashboard/dashboard-chart-utils";
import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { FilterSelect } from "@/components/forms/filter-select";
import {
  TicketingTourCompleteAnchor,
  TicketingTourOverviewRecentFixtures,
} from "@/components/help/ticketing-tour-fixtures";
import { TicketingTourSoftPrompt } from "@/components/help/ticketing-tour-soft-prompt";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { TicketingHelpEntryActions } from "@/components/help/ticketing-help-entry-actions";
import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import { formatTicketingDate } from "@/components/ticketing/ticketing-utils";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import { useTicketingDashboard } from "@/hooks/use-ticketing-dashboard";
import { fetchTicketingMetadata } from "@/lib/api/modules/ticketing-api";
import { getErrorMessage } from "@/lib/api/error";
import { isTicketingTourActive } from "@/lib/help/ticketing-tour-fixtures";
import { permissions } from "@/lib/rbac/permissions";
import {
  normalizeDashboardLayoutPrefs,
  type DashboardWidgetDef,
} from "@/lib/ui/dashboard-widget-registry";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { normalizeTicketingDashboard } from "@/lib/ui/normalize-dashboard-data";
import { resolvePageChrome, TICKETING_DASHBOARD_PAGE_CHROME } from "@/lib/ui/page-chrome-config";
import { syncHeroWithPageChrome } from "@/lib/ui/sync-hero-with-page-chrome";
import { cn } from "@/lib/utils";
import {
  expandTicketingDashboardWidgetIds,
  TICKETING_DASHBOARD_WIDGETS,
  useTicketingWorkspacePrefs,
} from "@/modules/ticketing/workspace-prefs";

const DASHBOARD_LAYOUT_KEY = "toweros.ticketing.dashboard.layout";
const TICKETING_DASHBOARD_DEFAULT_IDS = TICKETING_DASHBOARD_WIDGETS.map((widget) => widget.id);

const NAV_TILES = [
  {
    href: "/ticketing/tickets",
    label: "All tickets",
    description: "Search, filter, and manage the queue.",
    icon: Ticket,
  },
  {
    href: "/ticketing/tickets/new",
    label: "Report an issue",
    description: "Describe the problem and attach screenshots.",
    icon: LifeBuoy,
  },
] as const;
export function TicketingDashboardPageClient() {
  const searchParams = useSearchParams();
  const tourActive = isTicketingTourActive(searchParams);
  const { prefs, patchPrefs, layout: legacyLayout } = useTicketingWorkspacePrefs();
  const {
    layout,
    setLayout,
    tenantDefault,
    publishTenantDefault,
    resetToTenantDefault,
    serverReady,
    flushPersonalPersist,
  } = useDashboardLayoutPrefs(DASHBOARD_LAYOUT_KEY);
  const { editing, setEditing } = useDashboardCustomizeMode({ onExitEdit: flushPersonalPersist });
  const { status, category, priority, department, mineOnly, assignedMe } = prefs;
  const seededLayoutRef = useRef(false);
  const expandedBundlesRef = useRef(false);

  useEffect(() => {
    if (!serverReady || seededLayoutRef.current) return;
    seededLayoutRef.current = true;
    const empty =
      layout.enabledWidgetIds.length === 0 &&
      layout.widgetOrder.length === 0 &&
      Object.keys(layout.spans).length === 0 &&
      Object.keys(layout.widgetOptions).length === 0;
    const legacy = normalizeDashboardLayoutPrefs(legacyLayout);
    const hasLegacy =
      legacy.enabledWidgetIds.length > 0 ||
      legacy.widgetOrder.length > 0 ||
      Object.keys(legacy.spans).length > 0 ||
      Object.keys(legacy.widgetOptions).length > 0 ||
      Object.keys(legacy.pageChrome ?? {}).length > 0;
    if (empty && hasLegacy) {
      setLayout({
        ...legacy,
        enabledWidgetIds: expandTicketingDashboardWidgetIds(legacy.enabledWidgetIds),
        widgetOrder: expandTicketingDashboardWidgetIds(legacy.widgetOrder),
      });
    }
  }, [legacyLayout, layout, serverReady, setLayout]);

  // One-time: split legacy Analytics / Category bundles into separate widgets.
  useEffect(() => {
    if (!serverReady || expandedBundlesRef.current) return;
    const needsExpand =
      layout.enabledWidgetIds.includes("queue_charts") ||
      layout.enabledWidgetIds.includes("category_analytics") ||
      layout.widgetOrder.includes("queue_charts") ||
      layout.widgetOrder.includes("category_analytics");
    if (!needsExpand) {
      expandedBundlesRef.current = true;
      return;
    }
    expandedBundlesRef.current = true;
    const enabled = expandTicketingDashboardWidgetIds(
      layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : TICKETING_DASHBOARD_DEFAULT_IDS,
    );
    const order = expandTicketingDashboardWidgetIds(
      layout.widgetOrder.length > 0 ? layout.widgetOrder : enabled,
    );
    const spans = { ...layout.spans };
    delete spans.queue_charts;
    delete spans.category_analytics;
    if (!spans.chart_ticket_queue) spans.chart_ticket_queue = "half";
    if (!spans.chart_by_priority) spans.chart_by_priority = "half";
    if (!spans.chart_by_department) spans.chart_by_department = "full";
    if (!spans.chart_by_category) spans.chart_by_category = "half";
    if (!spans.table_category_analytics) spans.table_category_analytics = "half";
    setLayout({
      ...layout,
      enabledWidgetIds: enabled,
      widgetOrder: order,
      spans,
    });
  }, [layout, serverReady, setLayout]);

  const { data, isFetching, isError, error, isPlaceholderData, refetch } = useTicketingDashboard({
    status: status || undefined,
    category: category || undefined,
    priority: priority || undefined,
    department: department || undefined,
    mine: mineOnly || undefined,
    assigned_me: assignedMe || undefined,
  });
  const showSkeleton = isFetching && isPlaceholderData;

  const { data: metadata } = useQuery({
    queryKey: ["ticketing", "metadata"],
    queryFn: fetchTicketingMetadata,
    staleTime: 300_000,
  });

  const statusOptions = metadata?.statuses ?? [];
  const priorityOptions = metadata?.priorities ?? [];
  const categoryOptions =
    metadata?.category_options ?? (metadata?.categories ?? []).map((id) => ({ id, label: id }));
  const departmentOptions = data?.filter_options?.departments ?? [];
  const hasFilters = Boolean(status || category || priority || department || mineOnly || assignedMe);

  const queueSeries = useMemo(
    () =>
      kpiSeries(data?.kpis ?? [], ["open", "assigned_me", "urgent", "sla_at_risk", "resolved_week"]).filter(
        (row) => row.value > 0,
      ),
    [data?.kpis],
  );

  const prioritySeries = useMemo(() => {
    const fromApi = data?.priority_breakdown ?? [];
    if (fromApi.length > 0) {
      return fromApi.map((row) => ({
        key: row.key,
        label: row.label,
        value: row.count,
        fill:
          row.key === "urgent"
            ? DASHBOARD_CHART.danger
            : row.key === "high"
              ? DASHBOARD_CHART.warning
              : row.key === "normal"
                ? DASHBOARD_CHART.brand
                : DASHBOARD_CHART.muted,
      }));
    }
    return countBy(
      data?.recent_tickets ?? [],
      (ticket) => ticket.priority,
      (key) => key.charAt(0).toUpperCase() + key.slice(1),
    ).map((row) => ({
      ...row,
      fill:
        row.key === "urgent"
          ? DASHBOARD_CHART.danger
          : row.key === "high"
            ? DASHBOARD_CHART.warning
            : row.key === "normal"
              ? DASHBOARD_CHART.brand
              : DASHBOARD_CHART.muted,
    }));
  }, [data?.priority_breakdown, data?.recent_tickets]);

  const categorySeries = useMemo(
    () =>
      (data?.by_category ?? [])
        .map((row) => ({
          key: row.category ?? "uncategorized",
          label: row.label,
          value: row.open + row.in_progress + row.resolved_7d,
          fill: DASHBOARD_CHART.brand,
        }))
        .filter((row) => row.value > 0)
        .slice(0, 8),
    [data?.by_category],
  );

  const departmentSeries = useMemo(
    () =>
      (data?.department_breakdown ?? []).map((row, index) => ({
        key: row.key,
        label: row.label,
        value: row.count,
        fill: [DASHBOARD_CHART.brand, DASHBOARD_CHART.brandSoft, DASHBOARD_CHART.sky, DASHBOARD_CHART.muted][
          index % 4
        ],
      })),
    [data?.department_breakdown],
  );

  const layoutMeta = useMemo(
    () => TICKETING_DASHBOARD_WIDGETS.map((widget) => ({ id: widget.id, label: widget.label })),
    [],
  );

  const boundWidgets = useMemo((): DashboardWidgetDef[] => {
    return [
      {
        id: "chart_ticket_queue",
        label: "Ticket queue",
        defaultSpan: "half",
        render: () => (
          <DashboardBarChart
            title="Ticket queue"
            description="Open, assigned, urgent, and resolved this week"
            data={queueSeries}
            layout="horizontal"
            emptyMessage="No ticket KPIs to chart."
            height={200}
          />
        ),
      },
      {
        id: "chart_by_priority",
        label: "By priority",
        defaultSpan: "half",
        render: () => (
          <DashboardDonutChart
            title="By priority"
            description="Priority mix for filtered tickets"
            data={prioritySeries}
            emptyMessage="No priority data to chart."
            height={200}
          />
        ),
      },
      {
        id: "chart_by_department",
        label: "Volume by department",
        defaultSpan: "full",
        render: () => (
          <DashboardBarChart
            title="Volume by department"
            description="Requester department mix for the current filters"
            data={departmentSeries}
            layout="horizontal"
            emptyMessage="No department volume yet."
            height={220}
          />
        ),
      },
      {
        id: "chart_by_category",
        label: "Volume by category",
        defaultSpan: "half",
        render: () => (
          <DashboardBarChart
            title="Volume by category"
            description="Open, in progress, and resolved (7d) across categories"
            data={categorySeries}
            layout="horizontal"
            emptyMessage="No category volume yet."
            height={240}
          />
        ),
      },
      {
        id: "table_category_analytics",
        label: "Category analytics",
        defaultSpan: "half",
        render: () => (
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="border-b border-border px-4 py-3">
              <h2 className="text-sm font-medium text-foreground">Category analytics</h2>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Active queue and recent resolutions by category
              </p>
            </div>
            {(data?.by_category ?? []).length === 0 ? (
              <p className="px-4 py-8 text-center text-xs text-muted-foreground">No category data yet.</p>
            ) : (
              <div className="max-h-[240px] overflow-auto">
                <table className="w-full text-left text-[13px]">
                  <thead className="sticky top-0 border-b border-border bg-muted/80 text-xs font-medium text-muted-foreground">
                    <tr>
                      <th className="px-4 py-2">Category</th>
                      <th className="px-3 py-2 text-right">Open</th>
                      <th className="px-3 py-2 text-right">SLA risk</th>
                      <th className="px-4 py-2 text-right">Avg resolve</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(data?.by_category ?? []).map((row) => (
                      <tr
                        key={row.category ?? "uncategorized"}
                        className="border-b border-border last:border-0"
                      >
                        <td className="px-4 py-2 text-foreground">{row.label}</td>
                        <td className="px-3 py-2 text-right text-muted-foreground">
                          {row.open + row.in_progress}
                        </td>
                        <td className="px-3 py-2 text-right text-muted-foreground">{row.sla_at_risk}</td>
                        <td className="px-4 py-2 text-right text-muted-foreground">
                          {row.avg_resolve_hours == null ? "—" : `${row.avg_resolve_hours}h`}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        ),
      },
      {
        id: "quick_actions",
        label: "Quick actions",
        dataHelp: "tk-overview-quick-actions",
        defaultSpan: "full",
        render: () => (
          <div data-help="tk-overview-quick-actions" className="grid gap-4 sm:grid-cols-2">
            {NAV_TILES.map((tile) => {
              const Icon = tile.icon;
              return (
                <Link
                  key={tile.href}
                  href={tile.href}
                  className={cn(
                    "group flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm transition-colors",
                    "hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40",
                  )}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Icon className="h-4 w-4" aria-hidden />
                    </div>
                    <ArrowRight className="h-4 w-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                  </div>
                  <p className="mt-3 text-sm font-medium text-foreground">{tile.label}</p>
                  <p className="mt-0.5 text-xs text-muted-foreground">{tile.description}</p>
                </Link>
              );
            })}
          </div>
        ),
      },
      {
        id: "recent_tickets",
        label: "Recent tickets",
        dataHelp: "tk-overview-recent",
        defaultSpan: "full",
        render: () => (
          <div
            data-help="tk-overview-recent"
            className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
          >
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
              <h2 className="text-sm font-medium text-foreground">Recent tickets</h2>
              <Link href="/ticketing/tickets" className="text-xs text-primary hover:underline">
                View all
              </Link>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-border bg-muted/40">
                  <tr>
                    <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Ticket</th>
                    <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Title</th>
                    <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Status</th>
                    <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Priority</th>
                    <th className="hidden px-4 py-2.5 text-[13px] font-medium text-muted-foreground md:table-cell">
                      Updated
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <TicketingTourOverviewRecentFixtures />
                  {(data?.recent_tickets ?? []).map((ticket) => (
                    <tr key={ticket.id} className="border-b border-border last:border-0 hover:bg-muted/20">
                      <td className="px-4 py-2.5 font-mono text-xs">
                        <Link href={`/ticketing/tickets/${ticket.id}`} className="text-primary hover:underline">
                          {ticket.ticket_number}
                        </Link>
                      </td>
                      <td className="max-w-xs truncate px-4 py-2.5 text-foreground">{ticket.title}</td>
                      <td className="px-4 py-2.5">
                        <TicketingStatusBadge status={ticket.status} />
                      </td>
                      <td className="px-4 py-2.5">
                        <TicketingPriorityBadge priority={ticket.priority} />
                      </td>
                      <td className="hidden px-4 py-2.5 text-xs text-muted-foreground md:table-cell">
                        {formatTicketingDate(ticket.updated_at)}
                      </td>
                    </tr>
                  ))}
                  {!tourActive && (data?.recent_tickets ?? []).length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-4 py-8 text-center text-sm text-muted-foreground">
                        No tickets yet.{" "}
                        <Link href="/ticketing/tickets/new" className="text-primary hover:underline">
                          Create the first ticket
                        </Link>
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </div>
        ),
      },
    ];
  }, [
    categorySeries,
    data?.by_category,
    data?.recent_tickets,
    departmentSeries,
    prioritySeries,
    queueSeries,
    tourActive,
  ]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const normalizedData = useMemo(() => {
    const base = normalizeTicketingDashboard(data);
    const chrome = resolvePageChrome(TICKETING_DASHBOARD_PAGE_CHROME, layout.pageChrome);
    return syncHeroWithPageChrome(base, chrome);
  }, [data, layout.pageChrome]);

  const boardWidgets = useMemo(
    () =>
      buildDynamicBoardWidgets({
        moduleId: "ticketing",
        data: normalizedData,
        slots: boundWidgets,
        enabledIds:
          layout.enabledWidgetIds.length > 0
            ? layout.enabledWidgetIds
            : TICKETING_DASHBOARD_DEFAULT_IDS,
        titleOverrides,
        widgetOptions: layout.widgetOptions,
      }),
    [boundWidgets, layout.enabledWidgetIds, layout.widgetOptions, normalizedData, titleOverrides],
  );

  const bindableCatalog = useMemo(
    () => bindableCatalogEntries("ticketing", boundWidgets, normalizedData),
    [boundWidgets, normalizedData],
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
          defaults={{
            ...TICKETING_DASHBOARD_PAGE_CHROME,
            description:
              data?.message ?? TICKETING_DASHBOARD_PAGE_CHROME.description,
          }}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout((current) => ({ ...current, pageChrome }))}
          actionsById={{
            help: <TicketingHelpEntryActions showHelp showTour={false} />,
            tour: <TicketingHelpEntryActions showHelp={false} showTour />,
            customize: (
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
            ),
            refresh: (
              <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
                {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
            ),
            new: (
              <Button size="sm" render={<Link href="/ticketing/tickets/new" />}>
                <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                New ticket
              </Button>
            ),
          }}
        />

        <TicketingTourSoftPrompt />
        <TicketingTourCompleteAnchor />

        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex flex-wrap items-end gap-3">
            <FilterSelect
              id="ticketing-dash-status"
              label="Status"
              value={status}
              onChange={(value) => patchPrefs({ status: value })}
              className="w-[10rem]"
            >
              <option value="">All statuses</option>
              {statusOptions.map((item) => (
                <option key={item} value={item}>
                  {item.replace(/_/g, " ")}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect
              id="ticketing-dash-priority"
              label="Priority"
              value={priority}
              onChange={(value) => patchPrefs({ priority: value })}
              className="w-[9rem]"
            >
              <option value="">All priorities</option>
              {priorityOptions.map((item) => (
                <option key={item} value={item}>
                  {item.replace(/_/g, " ")}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect
              id="ticketing-dash-category"
              label="Category"
              value={category}
              onChange={(value) => patchPrefs({ category: value })}
              className="w-[12rem]"
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
                id="ticketing-dash-department"
                label="Department"
                value={department}
                onChange={(value) => patchPrefs({ department: value })}
                className="w-[11rem]"
              >
                <option value="">All departments</option>
                {departmentOptions.map((code) => (
                  <option key={code} value={code}>
                    {code}
                  </option>
                ))}
              </FilterSelect>
            ) : null}
            <div className="min-w-0 space-y-1.5">
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
                  onClick={() =>
                    patchPrefs({
                      mineOnly: !mineOnly,
                      assignedMe: !mineOnly ? false : assignedMe,
                    })
                  }
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
                  onClick={() =>
                    patchPrefs({
                      assignedMe: !assignedMe,
                      mineOnly: !assignedMe ? false : mineOnly,
                    })
                  }
                >
                  Assigned to me
                </button>
              </div>
            </div>
            {hasFilters ? (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="h-8"
                onClick={() =>
                  patchPrefs({
                    status: "",
                    category: "",
                    priority: "",
                    department: "",
                    mineOnly: false,
                    assignedMe: false,
                  })
                }
              >
                Clear filters
              </Button>
            ) : null}
          </div>
          <p className="text-xs text-muted-foreground">
            Filters apply to KPIs and charts, and are remembered with the tickets queue.
          </p>
        </div>

        {showSkeleton ? (
          <DashboardBoardSkeleton
            layout={layout}
            defaultEnabledIds={TICKETING_DASHBOARD_DEFAULT_IDS}
          />
        ) : null}
        {isError ? (
          <p className="text-sm text-destructive">
            Could not load ticketing dashboard. {getErrorMessage(error)}
          </p>
        ) : null}

        {!showSkeleton ? (
          <DashboardWidgetBoard
            widgets={boardWidgets}
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
        ) : null}
      </div>
    </PermissionGate>
  );
}
