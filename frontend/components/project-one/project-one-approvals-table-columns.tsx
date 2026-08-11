"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { Button } from "@/components/ui/button";
import {
  createActionsColumn,
  createDateColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { ProjectOneApprovalListRow } from "@/modules/project-one/types";

export function createProjectOneApprovalsTableColumns(options: {
  canResolve: boolean;
  onReview: (row: ProjectOneApprovalListRow) => void;
}): ColumnDef<ProjectOneApprovalListRow>[] {
  return [
    createTextColumn("status", "Status", (row) => (
      <span className="capitalize text-muted-foreground">{row.status}</span>
    ), { enableSorting: true }),
    createTextColumn("type", "Type", (row) => row.type, { enableSorting: true }),
    createTextColumn("title", "Title", (row) => (
      <span className="font-medium text-foreground">{row.title}</span>
    ), { enableSorting: true }),
    {
      id: "rollout",
      header: "Rollout",
      enableSorting: false,
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          {row.original.rollout?.rollout_ref ?? "—"}
        </span>
      ),
    },
    createTextColumn("requester", "Requester", (row) => row.requester, {
      className: "text-muted-foreground",
      enableSorting: true,
    }),
    createDateColumn("submittedAt", "Submitted", (row) => row.submittedAt, {
      enableSorting: true,
    }),
    createDateColumn("resolvedAt", "Resolved", (row) => row.resolvedAt, {
      enableSorting: true,
    }),
    createActionsColumn("Action", (row) =>
      row.original.status === "pending" && options.canResolve ? (
        <Button type="button" size="sm" variant="outline" onClick={() => options.onReview(row.original)}>
          Review
        </Button>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
    ),
  ];
}
