"use client";

import Link from "next/link";
import { useMemo, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import {
  ArrowRight,
  ClipboardCheck,
  FilePlus2,
  FileSpreadsheet,
  FileStack,
  FileText,
  Inbox,
} from "lucide-react";

import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardBoardSkeleton } from "@/components/dashboard/dashboard-board-skeleton";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { EApprovalHelpEntryActions } from "@/components/help/e-approval-help-entry-actions";
import { EApprovalTourCompleteAnchor, EApprovalTourOverviewQueueFixtures } from "@/components/help/e-approval-tour-fixtures";
import { EApprovalTourSoftPrompt } from "@/components/help/e-approval-tour-soft-prompt";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PermissionGate } from "@/components/layout/permission-gate";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import { useEApprovalDashboard } from "@/hooks/use-e-approval-dashboard";
import { usePermission } from "@/hooks/use-permission";
import { EMPTY_DASHBOARD_LAYOUT_PREFS } from "@/lib/ui/dashboard-widget-registry";
import { isEApprovalTourActive } from "@/lib/help/e-approval-tour-fixtures";
import { permissions } from "@/lib/rbac/permissions";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { normalizeEApprovalDashboard } from "@/lib/ui/normalize-dashboard-data";
import { E_APPROVAL_DASHBOARD_PAGE_CHROME } from "@/lib/ui/page-chrome-config";
import { cn } from "@/lib/utils";
import { formatEApprovalStatusLabel } from "@/modules/e-approval/status-display";
import type {
  EApprovalDashboardKpi,
  EApprovalDashboardQueueItem,
} from "@/modules/e-approval/types";

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return Number.isNaN(d.getTime())
    ? iso
    : d.toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" });
}

function OverviewKpiStrip({ items }: { items: EApprovalDashboardKpi[] }) {
  return (
    <KpiStrip
      dataHelp="ea-overview-kpis"
      items={items.map((item) => ({
        key: item.key,
        label: item.label,
        value: item.value,
        change: item.change,
        tone: item.tone ?? "neutral",
        href: item.href,
      }))}
    />
  );
}

function QueueList({
  items,
  emptyMessage,
  metaLabel,
  tourFixture,
}: {
  items: EApprovalDashboardQueueItem[];
  emptyMessage: string;
  metaLabel: "requestor" | "updated";
  tourFixture?: "awaiting" | "attention";
}) {
  const searchParams = useSearchParams();
  const tourActive = isEApprovalTourActive(searchParams);

  if (items.length === 0) {
    if (tourActive && tourFixture) {
      return (
        <Suspense fallback={<p className="px-1 py-6 text-sm text-muted-foreground">{emptyMessage}</p>}>
          <EApprovalTourOverviewQueueFixtures variant={tourFixture} />
        </Suspense>
      );
    }
    return <p className="px-1 py-6 text-sm text-muted-foreground">{emptyMessage}</p>;
  }

  return (
    <ul className="divide-y divide-border rounded-lg border border-border">
      {items.map((row) => (
        <li key={row.id}>
          <Link
            href={row.href}
            className="flex items-start justify-between gap-3 px-3 py-2.5 transition-colors hover:bg-muted/30"
          >
            <div className="min-w-0 space-y-0.5">
              <p className="truncate text-sm font-medium text-foreground">
                {row.document_no || "Untitled"}
                {row.form_name ? (
                  <span className="ml-2 font-normal text-muted-foreground">{row.form_name}</span>
                ) : null}
              </p>
              <p className="text-xs text-muted-foreground">
                {formatEApprovalStatusLabel(row.status)}
                {row.step_order != null ? ` · Step ${row.step_order}` : ""}
                {metaLabel === "requestor" && row.requestor_name
                  ? ` · ${row.requestor_name}`
                  : ` · ${formatWhen(row.waiting_since)}`}
              </p>
            </div>
            <ArrowRight className="mt-1 size-3.5 shrink-0 text-muted-foreground" aria-hidden />
          </Link>
        </li>
      ))}
    </ul>
  );
}

