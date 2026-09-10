"use client";

import Link from "next/link";
import { useMemo } from "react";

import { AwaitingMeHub } from "@/components/dashboard/awaiting-me-hub";
import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardBoardSkeleton } from "@/components/dashboard/dashboard-board-skeleton";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { chartColorAt, kpiSeries } from "@/components/dashboard/dashboard-chart-utils";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { ActionableWidgets } from "@/components/project-one/actionable-widgets";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useWorkspaceDashboard } from "@/hooks/use-workspace-dashboard";
import { emptyWorkspaceDashboard } from "@/lib/api/modules/workspace-dashboard-api";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { applyPageEnhancements, workspaceDashboardEnhancements } from "@/lib/ui/page-enhancement-bags";
import { normalizeWorkspaceDashboard } from "@/lib/ui/normalize-dashboard-data";
import { WORKSPACE_DASHBOARD_PAGE_CHROME } from "@/lib/ui/page-chrome-config";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import { cn } from "@/lib/utils";
import type { WorkspaceDashboardActivity } from "@/modules/workspace/types";

const LAYOUT_KEY = "toweros.workspace.dashboard.layout";

const DEFAULT_ENABLED_IDS = [
  "kpis",
  "awaiting_me",
  "action_charts",
  "action_queue",
  "recent_activity",
] as const;

const moduleLabels: Record<string, string> = {
  e_approval: "E-Forms",
  project_one: "PROJECT-ONE",
  ticketing: "Ticketing",
  notifications: "Notifications",
};

