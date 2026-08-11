"use client";

import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import {
  WORKSPACE_WIDGET_LABELS,
  availableFieldColumns,
  buildDefaultTableColumns,
  type FormWorkspaceDashboardSettings,
  type WorkspaceDashboardWidget,
  type WorkspaceSavedView,
  type WorkspaceTableColumn,
} from "@/modules/e-approval/form-workspace-dashboard-config";

type Props = {
  value: FormWorkspaceDashboardSettings;
  onChange: (next: FormWorkspaceDashboardSettings) => void;
  fields: EApprovalFormFieldInput[];
  disabled?: boolean;
};

function reorder<T extends { order: number }>(items: T[], index: number, direction: -1 | 1): T[] {
  const target = index + direction;
  if (target < 0 || target >= items.length) {
    return items;
  }
  const next = [...items];
  const [moved] = next.splice(index, 1);
  next.splice(target, 0, moved);
  return next.map((item, order) => ({ ...item, order: order + 1 }));
}

export function EApprovalFormWorkspaceDashboardCard({ value, onChange, fields, disabled }: Props) {
  const patch = (partial: Partial<FormWorkspaceDashboardSettings>) => {
    onChange({ ...value, ...partial });
  };

  const updateWidget = (index: number, partial: Partial<WorkspaceDashboardWidget>) => {
    const widgets = value.widgets.map((widget, idx) => (idx === index ? { ...widget, ...partial } : widget));
    patch({ widgets });
  };

  const updateColumn = (key: string, partial: Partial<WorkspaceTableColumn>) => {
    const table_columns = value.table_columns.map((column) =>
      column.key === key ? { ...column, ...partial } : column,
    );
    patch({ table_columns });
  };

  const addFieldColumn = (column: WorkspaceTableColumn) => {
    if (value.table_columns.some((item) => item.key === column.key)) {
      updateColumn(column.key, { visible: true });
      return;
    }
    patch({
      table_columns: [...value.table_columns, { ...column, visible: true, order: value.table_columns.length + 1 }],
    });
  };

  const addSavedView = () => {
    const id = `view_${Date.now()}`;
    patch({
      saved_views: [
        ...value.saved_views,
        { id, label: "Custom view", order: value.saved_views.length + 1 },
      ],
    });
  };

  const updateSavedView = (index: number, partial: Partial<WorkspaceSavedView>) => {
    const saved_views = value.saved_views.map((view, idx) => (idx === index ? { ...view, ...partial } : view));
    patch({ saved_views });
  };

  const removeSavedView = (index: number) => {
    patch({ saved_views: value.saved_views.filter((_, idx) => idx !== index) });
  };

  const fieldColumnOptions = availableFieldColumns(fields).filter(
    (column) => !value.table_columns.some((item) => item.key === column.key),
  );

  return (
    <EApprovalSectionCard
      title="Dashboard layout"
      description="Choose widgets, table columns, and saved filter views for the workspace page."
    >
      <div className="space-y-6">
        <div className="space-y-3">
          <div className="flex items-center justify-between gap-2">
            <Label>Widgets</Label>
          </div>
          <div className="space-y-2">
            {value.widgets.map((widget, index) => (
              <div key={widget.id} className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2">
                <div>
                  <p className="text-sm font-medium text-foreground">{WORKSPACE_WIDGET_LABELS[widget.type]}</p>
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-8 w-8"
                    disabled={disabled || index === 0}
                    onClick={() => patch({ widgets: reorder(value.widgets, index, -1) })}
                  >
                    <ArrowUp className="h-3.5 w-3.5" />
                  </Button>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-8 w-8"
                    disabled={disabled || index === value.widgets.length - 1}
                    onClick={() => patch({ widgets: reorder(value.widgets, index, 1) })}
                  >
                    <ArrowDown className="h-3.5 w-3.5" />
                  </Button>
                  <Switch
                    checked={widget.enabled}
                    disabled={disabled}
                    onCheckedChange={(checked) => updateWidget(index, { enabled: checked })}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <Label>Table columns</Label>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled}
              onClick={() => patch({ table_columns: buildDefaultTableColumns(fields) })}
            >
              Auto-add form fields
            </Button>
          </div>
          <div className="space-y-2">
            {value.table_columns.map((column) => (
              <label
                key={column.key}
                className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
              >
                <span className="text-sm text-foreground">
                  {column.label}
                  <span className="ml-2 text-xs text-muted-foreground">
                    {column.kind === "field" ? column.field_name : column.key}
                  </span>
                </span>
                <Switch
                  checked={column.visible}
                  disabled={disabled}
                  onCheckedChange={(checked) => updateColumn(column.key, { visible: checked })}
                />
              </label>
            ))}
          </div>
          {fieldColumnOptions.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {fieldColumnOptions.slice(0, 6).map((column) => (
                <Button
                  key={column.key}
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={disabled}
                  onClick={() => addFieldColumn(column)}
                >
                  <Plus className="h-3.5 w-3.5" />
                  {column.label}
                </Button>
              ))}
            </div>
          ) : null}
        </div>

        <div className="space-y-3">
          <div className="flex items-center justify-between gap-2">
            <Label>Saved views</Label>
            <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={addSavedView}>
              <Plus className="h-3.5 w-3.5" />
              Add view
            </Button>
          </div>
          <div className="space-y-2">
            {value.saved_views.map((view, index) => (
              <div key={view.id} className="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-[1fr_140px_120px_auto]">
                <Input
                  value={view.label}
                  disabled={disabled}
                  onChange={(e) => updateSavedView(index, { label: e.target.value })}
                  placeholder="View label"
                />
                <Input
                  value={view.status ?? ""}
                  disabled={disabled}
                  onChange={(e) => updateSavedView(index, { status: e.target.value || undefined })}
                  placeholder="Status filter"
                />
                <Input
                  type="number"
                  min={0}
                  value={view.period_days ?? ""}
                  disabled={disabled}
                  onChange={(e) =>
                    updateSavedView(index, {
                      period_days: e.target.value ? Number(e.target.value) : undefined,
                    })
                  }
                  placeholder="Days"
                />
                <div className="flex items-center gap-2">
                  <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Switch
                      checked={view.mine === true}
                      disabled={disabled}
                      onCheckedChange={(checked) => updateSavedView(index, { mine: checked || undefined })}
                    />
                    Mine
                  </label>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-8 w-8"
                    disabled={disabled || value.saved_views.length <= 1}
                    onClick={() => removeSavedView(index)}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </EApprovalSectionCard>
  );
}
