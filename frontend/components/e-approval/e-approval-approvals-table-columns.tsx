"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { createActionsColumn } from "@/components/ui/data-table-column-helpers";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";

function submissionHref(row: EApprovalApprovalRow): string | null {
  const id = row.submission?.id;
  if (!id) {
    return null;
  }
  const pending = (row.approval_status ?? row.status) === "pending";
  return pending ? `/e-approval/submissions/${id}?tab=decide` : `/e-approval/submissions/${id}`;
}

export function createEApprovalApprovalsTableColumns(): ColumnDef<EApprovalApprovalRow>[] {
  return [
    {
      id: "document",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Document" />,
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.original.submission?.document_no}</span>
      ),
    },
    {
      id: "form",
      header: "Form",
      cell: ({ row }) => row.original.submission?.form_name,
    },
    {
      id: "status",
      accessorKey: "status",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => (
        <EApprovalStatusBadge
          status={row.original.status}
          kind={row.original.status === "pending" ? "approval" : "submission"}
        />
      ),
    },
    createActionsColumn("Actions", (row) => {
      const href = submissionHref(row.original);
      if (!href) {
        return null;
      }

      return (
        <Link
          className="inline-flex h-8 items-center text-sm font-medium text-primary hover:underline"
          href={href}
        >
          Open submission
        </Link>
      );
    }),
  ];
}