export function EApprovalDashboardPageClient() {
  return (
    <Suspense
      fallback={
        <div className="space-y-6">
          <DashboardBoardSkeleton
            layout={EMPTY_DASHBOARD_LAYOUT_PREFS}
            defaultEnabledIds={["kpis", "queues", "shortcuts"]}
          />
        </div>
      }
    >
      <EApprovalDashboardPageInner />
    </Suspense>
  );
}

function EApprovalDashboardPageInner() {
  const { data, isFetching, isError, isPlaceholderData, refetch } = useEApprovalDashboard();
  const showSkeleton = isFetching && isPlaceholderData;
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canManageForms = usePermission([permissions.eApprovalFormsManage]);
  const canAudit = usePermission([permissions.eApprovalAuditView]);
  const { layout, setLayout, tenantDefault, publishTenantDefault, resetToTenantDefault } = useDashboardLayoutPrefs("toweros.e-approval.dashboard.layout");
  const { editing, setEditing } = useDashboardCustomizeMode();

  const capabilities = data?.capabilities;
  const showApproveQueue = capabilities?.can_approve ?? canApprove;
  const showCreate = capabilities?.can_create ?? canCreate;
  const showForms = capabilities?.can_manage_forms ?? canManageForms;
  const showReports = capabilities?.can_audit ?? canAudit;

  const awaitingQueue = data?.queues?.awaiting_approval ?? [];
  const attentionQueue = data?.queues?.my_attention ?? [];
  const financeKpis = data?.finance_kpis ?? [];

  const shortcuts = useMemo(
    () =>
      [
        {
          href: "/e-approval/submissions",
          label: "Submissions",
          description: "Track your requests",
          icon: FileStack,
          show: true,
        },
        {
          href: "/e-approval/approvals?awaiting_me=1",
          label: "Approvals",
          description: "Decide on pending items",
          icon: Inbox,
          show: showApproveQueue,
        },
        {
          href: "/e-approval/forms",
          label: "Forms",
          description: "Templates and workflows",
          icon: FileText,
          show: showForms,
        },
        {
          href: "/e-approval/reports",
          label: "Reports",
          description: "Analytics and exports",
          icon: FileSpreadsheet,
          show: showReports,
        },
      ].filter((item) => item.show),
    [showApproveQueue, showForms, showReports],
  );

  const layoutMeta = useMemo(() => {
    const items = [
      { id: "kpis", label: "Status KPIs" },
      { id: "queues", label: "Approval queues" },
      { id: "shortcuts", label: "Shortcuts" },
    ];
    if (financeKpis.length > 0) {
      items.splice(2, 0, { id: "finance", label: "Finance & procurement" });
    }
    return items;
  }, [financeKpis.length]);

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    const widgets: DashboardWidgetDef[] = [
      {
        id: "kpis",
        label: "Status KPIs",
        dataHelp: "ea-overview-kpis",
        defaultSpan: "full",
        render: () => <OverviewKpiStrip items={data?.kpis ?? []} />,
      },
      {
        id: "queues",
        label: "Approval queues",
        defaultSpan: "full",
        render: () => (
          <div className={cn("grid gap-4", showApproveQueue ? "lg:grid-cols-2" : "lg:grid-cols-1")}>
            {showApproveQueue ? (
              <EApprovalSectionCard
                dataHelp="ea-overview-awaiting"
                title="Needs my approval"
                description="Oldest pending items assigned to you."
                actions={
                  <Link
                    href="/e-approval/approvals?awaiting_me=1"
                    className="text-xs font-medium text-primary hover:underline"
                  >
                    View all
                  </Link>
                }
              >
                <QueueList
                  items={awaitingQueue}
                  emptyMessage="Nothing waiting on you right now. New items show up when someone submits work to you."
                  metaLabel="requestor"
                  tourFixture="awaiting"
                />
              </EApprovalSectionCard>
            ) : (
              <div
                data-help="ea-overview-awaiting"
                className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-6 text-sm text-muted-foreground"
              >
                Approver inbox appears here when your role can approve requests.
              </div>
            )}

            <EApprovalSectionCard
              dataHelp="ea-overview-attention"
              title="Needs my attention"
              description="Returned and draft submissions you own."
              actions={
                <Link
                  href="/e-approval/submissions?mine=1"
                  className="text-xs font-medium text-primary hover:underline"
                >
                  View mine
                </Link>
              }
            >
              <QueueList
                items={attentionQueue}
                emptyMessage="No drafts or returns yet. Items you own that need work will list here."
                metaLabel="updated"
                tourFixture="attention"
              />
            </EApprovalSectionCard>
          </div>
        ),
      },
    ];

    if (financeKpis.length > 0) {
      widgets.push({
        id: "finance",
        label: "Finance & procurement",
        defaultSpan: "full",
        render: () => (
          <EApprovalSectionCard
            title="Finance & procurement"
            description="Open cash advances and PR follow-ups."
          >
            <OverviewKpiStrip items={financeKpis} />
          </EApprovalSectionCard>
        ),
      });
    }

    widgets.push({
      id: "shortcuts",
      label: "Shortcuts",
      defaultSpan: "full",
      render: () => (
        <div className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {shortcuts.map((tile) => {
              const Icon = tile.icon;
              return (
                <Link
                  key={tile.href}
                  href={tile.href}
                  className="group flex items-start gap-3 rounded-xl border border-border bg-card p-3.5 shadow-sm transition-colors hover:border-primary/30 hover:bg-muted/30"
                >
                  <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                    <Icon className="size-4" aria-hidden />
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-foreground">{tile.label}</p>
                    <p className="text-xs text-muted-foreground">{tile.description}</p>
                  </div>
                  <ArrowRight className="ml-auto size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                </Link>
              );
            })}
          </div>
          {showReports ? (
            <p className="text-xs text-muted-foreground">
              Need trends or CSV/Excel extracts?{" "}
              <Link href="/e-approval/reports" className="font-medium text-primary hover:underline">
                Open Reports
              </Link>
              .
            </p>
          ) : null}
        </div>
      ),
    });

    return widgets;
  }, [
    attentionQueue,
    awaitingQueue,
    data?.kpis,
    financeKpis,
    shortcuts,
    showApproveQueue,
    showReports,
  ]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const normalizedData = useMemo(() => normalizeEApprovalDashboard(data), [data]);

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
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="space-y-6">
        <LiveProductTourHost />
        <ConfigurableModulePageHeader
          defaults={{
            ...E_APPROVAL_DASHBOARD_PAGE_CHROME,
            description: data?.message ?? E_APPROVAL_DASHBOARD_PAGE_CHROME.description,
          }}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout({ ...layout, pageChrome })}
          renderActions={({ isVisible }) => (
            <div data-help="ea-overview-quick-actions" className="flex flex-wrap items-center gap-2">
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
              {isVisible("refresh") ? (
                <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
                  {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                  Refresh
                </Button>
              ) : null}
              {isVisible("approvals") && showApproveQueue ? (
                <Button size="sm" variant="outline" render={<Link href="/e-approval/approvals?awaiting_me=1" />}>
                  <ClipboardCheck className="mr-1.5 size-3.5" aria-hidden />
                  Approvals
                </Button>
              ) : null}
              {isVisible("new") && showCreate ? (
                <Button size="sm" render={<Link href="/e-approval/submissions/new" />}>
                  <FilePlus2 className="mr-1.5 size-3.5" aria-hidden />
                  New submission
                </Button>
              ) : null}
            </div>
          )}
        />

        <EApprovalTourSoftPrompt />
        <Suspense fallback={null}>
          <EApprovalTourCompleteAnchor />
        </Suspense>

        {showSkeleton ? (
          <DashboardBoardSkeleton
            layout={layout}
            defaultEnabledIds={layoutMeta.map((widget) => widget.id)}
          />
        ) : null}

        {isError ? <p className="text-sm text-destructive">Could not load E-Forms overview.</p> : null}

        {!showSkeleton ? (
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
        ) : null}
      </div>
    </PermissionGate>
  );
}