function formatActivityTime(iso: string | null): string {
  if (!iso) {
    return "—";
  }
  try {
    return new Intl.DateTimeFormat(undefined, {
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

function RecentActivityPanel({ items }: { items: WorkspaceDashboardActivity[] }) {
  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium text-foreground">Recent activity</h2>
      <p className="mt-1 text-xs text-muted-foreground">
        Latest notifications and workflow updates for your account.
      </p>

      <div className="mt-4 divide-y divide-border">
        {items.length === 0 ? (
          <p className="py-6 text-sm text-muted-foreground">No recent activity yet.</p>
        ) : (
          items.map((item) => {
            const content = (
              <div className="py-3">
                <div className="flex items-center justify-between gap-2">
                  <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                    {moduleLabels[item.module] ?? item.module}
                  </span>
                  <span className="font-mono text-[10px] text-muted-foreground">
                    {formatActivityTime(item.created_at)}
                  </span>
                </div>
                <p className="mt-1 text-sm font-medium text-foreground">{item.label}</p>
                {item.detail ? (
                  <p className="mt-0.5 text-xs text-muted-foreground">{item.detail}</p>
                ) : null}
              </div>
            );

            if (item.href) {
              return (
                <Link
                  key={item.id}
                  href={item.href}
                  className="block transition-colors hover:bg-muted/40"
                >
                  {content}
                </Link>
              );
            }

            return (
              <div key={item.id} className="block">
                {content}
              </div>
            );
          })
        )}
      </div>
    </section>
  );
}

export function TenantWorkspaceDashboard() {
  const { data, isFetching, isError, isPlaceholderData, refetch } = useWorkspaceDashboard();
  const dashboard = data ?? emptyWorkspaceDashboard;
  const showSkeleton = isFetching && isPlaceholderData;
  const {
    layout,
    setLayout,
    tenantDefault,
    publishTenantDefault,
    resetToTenantDefault,
  } = useDashboardLayoutPrefs(LAYOUT_KEY);
  const { editing, setEditing } = useDashboardCustomizeMode();

  const actionSeries = useMemo(
    () =>
      dashboard.actions
        .filter((action) => action.count > 0)
        .map((action, index) => ({
          key: action.id,
          label: action.label,
          value: action.count,
          fill: chartColorAt(index),
        })),
    [dashboard.actions],
  );

  const attentionSeries = useMemo(
    () =>
      kpiSeries(dashboard.kpis, [
        "unread_notifications",
        "ea_awaiting_my_approval",
        "ea_stale_approvals",
        "rollout_gates_awaiting_me",
        "ticketing_assigned_me",
        "rollout_sla_risk",
      ]).filter((row) => row.value > 0),
    [dashboard.kpis],
  );

  const layoutMeta = useMemo(
    () => [
      { id: "kpis", label: "KPI strip" },
      { id: "awaiting_me", label: "Awaiting you" },
      { id: "action_charts", label: "Action & attention charts" },
      { id: "action_queue", label: "Action queue" },
      { id: "recent_activity", label: "Recent activity" },
    ],
    [],
  );

  const defaultEnabledIds = useMemo(() => [...DEFAULT_ENABLED_IDS], []);

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    return [
      {
        id: "kpis",
        label: "KPI strip",
        hideable: false,
        removable: false,
        defaultSpan: "full",
        render: () => <KpiStrip items={dashboard.kpis} />,
      },
      {
        id: "awaiting_me",
        label: "Awaiting you",
        hideable: false,
        removable: false,
        defaultSpan: "full",
        render: () => (
          <AwaitingMeHub
            total={dashboard.awaiting_me?.total ?? 0}
            items={dashboard.awaiting_me?.items ?? []}
          />
        ),
      },
      {
        id: "action_charts",
        label: "Action & attention charts",
        defaultSpan: "full",
        render: () => (
          <div className="grid gap-4 lg:grid-cols-2">
            <DashboardBarChart
              title="Action queues"
              description="Items in your operational queues"
              data={actionSeries}
              layout="horizontal"
              emptyMessage="No queued actions right now."
              height={200}
            />
            <DashboardDonutChart
              title="Attention mix"
              description="Notifications, approvals, tickets, and SLA risk"
              data={attentionSeries}
              emptyMessage="Nothing requiring attention."
              height={200}
            />
          </div>
        ),
      },
      {
        id: "action_queue",
        label: "Action queue",
        defaultSpan: "half",
        render: () => <ActionableWidgets items={dashboard.actions} />,
      },
      {
        id: "recent_activity",
        label: "Recent activity",
        defaultSpan: "half",
        render: () => <RecentActivityPanel items={dashboard.recent_activity} />,
      },
    ];
  }, [actionSeries, attentionSeries, dashboard]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const normalizedData = useMemo(
    () =>
      applyPageEnhancements(
        normalizeWorkspaceDashboard(dashboard),
        workspaceDashboardEnhancements({
          awaitingTotal: dashboard.awaiting_me?.total ?? 0,
          quickLinks: dashboard.quick_links,
        }),
      ),
    [dashboard],
  );

  const catalogBoardWidgets = useMemo(
    () =>
      buildDynamicBoardWidgets({
        moduleId: "workspace",
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
    () => bindableCatalogEntries("workspace", boardWidgets, normalizedData),
    [boardWidgets, normalizedData],
  );

  const addableCatalog = useMemo(() => {
    const enabled = new Set(
      layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds,
    );
    return bindableCatalog.filter((entry) => !enabled.has(entry.id));
  }, [bindableCatalog, defaultEnabledIds, layout.enabledWidgetIds]);

  const boardHandlers = useDashboardBoardLayoutHandlers(layout, setLayout, defaultEnabledIds);

  return (
    <div className="flex flex-col gap-6">
      <LiveProductTourHost />
      <ConfigurableModulePageHeader
        defaults={WORKSPACE_DASHBOARD_PAGE_CHROME}
        prefs={layout.pageChrome}
        editing={editing}
        onChromeChange={(pageChrome) => setLayout({ ...layout, pageChrome })}
        renderActions={({ isVisible }) => (
          <>
            {isVisible("customize") ? (
              <DashboardLayoutToolbar
                widgets={layoutMeta}
                catalogModule="workspace"
                layout={layout}
                editing={editing}
                onEditingChange={setEditing}
                onChange={setLayout}
                defaultEnabledIds={defaultEnabledIds}
                bindableCatalog={bindableCatalog}
                hasTenantDefault={Boolean(tenantDefault)}
                onPublishTenantDefault={publishTenantDefault}
                onResetToTenantDefault={resetToTenantDefault}
                data={normalizedData}
              />
            ) : null}
            {isVisible("refresh") ? (
              <Button size="sm" variant="outline" onClick={() => void refetch()} disabled={isFetching}>
                {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
            ) : null}
          </>
        )}
      />

      {dashboard.quick_links.length > 0 && !editing && !showSkeleton ? (
        <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium">
          {dashboard.quick_links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="text-primary underline-offset-4 hover:underline"
            >
              {link.label}
            </Link>
          ))}
        </p>
      ) : null}

      {showSkeleton ? (
        <DashboardBoardSkeleton layout={layout} defaultEnabledIds={[...DEFAULT_ENABLED_IDS]} />
      ) : (
        <DashboardWidgetBoard
          widgets={catalogBoardWidgets}
          order={layout.widgetOrder}
          hiddenIds={layout.hiddenWidgetIds}
          enabledIds={layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds}
          layoutPrefs={layout}
          editing={editing}
          addableCatalog={addableCatalog}
          data={normalizedData}
          {...boardHandlers}
        />
      )}

      {isError ? (
        <Card className={cn("border-destructive/40 bg-destructive/5")}>
          <CardHeader className="pb-2">
            <CardTitle className="text-base font-medium text-destructive">
              Unable to load dashboard
            </CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            Check API connectivity and try refresh. Module-specific dashboards remain available from
            the sidebar.
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
