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

export type EApprovalMasterDataItemRow = {
  id: string;
  code: string | null;
  label: string;
  is_active: boolean;
};

export const eApprovalMasterDataSetsTableColumns: ColumnDef<EApprovalMasterDataSetRow>[] = [
  createTextColumn("key", "Key", (row) => <span className="font-mono text-xs">{row.key}</span>),
  createTextColumn("name", "Name", (row) => row.name),
  createTextColumn("row_count", "Rows", (row) => row.row_count),
];

export function createEApprovalMasterDataRowsTableColumns(options: {
  onDelete: (id: string) => void;
}): ColumnDef<EApprovalMasterDataItemRow>[] {
  return [
    createTextColumn("label", "Label", (row) => row.label),
    createTextColumn(
      "code",
      "Code",
      (row) => <span className="font-mono text-xs">{row.code ?? "—"}</span>,
    ),
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
