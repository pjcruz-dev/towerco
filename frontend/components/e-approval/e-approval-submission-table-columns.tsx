"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import type { ColumnDef } from "@tanstack/react-table";

import { EApprovalRoleFieldFrame } from "@/components/e-approval/e-approval-role-field-access";
import { EApprovalLiquidateLink } from "@/components/e-approval/e-approval-liquidate-link";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import {
  EApprovalWorkflowStepShow,
  buildWorkflowStepShowItems,
  withPublicationTrainingStep,
} from "@/components/e-approval/e-approval-workflow-step-show";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { E_APPROVAL_LIST_FIELDS } from "@/lib/e-approval/submission-role-access";
import type { EApprovalSubmissionListRow } from "@/modules/e-approval/types";
import { cashAdvanceLiquidationHref } from "@/modules/e-approval/cash-advance-liquidation";

const ROLE_COLUMNS = E_APPROVAL_LIST_FIELDS.map((field) => ({ id: field.id, label: field.label }));

function roleCell(id: string, label: string, node: ReactNode) {
  return (
    <EApprovalRoleFieldFrame fieldName={id} fieldLabel={label} columns={ROLE_COLUMNS}>
      {node}
    </EApprovalRoleFieldFrame>
  );
}

export function eApprovalSubmissionTableColumns(
  liquidationFormId?: string | null,
  options?: { showView?: boolean },
): ColumnDef<EApprovalSubmissionListRow>[] {
  const showView = options?.showView !== false;

  const text = (
    id: string,
    label: string,
    value: (row: EApprovalSubmissionListRow) => ReactNode,
    sortable = false,
  ): ColumnDef<EApprovalSubmissionListRow> => ({
    id,
    accessorFn: (row) => {
      const shown = value(row);
      return typeof shown === "string" || typeof shown === "number" ? shown : "";
    },
    header: ({ column }) =>
      roleCell(
        id,
        label,
        sortable ? <DataTableColumnHeader column={column} title={label} /> : <span className="font-medium">{label}</span>,
      ),
    cell: ({ row }) => roleCell(id, label, value(row.original)),
    enableSorting: sortable,
  });

  return [
    text(
      "document_no",
      "Document",
      (row) => (
        <Link className="font-mono text-xs hover:text-primary" href={`/e-approval/submissions/${row.id}`}>
          {row.document_no}
        </Link>
      ),
      true,
    ),
    text("form_name", "Form", (row) => row.form_name ?? "—"),
    text("subsidiary", "Subsidiary", (row) => row.subsidiary ?? "—"),
    text(
      "status",
      "Status",
      (row) => <EApprovalStatusBadge status={row.status} label={row.status_label} kind="submission" />,
      true,
    ),
    text("requestor", "Requestor", (row) => row.requestor?.name ?? "—"),
    text(
      "current_step",
      "Step",
      (row) => (
        <EApprovalWorkflowStepShow
          variant="compact"
          steps={withPublicationTrainingStep(
            buildWorkflowStepShowItems({
              currentStep: row.current_step,
              stepCount: row.step_count,
              status: row.status,
              workflowSteps: row.workflow_steps,
            }),
            row.publication_training,
          )}
          emptyLabel="—"
        />
      ),
      true,
    ),
    {
      id: "actions",
      header: () => (
        <EApprovalRoleFieldFrame fieldName="actions" fieldLabel="Actions" columns={ROLE_COLUMNS} menu="actions">
          <span className="font-medium">Open</span>
        </EApprovalRoleFieldFrame>
      ),
      cell: ({ row }) => {
        const liquidate = cashAdvanceLiquidationHref(row.original, liquidationFormId);
        return (
          <EApprovalRoleFieldFrame fieldName="actions" fieldLabel="Actions" columns={ROLE_COLUMNS} menu="actions">
            <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
              {liquidate ? <EApprovalLiquidateLink href={liquidate} /> : null}
              {showView ? (
                <Link
                  className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                  href={`/e-approval/submissions/${row.original.id}`}
                >
                  View
                </Link>
              ) : null}
            </span>
          </EApprovalRoleFieldFrame>
        );
      },
      enableSorting: false,
    },
  ];
}
