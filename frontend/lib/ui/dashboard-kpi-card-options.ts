/**
 * Per-KPI overrides for metric-row widgets (Pinned KPI strip, etc.).
 * Stored as JSON in `widgetOptions[id].settings.kpiCards` so prefs stay primitive-compatible.
 */

export type DashboardKpiTone = "neutral" | "success" | "warning" | "danger";

export type DashboardKpiAccent =
  | "auto"
  | "sky"
  | "emerald"
  | "amber"
  | "rose"
  | "slate"
  | "indigo";

export type DashboardKpiIconId =
  | "auto"
  | "layers"
  | "scan"
  | "check"
  | "alert"
  | "ticket"
  | "clipboard"
  | "activity"
  | "file"
  | "folder";

/**
 * Card visualization — inspired by analytics dashboards (area / gauge / bars / progress),
 * kept operational (no decorative 3D art).
 */
export type DashboardKpiSparkStyle =
  | "auto"
  | "bars"
  | "thick"
  | "area"
  | "line"
  | "dots"
  | "gauge"
  | "ring"
  | "progress"
  | "steps"
  | "split"
  | "none";

/** Card body layout. */
export type DashboardKpiLayout = "stack" | "split" | "auto";

export type DashboardKpiCardOverride = {
  /** Semantic tone (also used when accent is auto). */
  tone?: DashboardKpiTone;
  /** Visual color family for icon / accent / spark. */
  accent?: DashboardKpiAccent;
  icon?: DashboardKpiIconId;
  /** Display label override (empty = use live KPI label). */
  label?: string;
  showSpark?: boolean;
  sparkStyle?: DashboardKpiSparkStyle;
  layout?: DashboardKpiLayout;
  /** Soft tinted card surface */
  tinted?: boolean;
  hidden?: boolean;
};

export const DASHBOARD_KPI_ACCENTS: Array<{ id: DashboardKpiAccent; label: string; swatch: string }> = [
  { id: "auto", label: "Auto", swatch: "bg-gradient-to-br from-sky-400 to-emerald-400" },
  { id: "sky", label: "Sky", swatch: "bg-sky-500" },
  { id: "emerald", label: "Green", swatch: "bg-emerald-500" },
  { id: "amber", label: "Amber", swatch: "bg-amber-500" },
  { id: "rose", label: "Rose", swatch: "bg-rose-500" },
  { id: "slate", label: "Slate", swatch: "bg-slate-500" },
  { id: "indigo", label: "Indigo", swatch: "bg-indigo-500" },
];

export const DASHBOARD_KPI_ICONS: Array<{ id: DashboardKpiIconId; label: string }> = [
  { id: "auto", label: "Auto" },
  { id: "layers", label: "Layers" },
  { id: "scan", label: "Scan" },
  { id: "check", label: "Check" },
  { id: "alert", label: "Alert" },
  { id: "ticket", label: "Ticket" },
  { id: "clipboard", label: "Clipboard" },
  { id: "activity", label: "Activity" },
  { id: "file", label: "File" },
  { id: "folder", label: "Folder" },
];

export const DASHBOARD_KPI_TONES: Array<{ id: DashboardKpiTone; label: string }> = [
  { id: "neutral", label: "Neutral" },
  { id: "success", label: "Success" },
  { id: "warning", label: "Warning" },
  { id: "danger", label: "Danger" },
];

export const DASHBOARD_KPI_VIZ_STYLES: Array<{
  id: DashboardKpiSparkStyle;
  label: string;
  hint: string;
}> = [
  { id: "auto", label: "Auto", hint: "Rotate styles across cards" },
  { id: "bars", label: "Slim bars", hint: "Classic spark bars" },
  { id: "thick", label: "Thick bars", hint: "Bold weekly columns" },
  { id: "area", label: "Area", hint: "Smooth trend wave" },
  { id: "line", label: "Line", hint: "Thin trend polyline" },
  { id: "dots", label: "Dots", hint: "Point series spark" },
  { id: "gauge", label: "Gauge", hint: "Semi-circle progress" },
  { id: "ring", label: "Ring", hint: "Circular completion" },
  { id: "progress", label: "Progress", hint: "Single horizontal bar" },
  { id: "steps", label: "Steps", hint: "Segmented fill cells" },
  { id: "split", label: "Compare", hint: "Two-tone comparison" },
  { id: "none", label: "None", hint: "Metric only" },
];

export const DASHBOARD_KPI_LAYOUTS: Array<{ id: DashboardKpiLayout; label: string }> = [
  { id: "auto", label: "Auto (stack · gauge side)" },
  { id: "stack", label: "Stack" },
  { id: "split", label: "Side chart" },
];

const VIZ_CYCLE: Exclude<DashboardKpiSparkStyle, "auto" | "none">[] = [
  "bars",
  "area",
  "gauge",
  "thick",
  "line",
  "dots",
  "ring",
  "steps",
];

/** Resolve stored style; `auto` cycles by card index for visual variety. */
export function resolveKpiVizStyle(
  style: DashboardKpiSparkStyle | undefined,
  index: number,
): Exclude<DashboardKpiSparkStyle, "auto"> {
  if (!style || style === "auto") {
    return VIZ_CYCLE[Math.abs(index) % VIZ_CYCLE.length] ?? "bars";
  }
  return style;
}

