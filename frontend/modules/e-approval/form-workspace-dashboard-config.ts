import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type WorkspaceWidgetType = "kpis" | "status_chart" | "recent_activity" | "audit_log" | "submissions_table";

export type WorkspaceDashboardWidget = {
  id: string;
  type: WorkspaceWidgetType;
  enabled: boolean;
  order: number;
};

export type WorkspaceTableColumn = {
  key: string;
  label: string;
  kind: "system" | "field";
  field_name?: string;
  visible: boolean;
  order: number;
};

export type WorkspaceSavedView = {
  id: string;
  label: string;
  status?: string;
  mine?: boolean;
  period_days?: number;
  order: number;
};

export type FormWorkspaceDashboardSettings = {
  widgets: WorkspaceDashboardWidget[];
  table_columns: WorkspaceTableColumn[];
  saved_views: WorkspaceSavedView[];
};

export const WORKSPACE_WIDGET_LABELS: Record<WorkspaceWidgetType, string> = {
  kpis: "KPI strip",
  status_chart: "Status breakdown",
  recent_activity: "Recent activity",
  audit_log: "Workspace audit log",
  submissions_table: "Submissions table",
};

const SKIP_FIELD_TYPES = new Set([
  "section",
  "page_break",
  "divider",
  "info",
  "heading",
  "html",
  "file",
  "attachment",
  "signature",
]);

export const DEFAULT_WORKSPACE_DASHBOARD: FormWorkspaceDashboardSettings = {
  widgets: [
    { id: "kpis", type: "kpis", enabled: true, order: 1 },
    { id: "status_chart", type: "status_chart", enabled: true, order: 2 },
    { id: "recent_activity", type: "recent_activity", enabled: true, order: 3 },
    { id: "audit_log", type: "audit_log", enabled: false, order: 4 },
    { id: "submissions_table", type: "submissions_table", enabled: true, order: 5 },
  ],
  table_columns: [
    { key: "document_no", label: "Document", kind: "system", visible: true, order: 1 },
    { key: "status", label: "Status", kind: "system", visible: true, order: 2 },
    { key: "requestor", label: "Requestor", kind: "system", visible: true, order: 3 },
    { key: "current_step", label: "Step", kind: "system", visible: true, order: 4 },
  ],
  saved_views: [
    { id: "all", label: "All", order: 1 },
    { id: "pending", label: "Pending", status: "pending", order: 2 },
    { id: "returned", label: "Needs revision", status: "returned", order: 3 },
    { id: "mine", label: "Mine", mine: true, order: 4 },
    { id: "this_month", label: "This month", period_days: 30, order: 5 },
  ],
};

function exportableFields(fields: EApprovalFormFieldInput[]): EApprovalFormFieldInput[] {
  return fields.filter((field) => field.name?.trim() && !SKIP_FIELD_TYPES.has(field.type));
}

export function buildDefaultTableColumns(fields: EApprovalFormFieldInput[]): WorkspaceTableColumn[] {
  const columns = [...DEFAULT_WORKSPACE_DASHBOARD.table_columns];
  let order = columns.length + 1;

  for (const field of exportableFields(fields).slice(0, 3)) {
    const name = field.name.trim();
    columns.push({
      key: `field:${name}`,
      label: field.label?.trim() || name,
      kind: "field",
      field_name: name,
      visible: true,
      order,
    });
    order += 1;
  }

  return columns;
}

export function dashboardSettingsFromMetadata(
  metadata: Record<string, unknown> | null | undefined,
  fields: EApprovalFormFieldInput[] = [],
): FormWorkspaceDashboardSettings {
  const workspace =
    metadata?.workspace && typeof metadata.workspace === "object"
      ? (metadata.workspace as Record<string, unknown>)
      : null;
  const raw =
    workspace?.dashboard && typeof workspace.dashboard === "object"
      ? (workspace.dashboard as Record<string, unknown>)
      : null;

  if (!raw) {
    return {
      ...DEFAULT_WORKSPACE_DASHBOARD,
      table_columns: buildDefaultTableColumns(fields),
    };
  }

  return {
    widgets: parseWidgets(raw.widgets),
    table_columns: parseTableColumns(raw.table_columns, fields),
    saved_views: parseSavedViews(raw.saved_views),
  };
}

