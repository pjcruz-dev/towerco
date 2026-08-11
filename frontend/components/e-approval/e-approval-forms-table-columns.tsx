"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { EApprovalFormListActions } from "@/components/e-approval/e-approval-form-list-actions";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { createActionsColumn } from "@/components/ui/data-table-column-helpers";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import type { EApprovalFormListRow } from "@/modules/e-approval/types";

function formatCategory(category: string): string {
  const trimmed = category.trim();
  if (!trimmed) return "General";
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
}

export function createEApprovalFormsTableColumns(canManage: boolean): ColumnDef<EApprovalFormListRow>[] {
  return [
    {
      id: "name",
      accessorKey: "name",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
      cell: ({ row }) => (
        <div>
          <Link
            href={`/e-approval/forms/${row.original.id}`}
            className="font-medium text-foreground hover:text-primary"
          >
            {row.original.name}
          </Link>
          {row.original.description ? (
            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{row.original.description}</p>
          ) : null}
        </div>
      ),
    },
    {
      id: "status",
      accessorKey: "status",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => <EApprovalStatusBadge status={row.original.status} kind="form" />,
    },
    {
      id: "category",
      accessorKey: "category",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Category" />,
      cell: ({ row }) => formatCategory(row.original.category),
    },
    createActionsColumn("Actions", (row) => (
      <EApprovalFormListActions
        formId={row.original.id}
        formName={row.original.name}
        canManage={canManage}
      />
    )),
  ];
}
