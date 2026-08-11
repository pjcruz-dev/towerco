"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { Badge } from "@/components/ui/badge";
import {
  createActionsColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { PlatformOperatorRow } from "@/lib/api/modules/platform-api";
import { platformRoleLabel } from "@/lib/platform/platform-permissions";

export function createPlatformOperatorsTableColumns(options: {
  canManage: boolean;
  currentUserId: string | undefined;
  onEdit: (row: PlatformOperatorRow) => void;
  onDelete: (operatorId: string) => void;
  deletePending: boolean;
}): ColumnDef<PlatformOperatorRow>[] {
  const columns: ColumnDef<PlatformOperatorRow>[] = [
    createTextColumn("name", "Name", (row) => <span className="font-medium">{row.name}</span>, {
      className: "px-4",
      enableSorting: true,
      sortValue: (row) => row.name,
    }),
    createTextColumn(
      "email",
      "Email",
      (row) => <span className="text-muted-foreground">{row.email}</span>,
      { className: "px-4", enableSorting: true, sortValue: (row) => row.email },
    ),
    createTextColumn(
      "platform_role",
      "Role",
      (row) => <Badge variant="outline">{platformRoleLabel(row.platform_role)}</Badge>,
      {
        className: "px-4",
        enableSorting: true,
        sortValue: (row) => row.platform_role,
      },
    ),
  ];

  if (options.canManage) {
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
              key: "remove",
              label: "Remove",
              destructive: true,
              disabled: options.deletePending || row.original.id === options.currentUserId,
              onSelect: () => options.onDelete(row.original.id),
            },
          ]}
        />
      )),
    );
  }

  return columns;
}
