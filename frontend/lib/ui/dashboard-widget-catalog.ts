/**
 * Haze-inspired widget catalog for INFRA SUITE dashboards.
 * Captured from Overview / Analytics / CRM / SaaS reference layouts.
 * Each entry is a choosable type; modules bind real operational data via renderers.
 */

import { reportsBoardCatalogEntries } from "@/lib/e-approval/reports-board-config";

export type DashboardWidgetSpan = "full" | "half" | "third" | "quarter";

export type DashboardWidgetKind =
  | "hero_banner"
  | "kpi_hero_chart"
  | "kpi_metric_row"
  | "kpi_single"
  | "kpi_sparkline"
  | "kpi_gauge"
  | "chart_line"
  | "chart_bar"
  | "chart_bar_horizontal"
  | "chart_donut"
  | "chart_area"
  | "chart_multi_line"
  | "chart_stacked_area"
  | "chart_radar"
  | "chart_polar"
  | "chart_bubble"
  | "chart_scatter"
  | "funnel_stages"
  | "pipeline_chevrons"
  | "list_progress"
  | "list_leaderboard"
  | "list_activity"
  | "list_users"
  | "bar_list"
  | "stacked_comparison"
  | "alerts"
  | "filters"
  | "table"
  | "shortcuts"
  | "page_tip"
  | "page_attention"
  | "page_exports"
  | "custom";

export type DashboardWidgetCategory =
  | "summary"
  | "charts"
  | "lists"
  | "pipeline"
  | "operations"
  | "navigation"
  | "enhancements";

/** Add-widget picker buckets (Phase 1). */
export type DashboardPickerGroup = "sections" | "enhancements" | "charts" | "other";

export type DashboardWidgetOptions = {
  title?: string;
  span?: DashboardWidgetSpan;
  /**
   * Functional per-widget settings:
   * - dataSource: which live series/bag to chart/list
   * - kpiKey: which KPI for single/gauge cards
   * - limit: max rows in lists
   * - compact: denser list/chart padding
   * - showDescription: show catalog description under title
   * - minHeight: reserved via settings from resize
   * - tipTitle / tipBody: page tip card copy
   */
  settings?: {
    dataSource?: string;
    kpiKey?: string;
    limit?: number;
    compact?: boolean;
    showDescription?: boolean;
    minHeight?: number;
    tipTitle?: string;
    tipBody?: string;
    [key: string]: string | number | boolean | undefined;
  };
};

export type DashboardCatalogEntry = {
  id: string;
  kind: DashboardWidgetKind;
  label: string;
  description: string;
  category: DashboardWidgetCategory;
  /** Haze reference surface this pattern came from */
  hazeSource: "overview" | "analytics" | "crm" | "saas" | "shared";
  defaultSpan: DashboardWidgetSpan;
  allowedSpans: DashboardWidgetSpan[];
  /** Soft-hide / remove from board; false = always required when enabled by admin */
  removable?: boolean;
  hideable?: boolean;
  /** Default Layout & options settings (dataSource, limit, sort, …) */
  defaultSettings?: DashboardWidgetOptions["settings"];
  /**
   * When "always", Add Widget lists this even without live normalized data
   * (enhancements show empty states until the page fills bags).
   */
  bindMode?: "data" | "always";
  /** Explicit Add-widget group; otherwise inferred from category/kind. */
  pickerGroup?: DashboardPickerGroup;
  /** Shown in Add Widget by default for these modules */
  modules: Array<"e-approval" | "e-approval-workspace" | "ticketing" | "doc-extract" | "workspace">;
};

/**
 * Full captured widget list — apply per module via enabledWidgetIds.
 * Ops labels map Haze patterns to INFRA SUITE (approvals / tickets / extraction).
 */
