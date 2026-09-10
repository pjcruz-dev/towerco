import type {
  DashboardChartDatum,
  DashboardMultiSeries,
  DashboardScatterPoint,
} from "@/components/dashboard/dashboard-chart-utils";
import { chartColorAt } from "@/components/dashboard/dashboard-chart-utils";
import type { DashboardCatalogEntry, DashboardWidgetKind } from "@/lib/ui/dashboard-widget-catalog";
import type { ProjectOneKpi } from "@/modules/project-one/types";

export type DashboardModuleId = DashboardCatalogEntry["modules"][number];

export type DashboardActivityItem = {
  id: string;
  title: string;
  subtitle?: string;
  at?: string | null;
  href?: string;
  tone?: "neutral" | "success" | "warning" | "danger";
};

export type DashboardShortcutItem = {
  href: string;
  label: string;
  description?: string;
};

export type DashboardHeroData = {
  title: string;
  description?: string;
  ctaHref?: string;
  ctaLabel?: string;
};

export type DashboardAttentionItem = {
  id: string;
  title: string;
  subtitle?: string;
  href?: string;
  tone?: "neutral" | "success" | "warning" | "danger";
};

export type DashboardExportsTeaser = {
  href: string;
  label?: string;
  count?: number;
  message?: string;
};

/**
 * Normalized operational bag every module dashboard can fill.
 * Kind renderers consume this so Add Widget always maps to real data.
 */
export type DashboardNormalizedData = {
  kpis: ProjectOneKpi[];
  secondaryKpis?: ProjectOneKpi[];
  series: {
    status?: DashboardChartDatum[];
    priority?: DashboardChartDatum[];
    department?: DashboardChartDatum[];
    category?: DashboardChartDatum[];
    subsidiary?: DashboardChartDatum[];
    queue?: DashboardChartDatum[];
  };
  activity?: DashboardActivityItem[];
  audit?: DashboardActivityItem[];
  people?: Array<{ id: string; name: string; value: number; meta?: string }>;
  shortcuts?: DashboardShortcutItem[];
  hero?: DashboardHeroData;
  /** Phase 1 enhancements — attention / exports teaser */
  attention?: DashboardAttentionItem[];
  exportsTeaser?: DashboardExportsTeaser;
};

/** Empty payload for page boards that only use custom/slot widgets (no live metrics). */
export function emptyNormalizedData(): DashboardNormalizedData {
  return { kpis: [], series: {} };
}

/** Merge page-provided enhancement bags without dropping existing normalize output. */
export function withPageEnhancements(
  data: DashboardNormalizedData,
  patch: Partial<
    Pick<DashboardNormalizedData, "shortcuts" | "attention" | "exportsTeaser" | "activity" | "kpis" | "hero">
  >,
): DashboardNormalizedData {
  return {
    ...data,
    ...patch,
    series: data.series,
    kpis: patch.kpis ?? data.kpis,
  };
}

/** Catalog id → prefer this bound slot id when present (module-aware). */
export const CATALOG_SLOT_ALIASES: Record<
  DashboardModuleId,
  Partial<Record<string, string>>
> = {
  ticketing: {
    kpi_metric_row: "kpis",
    saas_metric_quad: "kpis",
    table: "recent_tickets",
    shortcuts: "quick_actions",
  },
  "e-approval": {
    kpi_metric_row: "kpis",
    saas_metric_quad: "kpis",
    list_progress: "queues",
  },
  "e-approval-workspace": {
    kpi_metric_row: "kpis",
    saas_metric_quad: "kpis",
    table: "submissions_table",
    filters: "submissions_table",
  },
  "doc-extract": {
    kpi_metric_row: "kpis",
    saas_metric_quad: "kpis",
    table: "batch_list",
  },
};

/** Which normalized fields a kind needs to render live data. */
export function kindDataRequirements(kind: DashboardWidgetKind): Array<keyof DashboardNormalizedData | "series.any"> {
  switch (kind) {
    case "hero_banner":
      return ["hero"];
    case "kpi_hero_chart":
    case "kpi_metric_row":
    case "kpi_single":
    case "kpi_sparkline":
    case "kpi_gauge":
      return ["kpis"];
    case "chart_line":
    case "chart_bar":
    case "chart_bar_horizontal":
    case "chart_donut":
    case "chart_area":
    case "chart_radar":
    case "chart_polar":
    case "chart_bubble":
    case "chart_scatter":
    case "chart_stacked_area":
    case "chart_multi_line":
    case "funnel_stages":
    case "pipeline_chevrons":
    case "bar_list":
    case "stacked_comparison":
    case "list_progress":
      return ["series.any"];
    case "list_activity":
      return ["activity"];
    case "list_users":
    case "list_leaderboard":
      return ["people"];
    case "shortcuts":
      return ["shortcuts"];
    case "page_tip":
    case "page_attention":
    case "page_exports":
    case "alerts":
    case "filters":
    case "table":
    case "custom":
      return [];
    default:
      return [];
  }
}

