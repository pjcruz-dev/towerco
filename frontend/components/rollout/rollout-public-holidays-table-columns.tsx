"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { createActionsColumn, createTextColumn } from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { TenantPublicHolidayRow } from "@/modules/rollout/types";

function regionBadge(region: string | null) {
  if (!region) {
    return (
      <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
        National
      </span>
    );
  }

  return (
    <span className="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium uppercase text-blue-800 dark:bg-blue-950 dark:text-blue-200">
      {region}
    </span>
  );
}

export function createRolloutPublicHolidaysTableColumns(options: {
  canConfigure: boolean;
  onEdit: (row: TenantPublicHolidayRow) => void;
  onDelete: (id: string) => void;
  deletePending: boolean;
}): ColumnDef<TenantPublicHolidayRow>[] {
  const columns: ColumnDef<TenantPublicHolidayRow>[] = [
    createTextColumn(
      "holiday_date",
      "Date",
      (row) => <span className="whitespace-nowrap font-mono text-xs">{row.holiday_date}</span>,
    ),
    createTextColumn("name", "Name", (row) => row.name),
    createTextColumn("region", "Territory", (row) => regionBadge(row.region)),
  ];

  if (options.canConfigure) {
    columns.push(
      createActionsColumn("Actions", (row) => (
        <RowActionsMenu
          disabled={options.deletePending}
          items={[
            {
              key: "edit",
              label: "Edit",
              onSelect: () => options.onEdit(row.original),
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