export const DASHBOARD_WIDGET_CATALOG: DashboardCatalogEntry[] = [
  // --- Overview (Haze) ---
  {
    id: "hero_banner",
    kind: "hero_banner",
    label: "Welcome banner",
    description: "Full-width greeting with primary CTA.",
    category: "summary",
    hazeSource: "overview",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },
  {
    id: "kpi_hero_chart",
    kind: "kpi_hero_chart",
    label: "Hero KPI + trend",
    description: "Large metric with area/line trend (Haze revenue hero).",
    category: "summary",
    hazeSource: "overview",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "kpi_gauge",
    kind: "kpi_gauge",
    label: "Goal / gauge",
    description: "Radial progress toward a target (Haze monthly goal / win rate).",
    category: "summary",
    hazeSource: "overview",
    defaultSpan: "third",
    allowedSpans: ["full", "half", "third", "quarter"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "chart_bar",
    kind: "chart_bar",
    label: "Vertical bar chart",
    description: "Column bars for volume over categories (Vuexy Latest Statistics).",
    category: "charts",
    hazeSource: "overview",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract"],
  },
  {
    id: "chart_bar_horizontal",
    kind: "chart_bar_horizontal",
    label: "Horizontal bar chart",
    description: "Category ranking bars (Vuexy Balance style).",
    category: "charts",
    hazeSource: "overview",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract"],
  },
  {
    id: "chart_line",
    kind: "chart_line",
    label: "Area / line chart",
    description: "Single-series smooth trend with fill (Vuexy area style).",
    category: "charts",
    hazeSource: "overview",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "chart_multi_line",
    kind: "chart_multi_line",
    label: "Multi-line chart",
    description: "Compare status, priority, and other series as curved lines.",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },
  {
    id: "chart_radar",
    kind: "chart_radar",
    label: "Radar chart",
    description: "Spider overlay comparing two operational series.",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "chart_polar",
    kind: "chart_polar",
    label: "Polar / radial chart",
    description: "Radial skill-style breakdown (Vuexy Average Skills).",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third", "quarter"],
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract"],
  },
  {
    id: "chart_bubble",
    kind: "chart_bubble",
    label: "Bubble chart",
    description: "Compare two series with bubble size as magnitude.",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "half",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "chart_scatter",
    kind: "chart_scatter",
    label: "Scatter plot",
    description: "Point distribution by rank and value (Vuexy New Product Data).",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "half",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },
  {
    id: "chart_stacked_area",
    kind: "chart_stacked_area",
    label: "Stacked area chart",
    description: "Cumulative multi-series area (Vuexy Data Science).",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "funnel_stages",
    kind: "funnel_stages",
    label: "Conversion funnel",
    description: "Horizontal stage funnel with conversion rates.",
    category: "pipeline",
    hazeSource: "overview",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "list_progress",
    kind: "list_progress",
    label: "Progress list",
    description: "Ranked rows with progress bars (Haze top products / open deals).",
    category: "lists",
    hazeSource: "overview",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },
  {
    id: "list_activity",
    kind: "list_activity",
    label: "Activity timeline",
    description: "Recent events with status dots and timestamps.",
    category: "lists",
    hazeSource: "overview",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "e-approval-workspace", "ticketing"],
  },

  // --- Analytics (Haze) ---
  {
    id: "chart_area",
    kind: "chart_area",
    label: "Traffic / area chart",
    description: "Filled single-series area insight chart.",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "chart_donut",
    kind: "chart_donut",
    label: "Donut breakdown",
    description: "Share-of-total donut (device / priority / status) — Vuexy User by Devices.",
    category: "charts",
    hazeSource: "analytics",
    defaultSpan: "third",
    allowedSpans: ["full", "half", "third", "quarter"],
    modules: ["e-approval", "e-approval-workspace", "ticketing"],
  },
  {
    id: "kpi_metric_row",
    kind: "kpi_metric_row",
    label: "KPI metric row",
    description: "Row of compact KPI cards with sparklines/gauges.",
    category: "summary",
    hazeSource: "analytics",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract"],
  },
  {
    id: "bar_list",
    kind: "bar_list",
    label: "Horizontal bar list",
    description: "Category rows with fill bars (channels / pages / reasons).",
    category: "lists",
    hazeSource: "analytics",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract"],
  },
  {
    id: "list_users",
    kind: "list_users",
    label: "People / region list",
    description: "Avatar or flag rows with counts (region / trials style).",
    category: "lists",
    hazeSource: "analytics",
    defaultSpan: "third",
    allowedSpans: ["full", "half", "third"],
    modules: ["ticketing", "e-approval"],
  },

  // --- CRM (Haze) ---
  {
    id: "pipeline_chevrons",
    kind: "pipeline_chevrons",
    label: "Pipeline stages",
    description: "Chevron stage strip with value + count per stage.",
    category: "pipeline",
    hazeSource: "crm",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "kpi_single",
    kind: "kpi_single",
    label: "Single KPI card",
    description: "One large number with trend subtitle.",
    category: "summary",
    hazeSource: "crm",
    defaultSpan: "third",
    allowedSpans: ["full", "half", "third", "quarter"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },
  {
    id: "kpi_sparkline",
    kind: "kpi_sparkline",
    label: "KPI + sparkline",
    description: "Metric with mini trend line.",
    category: "summary",
    hazeSource: "crm",
    defaultSpan: "third",
    allowedSpans: ["full", "half", "third", "quarter"],
    modules: ["e-approval", "ticketing"],
  },
  {
    id: "list_leaderboard",
    kind: "list_leaderboard",
    label: "Leaderboard",
    description: "Ranked people with totals and win rate.",
    category: "lists",
    hazeSource: "crm",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["ticketing", "e-approval"],
  },
  {
    id: "stacked_comparison",
    kind: "stacked_comparison",
    label: "Stacked comparison",
    description: "Stacked bars comparing totals vs converted.",
    category: "charts",
    hazeSource: "crm",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },

  // --- SaaS (Haze) ---
  {
    id: "saas_metric_quad",
    kind: "kpi_metric_row",
    label: "Four metric tiles",
    description: "ARR-style quad: value, segmented bar, churn, LTV patterns.",
    category: "summary",
    hazeSource: "saas",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },

  // --- Operations (INFRA native) ---
  {
    id: "alerts",
    kind: "alerts",
    label: "Status alerts",
    description: "Actionable scanning / failure banners.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["doc-extract", "e-approval"],
  },
  {
    id: "filters",
    kind: "filters",
    label: "Filter bar",
    description: "Status chips and search controls.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["doc-extract", "ticketing", "e-approval-workspace", "e-approval"],
  },
  {
    id: "table",
    kind: "table",
    label: "Data table",
    description: "Primary operational table (submissions / tickets / batches).",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    removable: false,
    hideable: false,
    modules: ["e-approval-workspace", "doc-extract", "ticketing", "e-approval"],
  },
  {
    id: "scope_tabs",
    kind: "filters",
    label: "Scope tabs",
    description: "Awaiting me / All (or similar scope chips).",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval"],
  },
  {
    id: "template_gallery",
    kind: "custom",
    label: "Starter templates",
    description: "Quick-start form template gallery.",
    category: "navigation",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval"],
  },
  {
    id: "import_panel",
    kind: "custom",
    label: "Import panel",
    description: "JSON import for forms.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval"],
  },
  {
    id: "template_grid",
    kind: "custom",
    label: "Template catalog",
    description: "DocExtract reusable column templates.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    removable: false,
    hideable: false,
    modules: ["doc-extract"],
  },
  {
    id: "shortcuts",
    kind: "shortcuts",
    label: "Quick actions",
    description: "Navigation tiles to common workflows.",
    category: "navigation",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval", "ticketing", "doc-extract"],
  },
  {
    id: "queues",
    kind: "list_progress",
    label: "Approval queues",
    description: "Awaiting approval + needs attention panels.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["e-approval"],
  },
  {
    id: "recent_tickets",
    kind: "table",
    label: "Recent tickets",
    description: "Latest ticket rows with status and priority.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["ticketing"],
  },
  {
    id: "category_analytics",
    kind: "bar_list",
    label: "Category analytics",
    description: "Volume and SLA by ticket category.",
    category: "charts",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["ticketing"],
  },
  // --- Bound module widgets (real data renderers) ---
  {
    id: "kpis",
    kind: "kpi_metric_row",
    label: "KPI strip",
    description: "Primary operational KPI cards for the module.",
    category: "summary",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third", "quarter"],
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract", "workspace"],
  },
  {
    id: "finance",
    kind: "kpi_metric_row",
    label: "Finance & procurement",
    description: "Finance KPI strip when available on E-Forms overview.",
    category: "summary",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval"],
  },
  {
    id: "status_chart",
    kind: "chart_donut",
    label: "Status & subsidiary charts",
    description: "Status mix and subsidiary volume for a form workspace.",
    category: "charts",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval-workspace"],
  },
  {
    id: "recent_activity",
    kind: "list_activity",
    label: "Recent activity",
    description: "Latest workspace submission activity.",
    category: "lists",
    hazeSource: "shared",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval-workspace"],
  },
  {
    id: "audit_log",
    kind: "list_activity",
    label: "Audit log",
    description: "Recent audit events for the form workspace.",
    category: "lists",
    hazeSource: "shared",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    modules: ["e-approval-workspace"],
  },
  {
    id: "submissions_table",
    kind: "table",
    label: "Submissions table",
    description: "Primary submissions list with filters and export.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    removable: false,
    hideable: false,
    modules: ["e-approval-workspace"],
  },
  {
    id: "batch_list",
    kind: "table",
    label: "Extracted batches",
    description: "Doc Extract batch list with status and reopen actions.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    removable: false,
    hideable: false,
    modules: ["doc-extract"],
  },
  {
    id: "queue_charts",
    kind: "chart_bar",
    label: "Queue & priority charts",
    description: "Ticket volume by queue and priority.",
    category: "charts",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["ticketing"],
  },
  {
    id: "quick_actions",
    kind: "shortcuts",
    label: "Quick actions",
    description: "Shortcuts into ticket create and filtered queues.",
    category: "navigation",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half", "third"],
    modules: ["ticketing"],
  },

  // --- Tenant workspace home (/dashboard) ---
  {
    id: "awaiting_me",
    kind: "custom",
    label: "Awaiting you",
    description: "Gate approvals, e-approvals, and tickets assigned to you.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    removable: false,
    hideable: false,
    pickerGroup: "sections",
    modules: ["workspace"],
  },
  {
    id: "action_charts",
    kind: "custom",
    label: "Action & attention charts",
    description: "Queues and attention mix across modules.",
    category: "charts",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    pickerGroup: "sections",
    modules: ["workspace"],
  },
  {
    id: "action_queue",
    kind: "custom",
    label: "Action queue",
    description: "Prioritized follow-ups with deep links.",
    category: "operations",
    hazeSource: "shared",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    pickerGroup: "sections",
    modules: ["workspace"],
  },
  {
    id: "recent_activity",
    kind: "list_activity",
    label: "Recent activity",
    description: "Latest notifications and workflow updates.",
    category: "lists",
    hazeSource: "shared",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    pickerGroup: "sections",
    modules: ["workspace"],
  },

  // --- Page enhancements (Phase 1) — always addable; pages fill data in Phase 2 ---
  {
    id: "page_shortcuts",
    kind: "shortcuts",
    label: "Shortcuts strip",
    description: "Quick links (New, Approvals, Reports, Help). Fill via page data.",
    category: "enhancements",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    bindMode: "always",
    pickerGroup: "enhancements",
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract", "workspace"],
  },
  {
    id: "page_kpi_strip",
    kind: "kpi_metric_row",
    label: "Pinned KPI strip",
    description: "3–5 live metrics above the list when the page provides KPIs.",
    category: "enhancements",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    bindMode: "always",
    pickerGroup: "enhancements",
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract", "workspace"],
  },
  {
    id: "page_attention",
    kind: "page_attention",
    label: "Attention banner",
    description: "Overdue, failed, awaiting-me, SLA at-risk, or returned items.",
    category: "enhancements",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    bindMode: "always",
    pickerGroup: "enhancements",
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract", "workspace"],
  },
  {
    id: "page_activity",
    kind: "list_activity",
    label: "Recent activity",
    description: "Latest approvals, ticket updates, or extract events.",
    category: "enhancements",
    hazeSource: "shared",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    bindMode: "always",
    pickerGroup: "enhancements",
    defaultSettings: { limit: 8 },
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract", "workspace"],
  },
  {
    id: "page_tip",
    kind: "page_tip",
    label: "Page tip / note",
    description: "Tenant-editable tip under Layout & options (no code deploy).",
    category: "enhancements",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    bindMode: "always",
    pickerGroup: "enhancements",
    defaultSettings: {
      tipTitle: "Tip",
      tipBody: "Add onboarding guidance for your team in Layout & options.",
    },
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract", "workspace"],
  },
  {
    id: "page_exports",
    kind: "page_exports",
    label: "My exports teaser",
    description: "Link to Reports / My exports with optional pending count.",
    category: "enhancements",
    hazeSource: "shared",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    bindMode: "always",
    pickerGroup: "enhancements",
    modules: ["e-approval", "e-approval-workspace", "ticketing", "doc-extract", "workspace"],
  },

  ...reportsBoardCatalogEntries(),
];