export function hasSeries(data: DashboardNormalizedData): boolean {
  const s = data.series;
  return Boolean(
    (s.status?.length ?? 0) > 0 ||
      (s.priority?.length ?? 0) > 0 ||
      (s.department?.length ?? 0) > 0 ||
      (s.category?.length ?? 0) > 0 ||
      (s.subsidiary?.length ?? 0) > 0 ||
      (s.queue?.length ?? 0) > 0 ||
      data.kpis.length > 0,
  );
}

export function pickPrimarySeries(data: DashboardNormalizedData): DashboardChartDatum[] {
  const s = data.series;
  return (
    s.queue?.length ? s.queue :
    s.status?.length ? s.status :
    s.priority?.length ? s.priority :
    s.category?.length ? s.category :
    s.department?.length ? s.department :
    s.subsidiary?.length ? s.subsidiary :
    data.kpis.map((kpi) => ({
      key: kpi.key,
      label: kpi.label,
      value: typeof kpi.value === "number" ? kpi.value : Number(kpi.value) || 0,
    }))
  );
}

export function pickDonutSeries(data: DashboardNormalizedData): DashboardChartDatum[] {
  const s = data.series;
  return (
    s.priority?.length ? s.priority :
    s.status?.length ? s.status :
    pickPrimarySeries(data)
  );
}

export function pickBarListSeries(data: DashboardNormalizedData): DashboardChartDatum[] {
  const s = data.series;
  return (
    s.category?.length ? s.category :
    s.department?.length ? s.department :
    s.subsidiary?.length ? s.subsidiary :
    pickPrimarySeries(data)
  );
}

export type DashboardDataSourceId =
  | "auto"
  | "queue"
  | "status"
  | "priority"
  | "category"
  | "department"
  | "subsidiary"
  | "kpis"
  | "secondaryKpis"
  | "activity"
  | "audit"
  | "people";

export const DASHBOARD_DATA_SOURCE_LABELS: Record<DashboardDataSourceId, string> = {
  auto: "Auto (best available)",
  queue: "Queue mix",
  status: "Status breakdown",
  priority: "Priority mix",
  category: "Category volume",
  department: "Department volume",
  subsidiary: "Subsidiary volume",
  kpis: "KPI values",
  secondaryKpis: "Secondary KPIs",
  activity: "Recent activity",
  audit: "Audit events",
  people: "People / assignees",
};

export function availableDataSources(data: DashboardNormalizedData): DashboardDataSourceId[] {
  const ids: DashboardDataSourceId[] = ["auto"];
  const s = data.series;
  if (s.queue?.length) ids.push("queue");
  if (s.status?.length) ids.push("status");
  if (s.priority?.length) ids.push("priority");
  if (s.category?.length) ids.push("category");
  if (s.department?.length) ids.push("department");
  if (s.subsidiary?.length) ids.push("subsidiary");
  if (data.kpis.length) ids.push("kpis");
  if (data.secondaryKpis?.length) ids.push("secondaryKpis");
  if (data.activity?.length) ids.push("activity");
  if (data.audit?.length) ids.push("audit");
  if (data.people?.length) ids.push("people");
  return ids;
}

function kpisAsSeries(kpis: ProjectOneKpi[]): DashboardChartDatum[] {
  return kpis.map((kpi) => ({
    key: kpi.key,
    label: kpi.label,
    value: typeof kpi.value === "number" ? kpi.value : Number(kpi.value) || 0,
  }));
}

/** Resolve chart/list series from an explicit data source setting. */
export function seriesFromDataSource(
  data: DashboardNormalizedData,
  source: string | undefined,
  fallback: "primary" | "donut" | "bar" = "primary",
): DashboardChartDatum[] {
  const s = data.series;
  switch (source) {
    case "queue":
      return s.queue?.length ? s.queue : [];
    case "status":
      return s.status?.length ? s.status : [];
    case "priority":
      return s.priority?.length ? s.priority : [];
    case "category":
      return s.category?.length ? s.category : [];
    case "department":
      return s.department?.length ? s.department : [];
    case "subsidiary":
      return s.subsidiary?.length ? s.subsidiary : [];
    case "kpis":
      return kpisAsSeries(data.kpis);
    case "secondaryKpis":
      return kpisAsSeries(data.secondaryKpis ?? []);
    case "auto":
    case undefined:
    case "":
      if (fallback === "donut") return pickDonutSeries(data);
      if (fallback === "bar") return pickBarListSeries(data);
      return pickPrimarySeries(data);
    default:
      return pickPrimarySeries(data);
  }
}

export function activityFromDataSource(
  data: DashboardNormalizedData,
  source: string | undefined,
): DashboardActivityItem[] {
  if (source === "audit") return data.audit ?? [];
  if (source === "activity") return data.activity ?? [];
  if (data.activity?.length) return data.activity;
  return data.audit ?? [];
}

