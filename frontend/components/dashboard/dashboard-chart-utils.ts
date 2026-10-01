export type DashboardChartDatum = {
  key: string;
  label: string;
  value: number;
  fill?: string;
};

/** Multi-series bag for line / stacked area / radar overlays. */
export type DashboardMultiSeries = {
  categories: string[];
  series: Array<{
    key: string;
    label: string;
    color: string;
    values: number[];
  }>;
};

/** Point for scatter / bubble charts. */
export type DashboardScatterPoint = {
  key: string;
  label: string;
  x: number;
  y: number;
  z: number;
  fill?: string;
};

/** Brand-first operational chart hues (amber/red only for risk). */
export const DASHBOARD_CHART = {
  brand: "#2563EB",
  brandSoft: "#3B82F6",
  sky: "#0EA5E9",
  muted: "#64748B",
  success: "#16A34A",
  warning: "#D97706",
  danger: "#DC2626",
} as const;

/** Default multi-series palette — Vuexy-inspired cool operational hues. */
export const DASHBOARD_CHART_COLORS = [
  "#00BAD1", // teal
  "#7367F0", // violet
  "#28C76F", // green
  "#FF9F43", // amber (accent sparingly)
  "#EA5455", // soft red
  "#2563EB", // brand blue
  "#0EA5E9", // sky
  "#64748B", // muted
] as const;

const STATUS_FILLS: Record<string, string> = {
  success: DASHBOARD_CHART.success,
  warning: DASHBOARD_CHART.warning,
  danger: DASHBOARD_CHART.danger,
  neutral: DASHBOARD_CHART.brand,
};

/** Keys that should use warning amber in charts (not volume/info). */
const WARNING_KEYS = new Set([
  "ea_awaiting_my_approval",
  "awaiting_my_approval",
  "stale_approvals",
  "ea_stale_approvals",
  "rollout_gates_awaiting_me",
  "gate_approvals_awaiting_me",
  "at_risk",
  "high",
  "towers_maint",
  "assets_transit",
]);

/** Keys that should use danger red in charts. */
const DANGER_KEYS = new Set([
  "rollout_sla_risk",
  "sla_at_risk",
  "urgent",
  "blocked",
]);

const SUCCESS_KEYS = new Set([
  "on_track",
  "towers_ops",
  "fiber_active",
  "assets_dep",
  "resolved_week",
]);

export function chartColorAt(index: number): string {
  return DASHBOARD_CHART_COLORS[index % DASHBOARD_CHART_COLORS.length];
}

function statusToken(value: string): string {
  return value.trim().toLowerCase().replace(/[\s-]+/g, "_");
}

/**
 * Stable hue for a workflow status. Null when the token is not a status
 * (form names, dates, departments stay on the series palette).
 */
export function statusChartColor(value: string): string | null {
  const key = statusToken(value);
  if (!key) return null;

  if (
    key === "rejected" ||
    key.endsWith("_rejected") ||
    key === "failed" ||
    key.endsWith("_failed") ||
    key === "denied" ||
    key === "error" ||
    key === "canceled" ||
    key === "cancelled" ||
    DANGER_KEYS.has(key) ||
    key.includes("sla_risk") ||
    key.includes("breached")
  ) {
    return DASHBOARD_CHART.danger;
  }

  if (
    key === "approved" ||
    key === "completed" ||
    key === "complete" ||
    key === "resolved" ||
    key === "ready" ||
    key === "success" ||
    key === "published" ||
    SUCCESS_KEYS.has(key) ||
    key.includes("on_track") ||
    key.includes("_ops") ||
    key === "active" ||
    key === "healthy"
  ) {
    return DASHBOARD_CHART.success;
  }

  if (key === "draft") return DASHBOARD_CHART.sky;
  if (key === "closed") return DASHBOARD_CHART.muted;

  if (
    key === "pending" ||
    key === "submitted" ||
    key === "returned" ||
    key === "in_review" ||
    key.startsWith("awaiting") ||
    key.includes("awaiting") ||
    WARNING_KEYS.has(key) ||
    key.includes("at_risk") ||
    key.includes("stale") ||
    key === "past_due" ||
    key === "trial"
  ) {
    return DASHBOARD_CHART.warning;
  }

  if (key === "open" || key === "in_progress" || key === "processing" || key === "scanning") {
    return DASHBOARD_CHART.brand;
  }

  return null;
}

/**
 * Resolve fill by semantic key. Status tokens win over the rotating palette.
 */
export function chartFillForKey(key: string, index = 0, label?: string): string {
  return (
    statusChartColor(key) ??
    (label ? statusChartColor(label) : null) ??
    chartColorAt(index)
  );
}

/** Status color when the slice is a status; otherwise the fill already on the row. */
export function resolveChartFill(
  row: { key: string; label?: string; fill?: string },
  index = 0,
): string {
  return statusChartColor(row.key) ?? (row.label ? statusChartColor(row.label) : null) ?? row.fill ?? chartColorAt(index);
}

export function parseKpiNumber(value: string | number | null | undefined): number {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value : 0;
  }
  if (value == null || value === "") {
    return 0;
  }
  const match = String(value).replace(/,/g, "").match(/-?\d+(\.\d+)?/);
  if (!match) {
    return 0;
  }
  const n = Number.parseFloat(match[0]);
  return Number.isFinite(n) ? n : 0;
}

export function kpiSeries(
  kpis: Array<{ key: string; label: string; value: string | number; tone?: string }>,
  keys: string[],
): DashboardChartDatum[] {
  const byKey = new Map(kpis.map((kpi) => [kpi.key, kpi]));
  const rows: DashboardChartDatum[] = [];

  for (let index = 0; index < keys.length; index++) {
    const key = keys[index];
    const kpi = byKey.get(key);
    if (!kpi) {
      continue;
    }
    const value = parseKpiNumber(kpi.value);
    if (value < 0) {
      continue;
    }
    rows.push({
      key,
      label: kpi.label,
      value,
      fill: chartFillForKey(key, index),
    });
  }

  return rows;
}

export function recordToSeries(
  record: Record<string, number>,
  labelFn?: (key: string) => string,
): DashboardChartDatum[] {
  return Object.entries(record)
    .map(([key, value], index) => ({
      key,
      label: labelFn ? labelFn(key) : key.replace(/_/g, " "),
      value: Number(value) || 0,
      fill: chartFillForKey(key, index),
    }))
    .filter((row) => row.value > 0)
    .sort((a, b) => b.value - a.value);
}

export function countBy<T>(
  items: T[],
  keyFn: (item: T) => string,
  labelFn?: (key: string) => string,
): DashboardChartDatum[] {
  const counts = new Map<string, number>();
  for (const item of items) {
    const key = keyFn(item);
    if (!key) continue;
    counts.set(key, (counts.get(key) ?? 0) + 1);
  }
  return [...counts.entries()]
    .map(([key, value], index) => ({
      key,
      label: labelFn ? labelFn(key) : key.replace(/_/g, " "),
      value,
      fill: chartFillForKey(key, index),
    }))
    .sort((a, b) => b.value - a.value);
}

/** @deprecated Prefer chartFillForKey; kept for rare explicit tone mapping. */
export function fillFromTone(tone: string | undefined, index = 0): string {
  if (tone && STATUS_FILLS[tone]) {
    return STATUS_FILLS[tone];
  }
  return chartColorAt(index);
}
