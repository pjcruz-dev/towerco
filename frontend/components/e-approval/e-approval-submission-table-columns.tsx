"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import {
  EApprovalWorkflowStepShow,
  buildWorkflowStepShowItems,
} from "@/components/e-approval/e-approval-workflow-step-show";
import {
  createActionsColumn,
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { EApprovalSubmissionListRow } from "@/modules/e-approval/types";

export const eApprovalSubmissionTableColumns: ColumnDef<EApprovalSubmissionListRow>[] = [
  createLinkColumn("document_no", "Document", {
    href: (row) => `/e-approval/submissions/${row.id}`,
    label: (row) => row.document_no,
    className: "font-mono text-xs hover:text-primary",
    enableSorting: true,
  }),
  createTextColumn("form_name", "Form", (row) => row.form_name ?? "—"),
  createTextColumn("subsidiary", "Subsidiary", (row) => row.subsidiary ?? "—"),
  createTextColumn(
    "status",
    "Status",
    (row) => <EApprovalStatusBadge status={row.status} kind="submission" />,
    { enableSorting: true },
  ),
  createTextColumn("requestor", "Requestor", (row) => row.requestor?.name ?? "—"),
  createTextColumn(
    "current_step",
    "Step",
    (row) => (
      <EApprovalWorkflowStepShow
        variant="compact"
        steps={buildWorkflowStepShowItems({
          currentStep: row.current_step,
          stepCount: row.step_count,
          status: row.status,
          workflowSteps: row.workflow_steps,
        })}
        emptyLabel="—"
      />
    ),
    { enableSorting: true },
  ),
  createActionsColumn("Open", (row) => (
    <Link
      className="text-sm font-medium text-primary hover:underline"
      href={`/e-approval/submissions/${row.original.id}`}
    >
      View
    </Link>
  )),
];