/** Prefer compact stack by default; side chart for arc/ring gauges (or explicit Layout). */
export function resolveKpiLayout(
  layout: DashboardKpiLayout | undefined,
  viz: Exclude<DashboardKpiSparkStyle, "auto">,
): Exclude<DashboardKpiLayout, "auto"> {
  if (layout === "stack" || layout === "split") return layout;
  if (viz === "gauge" || viz === "ring") return "split";
  return "stack";
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

const VIZ_SET = new Set<string>(DASHBOARD_KPI_VIZ_STYLES.map((item) => item.id));
const LAYOUT_SET = new Set<string>(DASHBOARD_KPI_LAYOUTS.map((item) => item.id));

function cleanOverride(raw: Record<string, unknown>): DashboardKpiCardOverride | null {
  const next: DashboardKpiCardOverride = {};
  if (
    raw.tone === "neutral" ||
    raw.tone === "success" ||
    raw.tone === "warning" ||
    raw.tone === "danger"
  ) {
    next.tone = raw.tone;
  }
  if (
    raw.accent === "auto" ||
    raw.accent === "sky" ||
    raw.accent === "emerald" ||
    raw.accent === "amber" ||
    raw.accent === "rose" ||
    raw.accent === "slate" ||
    raw.accent === "indigo"
  ) {
    next.accent = raw.accent;
  }
  if (
    raw.icon === "auto" ||
    raw.icon === "layers" ||
    raw.icon === "scan" ||
    raw.icon === "check" ||
    raw.icon === "alert" ||
    raw.icon === "ticket" ||
    raw.icon === "clipboard" ||
    raw.icon === "activity" ||
    raw.icon === "file" ||
    raw.icon === "folder"
  ) {
    next.icon = raw.icon;
  }
  if (typeof raw.label === "string" && raw.label.trim()) {
    next.label = raw.label.trim().slice(0, 60);
  }
  if (typeof raw.showSpark === "boolean") next.showSpark = raw.showSpark;
  if (typeof raw.sparkStyle === "string" && VIZ_SET.has(raw.sparkStyle)) {
    next.sparkStyle = raw.sparkStyle as DashboardKpiSparkStyle;
  }
  if (typeof raw.layout === "string" && LAYOUT_SET.has(raw.layout)) {
    next.layout = raw.layout as DashboardKpiLayout;
  }
  if (typeof raw.tinted === "boolean") next.tinted = raw.tinted;
  if (typeof raw.hidden === "boolean") next.hidden = raw.hidden;

  return Object.keys(next).length > 0 ? next : null;
}

export function parseKpiCardOverrides(raw: unknown): Record<string, DashboardKpiCardOverride> {
  let parsed: unknown = raw;
  if (typeof raw === "string") {
    const trimmed = raw.trim();
    if (!trimmed) return {};
    try {
      parsed = JSON.parse(trimmed);
    } catch {
      return {};
    }
  }
  if (!isObject(parsed)) return {};

  const out: Record<string, DashboardKpiCardOverride> = {};
  for (const [key, value] of Object.entries(parsed)) {
    if (!key || !isObject(value)) continue;
    const cleaned = cleanOverride(value);
    if (cleaned) out[key] = cleaned;
  }
  return out;
}

export function serializeKpiCardOverrides(
  map: Record<string, DashboardKpiCardOverride>,
): string | undefined {
  const cleaned: Record<string, DashboardKpiCardOverride> = {};
  for (const [key, value] of Object.entries(map)) {
    if (!key || !value) continue;
    const next = cleanOverride(value as unknown as Record<string, unknown>);
    if (next) cleaned[key] = next;
  }
  if (Object.keys(cleaned).length === 0) return undefined;
  return JSON.stringify(cleaned);
}

export function patchKpiCardOverride(
  raw: unknown,
  kpiKey: string,
  patch: Partial<DashboardKpiCardOverride>,
): string | undefined {
  const current = parseKpiCardOverrides(raw);
  const prev = current[kpiKey] ?? {};
  const merged: DashboardKpiCardOverride = { ...prev, ...patch };

  // Normalize “reset to default” clears
  if (patch.accent === "auto") delete merged.accent;
  if (patch.icon === "auto") delete merged.icon;
  if (patch.label === "") delete merged.label;
  if (patch.showSpark === true) delete merged.showSpark;
  if (patch.sparkStyle === "auto") delete merged.sparkStyle;
  if (patch.layout === "auto") delete merged.layout;
  if (patch.tinted === false) delete merged.tinted;
  if (patch.hidden === false) delete merged.hidden;

  const cleaned = cleanOverride(merged as unknown as Record<string, unknown>);
  if (!cleaned) {
    delete current[kpiKey];
  } else {
    current[kpiKey] = cleaned;
  }
  return serializeKpiCardOverrides(current);
}

/** Map accent → tone used by existing tile tone maps when accent is not auto. */
export function accentToTone(accent: DashboardKpiAccent | undefined, fallback: DashboardKpiTone): DashboardKpiTone {
  switch (accent) {
    case "emerald":
      return "success";
    case "amber":
      return "warning";
    case "rose":
      return "danger";
    case "sky":
    case "indigo":
    case "slate":
    case "auto":
    case undefined:
      return fallback;
    default:
      return fallback;
  }
}