function parseWidgets(value: unknown): WorkspaceDashboardWidget[] {
  if (!Array.isArray(value) || value.length === 0) {
    return DEFAULT_WORKSPACE_DASHBOARD.widgets;
  }

  const widgets = value
    .map((item, index) => {
      if (!item || typeof item !== "object") return null;
      const row = item as Record<string, unknown>;
      const type = String(row.type ?? "") as WorkspaceWidgetType;
      if (!WORKSPACE_WIDGET_LABELS[type]) return null;
      return {
        id: String(row.id ?? type),
        type,
        enabled: row.enabled !== false,
        order: Number(row.order ?? index + 1),
      };
    })
    .filter((item): item is WorkspaceDashboardWidget => item !== null);

  return widgets.length > 0 ? widgets.sort((a, b) => a.order - b.order) : DEFAULT_WORKSPACE_DASHBOARD.widgets;
}

function parseTableColumns(
  value: unknown,
  fields: EApprovalFormFieldInput[],
): WorkspaceTableColumn[] {
  if (!Array.isArray(value) || value.length === 0) {
    return buildDefaultTableColumns(fields);
  }

  const columns = value
    .map((item, index) => {
      if (!item || typeof item !== "object") return null;
      const row = item as Record<string, unknown>;
      const kind = row.kind === "field" ? "field" : "system";
      const key = String(row.key ?? "").trim();
      if (!key) return null;
      const fieldName =
        kind === "field"
          ? String(row.field_name ?? (key.startsWith("field:") ? key.slice(6) : "")).trim()
          : undefined;
      return {
        key: kind === "field" && fieldName ? `field:${fieldName}` : key,
        label: String(row.label ?? key),
        kind,
        field_name: fieldName || undefined,
        visible: row.visible !== false,
        order: Number(row.order ?? index + 1),
      } as WorkspaceTableColumn;
    })
    .filter((item): item is WorkspaceTableColumn => item !== null);

  return columns.length > 0 ? columns.sort((a, b) => a.order - b.order) : buildDefaultTableColumns(fields);
}

function parseSavedViews(value: unknown): WorkspaceSavedView[] {
  if (!Array.isArray(value) || value.length === 0) {
    return DEFAULT_WORKSPACE_DASHBOARD.saved_views;
  }

  const views = value
    .map((item, index) => {
      if (!item || typeof item !== "object") return null;
      const row = item as Record<string, unknown>;
      const id = String(row.id ?? "").trim();
      const label = String(row.label ?? "").trim();
      if (!id || !label) return null;
      const view: WorkspaceSavedView = {
        id,
        label,
        order: Number(row.order ?? index + 1),
      };
      const status = String(row.status ?? "").trim();
      if (status && status !== "all") {
        view.status = status;
      }
      if (row.mine === true) {
        view.mine = true;
      }
      const periodDays = Number(row.period_days ?? 0);
      if (periodDays > 0) {
        view.period_days = periodDays;
      }
      return view;
    })
    .filter((item): item is WorkspaceSavedView => item !== null);

  return views.length > 0 ? views.sort((a, b) => a.order - b.order) : DEFAULT_WORKSPACE_DASHBOARD.saved_views;
}

export function mergeDashboardIntoWorkspaceMetadata(
  metadata: Record<string, unknown>,
  dashboard: FormWorkspaceDashboardSettings,
): Record<string, unknown> {
  const next = { ...metadata };
  const workspace =
    next.workspace && typeof next.workspace === "object"
      ? { ...(next.workspace as Record<string, unknown>) }
      : {};

  workspace.dashboard = {
    widgets: dashboard.widgets.map((widget) => ({ ...widget })),
    table_columns: dashboard.table_columns.map((column) => ({ ...column })),
    saved_views: dashboard.saved_views.map((view) => ({ ...view })),
  };

  next.workspace = workspace;
  return next;
}

export function visibleTableColumns(columns: WorkspaceTableColumn[]): WorkspaceTableColumn[] {
  return columns.filter((column) => column.visible).sort((a, b) => a.order - b.order);
}

export function enabledWidgets(widgets: WorkspaceDashboardWidget[]): WorkspaceDashboardWidget[] {
  return widgets.filter((widget) => widget.enabled).sort((a, b) => a.order - b.order);
}

export function periodStartIso(days: number): string {
  const date = new Date();
  date.setDate(date.getDate() - days);
  return date.toISOString().slice(0, 10);
}

export function resolveSavedViewFilters(view: WorkspaceSavedView): {
  status?: string;
  mine?: boolean;
  from?: string;
} {
  return {
    status: view.status,
    mine: view.mine,
    from: view.period_days ? periodStartIso(view.period_days) : undefined,
  };
}

export function availableFieldColumns(fields: EApprovalFormFieldInput[]): WorkspaceTableColumn[] {
  return exportableFields(fields).map((field, index) => {
    const name = field.name.trim();
    return {
      key: `field:${name}`,
      label: field.label?.trim() || name,
      kind: "field" as const,
      field_name: name,
      visible: false,
      order: 100 + index,
    };
  });
}
