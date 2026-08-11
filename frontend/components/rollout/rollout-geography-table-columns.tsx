"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { createActionsColumn, createTextColumn } from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { RolloutGeographyLookupRow } from "@/modules/rollout/types";

export function createRolloutGeographyTableColumns(options: {
  canConfigure: boolean;
  onEdit: (row: RolloutGeographyLookupRow) => void;
  onDelete: (id: string) => void;
  onToggleActive: (row: RolloutGeographyLookupRow) => void;
  pending: boolean;
}): ColumnDef<RolloutGeographyLookupRow>[] {
  const columns: ColumnDef<RolloutGeographyLookupRow>[] = [
    createTextColumn(
      "code",
      "Code",
      (row) => <span className="font-mono text-xs font-medium">{row.code}</span>,
    ),
    createTextColumn("label", "Label", (row) => row.label),
    createTextColumn("sort_order", "Sort", (row) => String(row.sort_order)),
    createTextColumn("is_active", "Status", (row) =>
      row.is_active ? (
        <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
          Active
        </span>
      ) : (
        <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
          Inactive
        </span>
      ),
    ),
  ];

  if (options.canConfigure) {
    columns.push(
      createActionsColumn("Actions", (row) => (
        <RowActionsMenu
          disabled={options.pending}
          items={[
            {
              key: "edit",
              label: "Edit",
              onSelect: () => options.onEdit(row.original),
            },
            {
              key: "toggle",
              label: row.original.is_active ? "Deactivate" : "Activate",
              onSelect: () => options.onToggleActive(row.original),
            },
            { type: "separator", key: "sep" },
            {
              key: "delete",
              label: "Delete",
              destructive: true,
              onSelect: () => options.onDelete(row.original.id),
            },
          ]}
        />
      )),
    );
  }

  return columns;
}
