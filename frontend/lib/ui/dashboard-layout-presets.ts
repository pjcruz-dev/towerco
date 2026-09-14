import { getCatalogEntry, type DashboardWidgetSpan } from "@/lib/ui/dashboard-widget-catalog";
import type { DashboardLayoutPrefs } from "@/lib/ui/dashboard-widget-registry";

/** Instance ids: `chart_donut` or `chart_donut~2` for duplicates. */
export function catalogBaseId(widgetId: string): string {
  const idx = widgetId.indexOf("~");
  return idx >= 0 ? widgetId.slice(0, idx) : widgetId;
}

export function nextDuplicateWidgetId(widgetId: string, existingIds: string[]): string {
  const base = catalogBaseId(widgetId);
  const taken = new Set(existingIds);
  if (!taken.has(base)) return base;
  let n = 2;
  while (taken.has(`${base}~${n}`)) n += 1;
  return `${base}~${n}`;
}

export type DashboardLayoutPresetId =
  | "default"
  | "compact"
  | "focus_half"
  | "analytics"
  | "ops"
  | "manager"
  | "auditor"
  | "reset";

export const DASHBOARD_LAYOUT_PRESETS: Array<{
  id: DashboardLayoutPresetId;
  label: string;
  description: string;
  /** Role packs change which widgets are enabled; density packs only retile. */
  kind: "density" | "role" | "reset";
}> = [
  {
    id: "ops",
    label: "Ops",
    description: "Filters, attention, and the primary table — lean queue work.",
    kind: "role",
  },
  {
    id: "manager",
    label: "Manager",
    description: "KPIs, shortcuts, attention, and the primary table.",
    kind: "role",
  },
  {
    id: "auditor",
    label: "Auditor",
    description: "Table, activity, and exports — denser review layout.",
    kind: "role",
  },
  {
    id: "compact",
    label: "Compact",
    description: "Half-width cards + compact density (keep widgets).",
    kind: "density",
  },
  {
    id: "focus_half",
    label: "Split view",
    description: "Alternate full / half for scanning (keep widgets).",
    kind: "density",
  },
  {
    id: "analytics",
    label: "Analytics",
    description: "Charts prefer half; lists denser (keep widgets).",
    kind: "density",
  },
  {
    id: "default",
    label: "Clear density",
    description: "Reset spans and density; keep the same widgets.",
    kind: "density",
  },
  {
    id: "reset",
    label: "Reset page defaults",
    description: "Restore default sections and clear personal layout overrides.",
    kind: "reset",
  },
];

/** Preferred widget base ids for role packs (first match wins order). */
const ROLE_PREFER: Record<"ops" | "manager" | "auditor", string[]> = {
  ops: [
    "filters",
    "scope_tabs",
    "alerts",
    "page_attention",
    "awaiting_me",
    "table",
    "batch_list",
    "submissions_table",
    "recent_tickets",
    "template_grid",
    "queues",
    "queue_awaiting",
    "queue_attention",
    "action_queue",
  ],
  manager: [
    "page_kpi_strip",
    "kpis",
    "kpi_metric_row",
    "page_shortcuts",
    "shortcuts",
    "quick_actions",
    "page_attention",
    "awaiting_me",
    "filters",
    "scope_tabs",
    "table",
    "batch_list",
    "submissions_table",
    "recent_tickets",
    "template_grid",
    "chart_donut",
    "chart_bar",
    "chart_ticket_queue",
    "chart_by_priority",
    "chart_by_department",
    "chart_by_category",
    "table_category_analytics",
    "queue_awaiting",
    "queue_attention",
    "chart_by_status",
    "chart_by_subsidiary",
    "action_charts",
  ],
  auditor: [
    "filters",
    "scope_tabs",
    "table",
    "batch_list",
    "submissions_table",
    "recent_tickets",
    "template_grid",
    "recent_activity",
    "page_activity",
    "list_activity",
    "audit_log",
    "page_exports",
  ],
};

export type ApplyLayoutPresetOptions = {
  /** Page-native default sections (used by reset + required fallback). */
  defaultEnabledIds?: string[];
  /** All ids that can be enabled on this page (slots + bindable catalog). */
  availableIds?: string[];
};

function uniquePreserve(ids: string[]): string[] {
  const seen = new Set<string>();
  const out: string[] = [];
  for (const id of ids) {
    if (seen.has(id)) continue;
    seen.add(id);
    out.push(id);
  }
  return out;
}

function requiredAvailableIds(availableIds: string[]): string[] {
  return availableIds.filter((id) => {
    const entry = getCatalogEntry(id);
    return entry?.removable === false || entry?.hideable === false;
  });
}

/** Pick role-preferred widgets that exist on this page; always keep required ones. */
export function resolveRoleEnabledIds(
  role: "ops" | "manager" | "auditor",
  availableIds: string[],
  defaultEnabledIds: string[],
): string[] {
  const available = new Set(availableIds);
  const preferred = ROLE_PREFER[role].filter((id) => available.has(id));
  const required = requiredAvailableIds(availableIds);
  const fallback = defaultEnabledIds.filter((id) => available.has(id));
  const picked = uniquePreserve([...preferred, ...required]);
  return picked.length > 0 ? picked : fallback.length > 0 ? fallback : [...availableIds];
}