export const DASHBOARD_WIDGET_CATEGORY_LABELS: Record<DashboardWidgetCategory, string> = {
  summary: "Summary",
  charts: "Charts",
  lists: "Lists",
  pipeline: "Pipeline",
  operations: "Operations",
  navigation: "Navigation",
  enhancements: "Enhancements",
};

export const DASHBOARD_PICKER_GROUP_LABELS: Record<DashboardPickerGroup, string> = {
  sections: "Page sections",
  enhancements: "Enhancements",
  charts: "Charts",
  other: "More",
};

export function resolvePickerGroup(entry: DashboardCatalogEntry): DashboardPickerGroup {
  if (entry.pickerGroup) return entry.pickerGroup;
  if (entry.category === "enhancements") return "enhancements";
  if (entry.category === "charts") return "charts";
  if (
    entry.kind === "filters" ||
    entry.kind === "table" ||
    entry.kind === "alerts" ||
    entry.category === "operations"
  ) {
    return "sections";
  }
  if (entry.category === "summary" || entry.category === "lists" || entry.category === "pipeline") {
    return "charts";
  }
  return "other";
}

export function catalogEntriesForModule(
  moduleId: DashboardCatalogEntry["modules"][number],
): DashboardCatalogEntry[] {
  return DASHBOARD_WIDGET_CATALOG.filter((entry) => entry.modules.includes(moduleId));
}

export function getCatalogEntry(id: string): DashboardCatalogEntry | undefined {
  const base = id.includes("~") ? id.slice(0, id.indexOf("~")) : id;
  return DASHBOARD_WIDGET_CATALOG.find((entry) => entry.id === base);
}

export const SPAN_CLASS: Record<DashboardWidgetSpan, string> = {
  full: "col-span-12",
  half: "col-span-12 md:col-span-6",
  third: "col-span-12 md:col-span-4",
  /** 3 of 12 from md up so four quarters pack in one row */
  quarter: "col-span-12 md:col-span-3",
};
