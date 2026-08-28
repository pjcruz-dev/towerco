"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { Badge } from "@/components/ui/badge";
import { AuditIpCompact } from "@/components/governance/audit-ip-location";
import {
  createDateColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { WorkspaceAuditRow } from "@/lib/api/modules/workspace-audit-api";
import { TENANT_MODULE_LABELS } from "@/lib/tenant/enabled-modules";
import {
  auditCategoryLabel,
  auditSeverityClassName,
  auditSeverityLabel,
} from "@/lib/workspace/audit-display";
import { cn } from "@/lib/utils";

function moduleLabel(module: string): string {
  return TENANT_MODULE_LABELS[module] ?? module.replace(/_/g, " ");
}

type Options = {
  onOpen: (row: WorkspaceAuditRow) => void;
};

export function createWorkspaceAuditTableColumns(
  options: Options,
): ColumnDef<WorkspaceAuditRow>[] {
  return [
    createDateColumn("created_at", "When", (row) => row.created_at, {
      className: "whitespace-nowrap text-xs text-muted-foreground",
      enableSorting: true,
    }),
    createTextColumn("module", "Module", (row) => moduleLabel(row.module), {
      enableSorting: true,
    }),
    createTextColumn(
      "category",
      "Category",
      (row) => (
        <Badge variant="outline" className="font-normal">
          {auditCategoryLabel(row.category)}
        </Badge>
      ),
      {
        enableSorting: true,
        sortValue: (row) => row.category ?? "",
      },
    ),
    createTextColumn(
      "severity",
      "Severity",
      (row) => (
        <Badge variant="outline" className={cn("font-normal", auditSeverityClassName(row.severity))}>
          {auditSeverityLabel(row.severity)}
        </Badge>
      ),
      {
        enableSorting: true,
        sortValue: (row) => row.severity ?? "",
      },
    ),
    createTextColumn(
      "action",
      "Action",
      (row) => (
        <button
          type="button"
          className="text-left text-sm font-medium text-foreground hover:underline"
          onClick={() => options.onOpen(row)}
        >
          {row.action_label || row.action}
        </button>
      ),
      {
        enableSorting: true,
        sortValue: (row) => row.action_label || row.action,
      },
    ),
    createTextColumn("summary", "Summary", (row) => (
      <div className="min-w-0 max-w-[280px]">
        <div className="truncate text-sm">{row.summary ?? "—"}</div>
        {row.reason ? <div className="mt-0.5 truncate text-xs text-muted-foreground">Reason: {row.reason}</div> : null}
        {row.changes && Object.keys(row.changes).length > 0 ? (
          <div className="mt-0.5 text-xs text-muted-foreground">
            {Object.keys(row.changes).length} field change
            {Object.keys(row.changes).length === 1 ? "" : "s"}
          </div>
        ) : null}
      </div>
    )),
    createTextColumn("actor", "Actor", (row) => (
      <div className="min-w-0">
        <div className="truncate text-sm">{row.actor?.name ?? "—"}</div>
        {row.actor?.email ? (
          <div className="truncate text-xs text-muted-foreground">{row.actor.email}</div>
        ) : null}
      </div>
    )),
    createTextColumn("ip_address", "IP", (row) => <AuditIpCompact ip={row.ip_address} />, {
      className: "whitespace-nowrap",
      sortValue: (row) => row.ip_address ?? "",
    }),
    createTextColumn(
      "entity",
      "Entity",
      (row) =>
        row.href ? (
          <Link href={row.href} className="text-primary hover:underline" onClick={(event) => event.stopPropagation()}>
            {row.entity_label ?? row.entity_id ?? "Open"}
          </Link>
        ) : (
          (row.entity_label ?? row.entity_id ?? "—")
        ),
    ),
  ];
}

/** @deprecated Prefer createWorkspaceAuditTableColumns for drawer support */
export const workspaceAuditTableColumns = createWorkspaceAuditTableColumns({
  onOpen: () => undefined,
});
