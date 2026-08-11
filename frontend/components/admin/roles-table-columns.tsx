"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { Badge } from "@/components/ui/badge";
import {
  createActionsColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import { permissionLabel } from "@/lib/rbac/permission-groups";

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

export function createRolesTableColumns(options: {
  onView: (role: AdminRoleRow) => void;
  onClone: (role: AdminRoleRow) => void;
  onEdit: (role: AdminRoleRow) => void;
  onDelete: (role: AdminRoleRow) => void;
  deletePending: boolean;
}): ColumnDef<AdminRoleRow>[] {
  return [
    createTextColumn(
      "name",
      "Role",
      (row) => (
        <button
          type="button"
          className="text-left font-medium capitalize text-foreground hover:text-primary"
          onClick={() => options.onView(row)}
        >
          {roleLabel(row.name)}
        </button>
      ),
      { enableSorting: true, sortValue: (row) => row.name },
    ),
    createTextColumn(
      "type",
      "Type",
      (row) => (
        <Badge variant={row.is_baseline ? "outline" : row.is_system ? "secondary" : "outline"}>
          {row.is_baseline ? "Core" : row.is_system ? "System" : "Custom"}
        </Badge>
      ),
      {
        enableSorting: true,
        sortValue: (row) => (row.is_baseline ? "core" : row.is_system ? "system" : "custom"),
      },
    ),
    createTextColumn(
      "user_count",
      "Users",
      (row) => <span className="text-sm text-muted-foreground">{row.user_count}</span>,
      { enableSorting: true, sortValue: (row) => row.user_count },
    ),
    createTextColumn("permissions", "Permissions", (row) => (
      <div className="flex flex-wrap gap-1">
        {row.permissions.slice(0, 4).map((permission) => (
          <Badge key={permission} variant="ghost" className="font-normal">
            {permissionLabel(permission)}
          </Badge>
        ))}
        {row.permissions.length > 4 ? (
          <Badge variant="ghost">+{row.permissions.length - 4}</Badge>
        ) : null}
      </div>
    )),
    createActionsColumn("Actions", (row) => {
      const role = row.original;
      const locked = role.is_baseline || role.is_system;

      return (
        <RowActionsMenu
          disabled={options.deletePending}
          items={[
            {
              key: "view",
              label: "View details",
              onSelect: () => options.onView(role),
            },
            {
              key: "clone",
              label: "Clone",
              onSelect: () => options.onClone(role),
            },
            {
              key: "edit",
              label: "Edit",
              hidden: locked,
              onSelect: () => options.onEdit(role),
            },
            { type: "separator", key: "sep", hidden: locked },
            {
              key: "delete",
              label: "Delete",
              hidden: locked,
              destructive: true,
              disabled: role.user_count > 0 || options.deletePending,
              onSelect: () => options.onDelete(role),
            },
          ]}
        />
      );
    }),
  ];
}
