"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { EApprovalRoleFieldFrame } from "@/components/e-approval/e-approval-role-field-access";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { E_APPROVAL_LIST_FIELDS } from "@/lib/e-approval/submission-role-access";
import { createActionsColumn } from "@/components/ui/data-table-column-helpers";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { submissionOpenPath } from "@/modules/documents/controlled-document-submission-url";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";

function submissionHref(row: EApprovalApprovalRow): string | null {
  const id = row.submission?.id;
  if (!id) {
    return null;
  }
  const pending = (row.approval_status ?? row.status) === "pending";
  return submissionOpenPath(id, row.submission?.form_family, pending ? "decide" : null);
}

const ROLE_COLUMNS = E_APPROVAL_LIST_FIELDS.map((field) => ({ id: field.id, label: field.label }));

export function createEApprovalApprovalsTableColumns(): ColumnDef<EApprovalApprovalRow>[] {
  return [
    {
      id: "document",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Document" />,
      cell: ({ row }) => (
        <EApprovalRoleFieldFrame fieldName="document_no" fieldLabel="Document" columns={ROLE_COLUMNS}>
          <span className="font-mono text-xs">{row.original.submission?.document_no}</span>
        </EApprovalRoleFieldFrame>
      ),
    },
    {
      id: "form",
      header: "Form",
      cell: ({ row }) => (
        <EApprovalRoleFieldFrame fieldName="form_name" fieldLabel="Form" columns={ROLE_COLUMNS}>
          <span>{row.original.submission?.form_name}</span>
        </EApprovalRoleFieldFrame>
      ),
    },
    {
      id: "requestor",
      header: "Requestor",
      cell: ({ row }) => (
        <EApprovalRoleFieldFrame fieldName="requestor" fieldLabel="Requestor" columns={ROLE_COLUMNS}>
          <span>{row.original.submission?.requestor?.name ?? "—"}</span>
        </EApprovalRoleFieldFrame>
      ),
    },
    {
      id: "submitted",
      header: "Submitted",
      cell: ({ row }) => {
        const value = row.original.submission?.created_at;
        if (!value) return "—";
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { dateStyle: "medium" });
      },
    },
    {
      id: "status",
      accessorKey: "status",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => (
        <EApprovalRoleFieldFrame fieldName="status" fieldLabel="Status" columns={ROLE_COLUMNS}>
          <EApprovalStatusBadge
            status={row.original.submission?.status || row.original.status}
            label={row.original.submission?.status_label}
            kind="submission"
          />
        </EApprovalRoleFieldFrame>
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
