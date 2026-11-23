"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { EApprovalRoleFieldFrame } from "@/components/e-approval/e-approval-role-field-access";
import {
  createDateColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { EApprovalAuditRow } from "@/modules/e-approval/types";

export const eApprovalAuditTableColumns: ColumnDef<EApprovalAuditRow>[] = [
  createDateColumn("created_at", "When", (row) => row.created_at, {
    enableSorting: true,
  }),
  createTextColumn(
    "action",
    "Action",
    (row) => (
      <EApprovalRoleFieldFrame fieldName="status" fieldLabel="Audit action" menu="actions">
        <span className="font-mono text-xs">{row.action}</span>
      </EApprovalRoleFieldFrame>
    ),
    { className: "font-mono text-xs", enableSorting: true },
  ),
  createTextColumn("user", "User", (row) => row.user?.name ?? "—"),
  createTextColumn("target_id", "Target", (row) => row.target_id ?? "—", {
    className: "font-mono text-xs",
    enableSorting: true,
  }),
  createTextColumn("remarks", "Remarks", (row) => row.remarks ?? "—", {
    className: "max-w-xs truncate text-muted-foreground",
  }),
];
