import type { DashboardCatalogEntry, DashboardWidgetSpan } from "@/lib/ui/dashboard-widget-catalog";

export const CONTROLLED_REPORTS_LAYOUT_KEY = "toweros.documents.controlled.reports.layout";

export type ControlledReportWidget = {
  id: string;
  label: string;
  description: string;
  defaultSpan: DashboardWidgetSpan;
  removable: boolean;
};

export const CONTROLLED_REPORT_WIDGETS: readonly ControlledReportWidget[] = [
  {
    id: "cdr_filters",
    label: "Filters",
    description: "Period, department, type, status, review, and file.",
    defaultSpan: "full",
    removable: false,
  },
  {
    id: "cdr_counts",
    label: "Counts",
    description: "Documents in the period, published, and obsolete.",
    defaultSpan: "full",
    removable: true,
  },
  {
    id: "cdr_publications",
    label: "Publications over time",
    description: "Documents whose published date falls in the period.",
    defaultSpan: "half",
    removable: true,
  },
  {
    id: "cdr_department",
    label: "By department",
    description: "Publication counts grouped by department.",
    defaultSpan: "half",
    removable: true,
  },
  {
    id: "cdr_type",
    label: "By document type",
    description: "Publication counts grouped by document type.",
    defaultSpan: "half",
    removable: true,
  },
  {
    id: "cdr_review",
    label: "Review horizon",
    description: "Overdue, the next 30 days, and days 31 to 90.",
    defaultSpan: "full",
    removable: true,
  },
  {
    id: "cdr_saved",
    label: "Saved reports",
    description: "Named filter sets, a manual run, and a daily or weekly schedule.",
    defaultSpan: "full",
    removable: true,
  },
];

export const CONTROLLED_REPORT_DEFAULT_IDS = CONTROLLED_REPORT_WIDGETS.map((widget) => widget.id);

export function controlledReportCatalog(): DashboardCatalogEntry[] {
  return CONTROLLED_REPORT_WIDGETS.filter((widget) => widget.removable).map((widget) => ({
    id: widget.id,
    kind: "custom",
    label: widget.label,
    description: widget.description,
    category: widget.id === "cdr_saved" ? "operations" : "charts",
    hazeSource: "analytics",
    defaultSpan: widget.defaultSpan,
    allowedSpans: widget.defaultSpan === "full" ? ["full", "half"] : ["full", "half", "third"],
    removable: true,
    hideable: true,
    bindMode: "always",
    pickerGroup: "sections",
    modules: ["workspace"],
  }));
}
