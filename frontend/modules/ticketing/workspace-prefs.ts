"use client";

import { useCallback } from "react";

import { useLocalStorageJsonState } from "@/hooks/use-local-storage-json-state";
import {
  EMPTY_DASHBOARD_LAYOUT_PREFS,
  normalizeDashboardLayoutPrefs,
  type DashboardLayoutPrefs,
} from "@/lib/ui/dashboard-widget-registry";

export type TicketingChartMode = "bars" | "donut" | "both";

export type TicketingWorkspacePrefs = {
  status: string;
  category: string;
  priority: string;
  department: string;
  mineOnly: boolean;
  assignedMe: boolean;
  /** Comma-separated SLA statuses, e.g. at_risk,breached */
  slaStatus: string;
  chartMode: TicketingChartMode;
  density: "comfortable" | "compact";
} & DashboardLayoutPrefs;

export const DEFAULT_TICKETING_PREFS: TicketingWorkspacePrefs = {
  status: "",
  category: "",
  priority: "",
  department: "",
  mineOnly: false,
  assignedMe: false,
  slaStatus: "",
  chartMode: "both",
  density: "comfortable",
  ...EMPTY_DASHBOARD_LAYOUT_PREFS,
  spans: {},
  widgetOptions: {},
  pageChrome: {},
};

export const TICKETING_DASHBOARD_WIDGETS = [
  { id: "kpis", label: "KPI strip" },
  { id: "chart_ticket_queue", label: "Ticket queue" },
  { id: "chart_by_priority", label: "By priority" },
  { id: "chart_by_department", label: "Volume by department" },
  { id: "chart_by_category", label: "Volume by category" },
  { id: "table_category_analytics", label: "Category analytics" },
  { id: "quick_actions", label: "Quick actions" },
  { id: "recent_tickets", label: "Recent tickets" },
] as const;

export type TicketingDashboardWidgetId = (typeof TICKETING_DASHBOARD_WIDGETS)[number]["id"];

/** Expand legacy bundled Analytics / Category widgets into separate board ids. */
export function expandTicketingDashboardWidgetIds(ids: string[]): string[] {
  const out: string[] = [];
  for (const id of ids) {
    if (id === "queue_charts") {
      for (const next of ["chart_ticket_queue", "chart_by_priority", "chart_by_department"] as const) {
        if (!out.includes(next)) out.push(next);
      }
      continue;
    }
    if (id === "category_analytics") {
      for (const next of ["chart_by_category", "table_category_analytics"] as const) {
        if (!out.includes(next)) out.push(next);
      }
      continue;
    }
    if (!out.includes(id)) out.push(id);
  }
  return out;
}

function isTicketingWorkspacePrefs(value: unknown): value is TicketingWorkspacePrefs {
  if (!value || typeof value !== "object") return false;
  const row = value as Record<string, unknown>;
  const hasCore =
    typeof row.status === "string" &&
    typeof row.category === "string" &&
    typeof row.priority === "string" &&
    typeof row.department === "string" &&
    typeof row.mineOnly === "boolean" &&
    typeof row.assignedMe === "boolean" &&
    (row.slaStatus === undefined || typeof row.slaStatus === "string") &&
    (row.chartMode === "bars" || row.chartMode === "donut" || row.chartMode === "both") &&
    (row.density === "comfortable" || row.density === "compact");
  if (!hasCore) return false;
  return true;
}

export function useTicketingWorkspacePrefs() {
  const [prefs, setPrefs] = useLocalStorageJsonState<TicketingWorkspacePrefs>(
    "toweros.ticketing.prefs",
    DEFAULT_TICKETING_PREFS,
    isTicketingWorkspacePrefs,
  );

  const layout = normalizeDashboardLayoutPrefs(prefs);

  const patchPrefs = useCallback((patch: Partial<TicketingWorkspacePrefs>) => {
    setPrefs((current) => ({
      ...DEFAULT_TICKETING_PREFS,
      ...current,
      ...normalizeDashboardLayoutPrefs(current),
      ...patch,
    }));
  }, [setPrefs]);

  const setLayout = useCallback(
    (next: DashboardLayoutPrefs) => {
      patchPrefs({ ...normalizeDashboardLayoutPrefs(next) });
    },
    [patchPrefs],
  );

  return {
    prefs: {
      ...DEFAULT_TICKETING_PREFS,
      ...prefs,
      ...layout,
    },
    layout,
    patchPrefs,
    setLayout,
  };
}
