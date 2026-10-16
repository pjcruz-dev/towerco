"use client";

import type { ColumnDef } from "@tanstack/react-table";

import {
  createActionsColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";

export type EApprovalMasterDataSetRow = {
  id: string;
  key: string;
  name: string;
  status: string;
  row_count: number;
};

export type EApprovalMasterDataColumn = {
  key: string;
  label: string;
  type: "text" | "number" | "boolean";
};

export type EApprovalMasterDataItemRow = {
  id: string;
  code: string | null;
  label: string;
  data?: Record<string, unknown> | null;
  is_active: boolean;
};

export function formatMasterDataCell(value: unknown, type: EApprovalMasterDataColumn["type"]): string {
  if (value === null || value === undefined || value === "") {
    return "—";
  }
  if (type === "boolean") {
    return value === true || value === "true" || value === 1 || value === "1" ? "Yes" : "No";
  }
  return String(value);
}

export const eApprovalMasterDataSetsTableColumns: ColumnDef<EApprovalMasterDataSetRow>[] = [
  createTextColumn("key", "Key", (row) => <span className="font-mono text-xs">{row.key}</span>),
  createTextColumn("name", "Name", (row) => row.name),
  createTextColumn("row_count", "Rows", (row) => row.row_count),
];

export function createEApprovalMasterDataRowsTableColumns(options: {
  columns?: EApprovalMasterDataColumn[];
  onDelete: (id: string) => void;
}): ColumnDef<EApprovalMasterDataItemRow>[] {
  const extra = (options.columns ?? []).map((column) =>
    createTextColumn(
      `data.${column.key}`,
      column.label,
      (row) => formatMasterDataCell(row.data?.[column.key], column.type),
      column.type === "number" ? { className: "text-right tabular-nums" } : undefined,
    ),
  );

  return [
    createTextColumn("label", "Label", (row) => row.label),
    createTextColumn(
      "code",
      "Code",
      (row) => <span className="font-mono text-xs">{row.code ?? "—"}</span>,
    ),
    ...extra,
    createActionsColumn("Actions", (row) => (
      <RowActionsMenu
        items={[
          {
            key: "delete",
            label: "Delete",
            destructive: true,
            onSelect: () => options.onDelete(row.original.id),
          },
        ]}
      />
    )),
  ];
}