type NamedSeriesPack = {
  key: string;
  label: string;
  rows: DashboardChartDatum[];
};

function seriesPacks(data: DashboardNormalizedData): NamedSeriesPack[] {
  const s = data.series;
  const packs: NamedSeriesPack[] = [
    { key: "queue", label: "Queue", rows: s.queue ?? [] },
    { key: "status", label: "Status", rows: s.status ?? [] },
    { key: "priority", label: "Priority", rows: s.priority ?? [] },
    { key: "category", label: "Category", rows: s.category ?? [] },
    { key: "department", label: "Department", rows: s.department ?? [] },
    { key: "subsidiary", label: "Subsidiary", rows: s.subsidiary ?? [] },
  ].filter((pack) => pack.rows.length > 0);

  if (packs.length === 0 && data.kpis.length > 0) {
    packs.push({ key: "kpis", label: "KPIs", rows: kpisAsSeries(data.kpis) });
  }
  if (data.secondaryKpis?.length) {
    packs.push({ key: "secondaryKpis", label: "Secondary", rows: kpisAsSeries(data.secondaryKpis) });
  }
  return packs;
}

/**
 * Align available module series into a multi-series chart bag
 * (Vuexy multi-line / stacked area / radar).
 */
export function multiSeriesFromData(
  data: DashboardNormalizedData,
  limit = 8,
): DashboardMultiSeries {
  const packs = seriesPacks(data).slice(0, 4);
  if (packs.length === 0) {
    return { categories: [], series: [] };
  }

  // Prefer shared category labels from the richest pack; fall back to index slots.
  const primary = packs.reduce((best, pack) =>
    pack.rows.length > best.rows.length ? pack : best,
  );
  const categories = primary.rows.slice(0, limit).map((row) => row.label);
  const categoryKeys = primary.rows.slice(0, limit).map((row) => row.key);

  const series = packs.map((pack, index) => {
    const byKey = new Map(pack.rows.map((row) => [row.key, row.value]));
    const byLabel = new Map(pack.rows.map((row) => [row.label, row.value]));
    const values = categories.map((label, i) => {
      const key = categoryKeys[i];
      return byKey.get(key!) ?? byLabel.get(label) ?? pack.rows[i]?.value ?? 0;
    });
    return {
      key: pack.key,
      label: pack.label,
      color: chartColorAt(index),
      values,
    };
  });

  return { categories, series };
}

/** Scatter/bubble points from a primary series (x=rank, y=value, z=value). */
export function scatterFromDataSource(
  data: DashboardNormalizedData,
  source: string | undefined,
  limit = 12,
): DashboardScatterPoint[] {
  const rows = seriesFromDataSource(data, source, "primary").slice(0, limit);
  const max = Math.max(...rows.map((row) => row.value), 1);
  return rows.map((row, index) => ({
    key: row.key,
    label: row.label,
    x: index + 1,
    y: row.value,
    z: Math.max(8, Math.round((row.value / max) * 40)),
    fill: row.fill ?? chartColorAt(index),
  }));
}

/**
 * Compare two series as x/y bubbles when a second pack exists;
 * otherwise fall back to rank vs value.
 */
export function bubbleFromData(data: DashboardNormalizedData, limit = 12): DashboardScatterPoint[] {
  const packs = seriesPacks(data);
  if (packs.length >= 2) {
    const [a, b] = packs;
    const len = Math.min(limit, a!.rows.length, b!.rows.length);
    return Array.from({ length: len }, (_, index) => {
      const left = a!.rows[index]!;
      const right = b!.rows[index]!;
      return {
        key: left.key,
        label: left.label,
        x: left.value,
        y: right.value,
        z: Math.max(10, Math.round((left.value + right.value) / 2)),
        fill: chartColorAt(index),
      };
    });
  }
  return scatterFromDataSource(data, "auto", limit);
}

export function dataSatisfiesKind(kind: DashboardWidgetKind, data: DashboardNormalizedData): boolean {
  const reqs = kindDataRequirements(kind);
  if (reqs.length === 0) return false;
  return reqs.every((req) => {
    if (req === "series.any") return hasSeries(data);
    if (req === "kpis") return data.kpis.length > 0;
    if (req === "secondaryKpis") return (data.secondaryKpis?.length ?? 0) > 0;
    if (req === "activity") return (data.activity?.length ?? 0) > 0 || (data.audit?.length ?? 0) > 0;
    if (req === "audit") return (data.audit?.length ?? 0) > 0;
    if (req === "people") return (data.people?.length ?? 0) > 0;
    if (req === "shortcuts") return (data.shortcuts?.length ?? 0) > 0;
    if (req === "hero") return Boolean(data.hero?.title);
    if (req === "series") return hasSeries(data);
    return false;
  });
}

export function resolveCatalogSlotId(
  moduleId: DashboardModuleId,
  catalogId: string,
): string {
  return CATALOG_SLOT_ALIASES[moduleId]?.[catalogId] ?? catalogId;
}
