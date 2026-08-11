export type DashboardChartDatum = {
  key: string;
  label: string;
  value: number;
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

/** Default multi-series palette — cool tones only (no amber/red rotation). */
export const DASHBOARD_CHART_COLORS = [
  DASHBOARD_CHART.brand,
  DASHBOARD_CHART.muted,
  DASHBOARD_CHART.brandSoft,
  DASHBOARD_CHART.sky,
  "#94A3B8",
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

/**
 * Resolve fill by semantic key. Defaults to brand blue — not backend KPI tone
 * (tones often mark "attention" for inbox volume, which should stay cool).
 */
export function chartFillForKey(key: string, index = 0): string {
  const normalized = key.trim().toLowerCase();
  if (
    DANGER_KEYS.has(normalized) ||
    normalized.includes("sla_risk") ||
    normalized.includes("breached") ||
    normalized === "canceled" ||
    normalized === "cancelled"
  ) {
    return DASHBOARD_CHART.danger;
  }
  if (
    WARNING_KEYS.has(normalized) ||
    normalized.includes("at_risk") ||
    normalized.includes("stale") ||
    normalized === "past_due" ||
    normalized === "trial"
  ) {
    return DASHBOARD_CHART.warning;
  }
  if (
    SUCCESS_KEYS.has(normalized) ||
    normalized.includes("on_track") ||
    normalized.includes("_ops") ||
    normalized === "active" ||
    normalized === "healthy"
  ) {
    return DASHBOARD_CHART.success;
  }
  return chartColorAt(index);
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
