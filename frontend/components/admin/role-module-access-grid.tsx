"use client";

import { Checkbox } from "@/components/ui/checkbox";
import {
  MODULE_ACCESS_ROWS,
  type DataAccessColumn,
} from "@/lib/rbac/role-management-layout";

const COLUMNS: Array<{ id: DataAccessColumn; label: string }> = [
  { id: "view", label: "View" },
  { id: "create", label: "Create" },
  { id: "edit", label: "Edit" },
  { id: "export", label: "Export" },
];

type Props = {
  enabledModules?: string[];
  granted: string[];
  filter: string;
  disabled?: boolean;
  onToggle: (permission: string) => void;
};

export function RoleModuleAccessGrid({
  enabledModules,
  granted,
  filter,
  disabled,
  onToggle,
}: Props) {
  const needle = filter.trim().toLowerCase();
  const rows = MODULE_ACCESS_ROWS.filter((row) => {
    if (enabledModules && !enabledModules.includes(row.module)) {
      return false;
    }
    if (!needle) return true;
    return row.label.toLowerCase().includes(needle);
  });

  return (
    <div className="space-y-3">
      <p className="text-xs text-muted-foreground">
        Module access for this workspace. Field-level security and workflow approvals appear when Dynamic Entities is enabled.
      </p>
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="min-w-full text-sm">
          <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground">
            <tr>
              <th className="px-3 py-2.5">Module</th>
              {COLUMNS.map((column) => (
                <th key={column.id} className="px-2 py-2.5 text-center">
                  {column.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-3 py-8 text-center text-sm text-muted-foreground">
                  {needle ? "No modules match this filter." : "No modules are enabled for data access."}
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id} className="border-t border-border/70">
                  <td className="px-3 py-2 font-medium text-foreground">{row.label}</td>
                  {COLUMNS.map((column) => {
                    const permission = row.columns[column.id];
                    if (!permission) {
                      return (
                        <td key={column.id} className="px-2 py-2 text-center text-muted-foreground">
                          —
                        </td>
                      );
                    }
                    return (
                      <td key={column.id} className="px-2 py-2 text-center">
                        <Checkbox
                          checked={granted.includes(permission)}
                          disabled={disabled}
                          aria-label={`${row.label} ${column.label}`}
                          onCheckedChange={() => onToggle(permission)}
                        />
                      </td>
                    );
                  })}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