function applyDensityToIds(
  layout: DashboardLayoutPrefs,
  preset: Exclude<DashboardLayoutPresetId, "ops" | "manager" | "auditor" | "reset">,
  orderedIds: string[],
): DashboardLayoutPrefs {
  if (preset === "default") {
    return {
      ...layout,
      spans: {},
      widgetOptions: Object.fromEntries(
        Object.entries(layout.widgetOptions).map(([id, opt]) => [
          id,
          {
            ...opt,
            span: undefined,
            settings: {
              ...(opt.settings ?? {}),
              compact: undefined,
              collapsed: undefined,
            },
          },
        ]),
      ),
    };
  }

  const spans: Record<string, DashboardWidgetSpan> = { ...layout.spans };
  const widgetOptions = { ...layout.widgetOptions };

  orderedIds.forEach((id, index) => {
    let span: DashboardWidgetSpan = "full";
    let compact = false;
    if (preset === "compact") {
      span = index === 0 ? "full" : "half";
      compact = true;
    } else if (preset === "focus_half") {
      span = index % 2 === 0 ? "full" : "half";
    } else if (preset === "analytics") {
      span = index === 0 ? "full" : "half";
      compact = index > 0;
    }
    spans[id] = span;
    widgetOptions[id] = {
      ...widgetOptions[id],
      span,
      settings: {
        ...(widgetOptions[id]?.settings ?? {}),
        compact: compact || undefined,
      },
    };
  });

  return { ...layout, spans, widgetOptions };
}

function applyRolePack(
  layout: DashboardLayoutPrefs,
  role: "ops" | "manager" | "auditor",
  availableIds: string[],
  defaultEnabledIds: string[],
): DashboardLayoutPrefs {
  const enabled = resolveRoleEnabledIds(role, availableIds, defaultEnabledIds);
  const withEnabled: DashboardLayoutPrefs = {
    ...layout,
    enabledWidgetIds: enabled,
    widgetOrder: enabled,
    hiddenWidgetIds: [],
  };

  if (role === "auditor") {
    return applyDensityToIds(withEnabled, "compact", enabled);
  }
  if (role === "manager") {
    return applyDensityToIds(withEnabled, "focus_half", enabled);
  }
  // Ops: primary sections full width
  return applyDensityToIds(withEnabled, "default", enabled);
}

/**
 * Apply a layout preset.
 * - density: retile current widgets
 * - role: enable a curated subset available on this page
 * - reset: restore page default sections and clear personal overrides
 */
export function applyLayoutPreset(
  layout: DashboardLayoutPrefs,
  preset: DashboardLayoutPresetId,
  orderedIds: string[],
  options: ApplyLayoutPresetOptions = {},
): DashboardLayoutPrefs {
  const defaultEnabledIds =
    options.defaultEnabledIds && options.defaultEnabledIds.length > 0
      ? options.defaultEnabledIds
      : orderedIds;
  const availableIds =
    options.availableIds && options.availableIds.length > 0
      ? uniquePreserve([...options.availableIds, ...defaultEnabledIds])
      : uniquePreserve([
          ...orderedIds,
          ...layout.enabledWidgetIds,
          ...defaultEnabledIds,
        ]);

  if (preset === "reset") {
    return {
      ...layout,
      enabledWidgetIds: [...defaultEnabledIds],
      widgetOrder: [...defaultEnabledIds],
      hiddenWidgetIds: [],
      spans: {},
      widgetOptions: {},
      pageChrome: {},
    };
  }

  if (preset === "ops" || preset === "manager" || preset === "auditor") {
    return applyRolePack(layout, preset, availableIds, defaultEnabledIds);
  }

  const currentOrder =
    orderedIds.length > 0
      ? orderedIds
      : layout.enabledWidgetIds.length > 0
        ? layout.enabledWidgetIds
        : defaultEnabledIds;

  return applyDensityToIds(layout, preset, currentOrder);
}

export function duplicateWidgetInLayout(
  layout: DashboardLayoutPrefs,
  widgetId: string,
  currentOrder: string[],
  defaultEnabledIds: string[],
): DashboardLayoutPrefs {
  const enabled =
    layout.enabledWidgetIds.length > 0 ? [...layout.enabledWidgetIds] : [...defaultEnabledIds];
  const order = currentOrder.length > 0 ? [...currentOrder] : [...enabled];
  const newId = nextDuplicateWidgetId(widgetId, [...enabled, ...order]);
  const at = order.indexOf(widgetId);
  const insertAt = at >= 0 ? at + 1 : order.length;
  order.splice(insertAt, 0, newId);
  if (!enabled.includes(newId)) enabled.push(newId);

  const sourceOpts = layout.widgetOptions[widgetId];
  const sourceSpan = layout.spans[widgetId] ?? sourceOpts?.span;

  return {
    ...layout,
    enabledWidgetIds: enabled,
    widgetOrder: order,
    spans: sourceSpan ? { ...layout.spans, [newId]: sourceSpan } : layout.spans,
    widgetOptions: {
      ...layout.widgetOptions,
      [newId]: sourceOpts
        ? {
            ...sourceOpts,
            title: sourceOpts.title ? `${sourceOpts.title} (copy)` : undefined,
            settings: { ...(sourceOpts.settings ?? {}) },
          }
        : { title: "Copy" },
    },
  };
}
