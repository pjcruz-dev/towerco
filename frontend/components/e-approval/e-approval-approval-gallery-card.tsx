"use client";

import Link from "next/link";
import { FileStack, User } from "lucide-react";

import { EApprovalRoleFieldFrame, useEApprovalSubmissionAccess } from "@/components/e-approval/e-approval-role-field-access";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { E_APPROVAL_LIST_FIELDS } from "@/lib/e-approval/submission-role-access";
import {
  EApprovalWorkflowStepShow,
  buildWorkflowStepShowItems,
  withPublicationTrainingStep,
} from "@/components/e-approval/e-approval-workflow-step-show";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  row: EApprovalApprovalRow;
};

function formatSubmittedAt(value: string | null | undefined): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { dateStyle: "medium" });
}

function submissionHref(row: EApprovalApprovalRow): string | null {
  const id = row.submission?.id;
  if (!id) {
    return null;
  }
  const pending = (row.approval_status ?? row.status) === "pending";
  return pending ? `/e-approval/submissions/${id}?tab=decide` : `/e-approval/submissions/${id}`;
}

const ROLE_COLUMNS = E_APPROVAL_LIST_FIELDS.map((field) => ({ id: field.id, label: field.label }));

export function EApprovalApprovalGalleryCard({ row }: Props) {
  const access = useEApprovalSubmissionAccess();
  const submission = row.submission;
  const href = access.allows("view") ? submissionHref(row) : null;
  const status = submission?.status || row.status;

  const body = (
    <>
      <div className="flex flex-1 flex-col p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex min-w-0 items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <FileStack className="h-5 w-5" aria-hidden />
            </div>
            <div className="min-w-0">
              {access.visible("document_no", "table") ? (
                <EApprovalRoleFieldFrame fieldName="document_no" fieldLabel="Document" columns={ROLE_COLUMNS}>
                  <p className="font-mono text-sm font-medium text-foreground group-hover:text-primary">
                    {submission?.document_no ?? "—"}
                  </p>
                </EApprovalRoleFieldFrame>
              ) : (
                <p className="font-mono text-sm font-medium text-foreground">Approval</p>
              )}
              {access.visible("form_name", "table") ? (
                <EApprovalRoleFieldFrame fieldName="form_name" fieldLabel="Form" columns={ROLE_COLUMNS}>
                  <p className="mt-0.5 line-clamp-1 text-sm text-muted-foreground">{submission?.form_name ?? "Form"}</p>
                </EApprovalRoleFieldFrame>
              ) : null}
              {submission?.subsidiary && access.visible("subsidiary", "table") ? (
                <EApprovalRoleFieldFrame fieldName="subsidiary" fieldLabel="Subsidiary" columns={ROLE_COLUMNS}>
                  <p className="mt-0.5 text-xs text-muted-foreground">{submission.subsidiary}</p>
                </EApprovalRoleFieldFrame>
              ) : null}
            </div>
          </div>
          {access.visible("status", "table") ? (
            <EApprovalRoleFieldFrame fieldName="status" fieldLabel="Status" columns={ROLE_COLUMNS}>
              <EApprovalStatusBadge
                status={status}
                label={submission?.status_label}
                kind="submission"
              />
            </EApprovalRoleFieldFrame>
          ) : null}
        </div>

        <div className="mt-4 space-y-2">
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {access.visible("requestor", "table") ? (
              <EApprovalRoleFieldFrame fieldName="requestor" fieldLabel="Requestor" columns={ROLE_COLUMNS}>
                <span className="inline-flex items-center gap-1">
                  <User className="h-3.5 w-3.5" aria-hidden />
                  {submission?.requestor?.name ?? "Unknown requestor"}
                </span>
              </EApprovalRoleFieldFrame>
            ) : null}
            <span>Submitted {formatSubmittedAt(submission?.created_at)}</span>
          </div>
          {row.step_order != null ? (
            <p className="text-xs text-muted-foreground">Your step: {row.step_order}</p>
          ) : null}
          <EApprovalWorkflowStepShow
            variant="compact"
            steps={withPublicationTrainingStep(
              buildWorkflowStepShowItems({
                currentStep: submission?.current_step ?? 0,
                stepCount: submission?.step_count,
                status,
                workflowSteps: submission?.workflow_steps,
              }),
              submission?.publication_training,
            )}
          />
        </div>
      </div>
      {href ? (
        <div className="border-t border-border bg-muted/20 px-4 py-2.5">
          <span className="text-sm font-medium text-primary">Open submission</span>
        </div>
      ) : null}
    </>
  );

  const className = cn(
    "group flex h-full w-full flex-col rounded-card border border-border bg-card text-left shadow-surface transition-colors",
    href && "hover:border-primary/30 hover:shadow-md",
  );

  if (href) {
    return (
      <Link
        href={href}
        className={className}
        aria-label={`Open submission ${submission?.document_no ?? ""}`.trim()}
      >
        {body}
      </Link>
    );
  }

  return <article className={className}>{body}</article>;
}
