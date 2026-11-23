"use client";

import Link from "next/link";
import { FileStack, User } from "lucide-react";

import { EApprovalLiquidateLink } from "@/components/e-approval/e-approval-liquidate-link";
import { EApprovalRoleFieldFrame, useEApprovalSubmissionAccess } from "@/components/e-approval/e-approval-role-field-access";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import {
  EApprovalWorkflowStepShow,
  buildWorkflowStepShowItems,
  withPublicationTrainingStep,
} from "@/components/e-approval/e-approval-workflow-step-show";
import { cashAdvanceLiquidationHref } from "@/modules/e-approval/cash-advance-liquidation";
import { E_APPROVAL_LIST_FIELDS } from "@/lib/e-approval/submission-role-access";
import type { EApprovalSubmissionListRow } from "@/modules/e-approval/types";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

function formatSubmittedAt(value: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { dateStyle: "medium" });
}

type Props = {
  submission: EApprovalSubmissionListRow;
  /** Mark status badge for live Help tour (usually first visible card). */
  helpStatus?: boolean;
  /** Mark card actions for live Help tour. */
  helpActions?: boolean;
  /** Published liquidation form. A released cash advance offers a link into it. */
  liquidationFormId?: string | null;
};

export function EApprovalSubmissionGalleryCard({
  submission,
  helpStatus,
  helpActions,
  liquidationFormId,
}: Props) {
  const href = `/e-approval/submissions/${submission.id}`;
  const currentUserId = useAuthStore((state) => state.user?.id ?? null);
  const continueHref = `/e-approval/request/${submission.form_id}`;
  const canContinueEditing =
    submission.status === "draft" && submission.requestor?.id != null && submission.requestor.id === currentUserId;
  const liquidateHref = cashAdvanceLiquidationHref(submission, liquidationFormId);
  const access = useEApprovalSubmissionAccess();
  const columns = E_APPROVAL_LIST_FIELDS.map((field) => ({ id: field.id, label: field.label }));
  const showDocument = access.visible("document_no", "table");
  const showForm = access.visible("form_name", "table");
  const showSubsidiary = access.visible("subsidiary", "table");
  const showStatus = access.visible("status", "table");
  const showRequestor = access.visible("requestor", "table");
  const showStep = access.visible("current_step", "table");
  const showOpen = access.allows("view");

  return (
    <article
      className={cn(
        "group flex h-full flex-col rounded-card border border-border bg-card shadow-surface transition-colors",
        "hover:border-primary/30 hover:shadow-md",
        submission.status === "returned" && "border-amber-300/70 bg-amber-50/40 dark:border-amber-900/60 dark:bg-amber-950/20",
      )}
    >
      <Link href={href} className="flex flex-1 flex-col p-4 outline-none focus-visible:ring-2 focus-visible:ring-ring">
        <div className="flex items-start justify-between gap-3">
          <div className="flex min-w-0 items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <FileStack className="h-5 w-5" aria-hidden />
            </div>
            <div className="min-w-0">
              {showDocument ? (
                <EApprovalRoleFieldFrame fieldName="document_no" fieldLabel="Document" columns={columns}>
                  <p className="font-mono text-sm font-medium text-foreground group-hover:text-primary">
                    {submission.document_no}
                  </p>
                </EApprovalRoleFieldFrame>
              ) : null}
              {showForm ? (
                <EApprovalRoleFieldFrame fieldName="form_name" fieldLabel="Form" columns={columns}>
                  <p className="mt-0.5 line-clamp-1 text-sm text-muted-foreground">{submission.form_name ?? "Form"}</p>
                </EApprovalRoleFieldFrame>
              ) : null}
              {showSubsidiary && submission.subsidiary ? (
                <EApprovalRoleFieldFrame fieldName="subsidiary" fieldLabel="Subsidiary" columns={columns}>
                  <p className="mt-0.5 text-xs text-muted-foreground">{submission.subsidiary}</p>
                </EApprovalRoleFieldFrame>
              ) : null}
            </div>
          </div>
          {showStatus ? (
            <EApprovalRoleFieldFrame fieldName="status" fieldLabel="Status" columns={columns}>
              <span data-help={helpStatus ? "ea-submissions-status" : undefined}>
                <EApprovalStatusBadge status={submission.status} label={submission.status_label} kind="submission" />
              </span>
            </EApprovalRoleFieldFrame>
          ) : null}
        </div>

        <div className="mt-4 space-y-2">
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {showRequestor ? (
              <EApprovalRoleFieldFrame fieldName="requestor" fieldLabel="Requestor" columns={columns}>
                <span className="inline-flex items-center gap-1">
                  <User className="h-3.5 w-3.5" aria-hidden />
                  {submission.requestor?.name ?? "Unknown requestor"}
                </span>
              </EApprovalRoleFieldFrame>
            ) : null}
            <span>Submitted {formatSubmittedAt(submission.created_at)}</span>
          </div>
          {showStep ? (
            <EApprovalRoleFieldFrame fieldName="current_step" fieldLabel="Step" columns={columns}>
              <div data-help={helpStatus ? "ea-submissions-step-show" : undefined}>
                <EApprovalWorkflowStepShow
                  variant="compact"
                  steps={withPublicationTrainingStep(
                    buildWorkflowStepShowItems({
                      currentStep: submission.current_step,
                      stepCount: submission.step_count,
                      status: submission.status,
                      workflowSteps: submission.workflow_steps,
                    }),
                    submission.publication_training,
                  )}
                />
              </div>
            </EApprovalRoleFieldFrame>
          ) : null}
        </div>
      </Link>

      <div
        data-help={helpActions ? "ea-submissions-card-actions" : undefined}
        className="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-border bg-muted/20 px-4 py-2.5"
      >
        {canContinueEditing && access.allows("edit") ? (
          <>
            <Link href={continueHref} className="text-sm font-medium text-primary hover:underline">
              Continue editing
            </Link>
          <Link href={href} className="text-sm text-muted-foreground hover:text-foreground hover:underline">
            View draft
          </Link>
          </>
        ) : showOpen ? (
          <Link href={href} className="text-sm text-muted-foreground hover:text-foreground hover:underline">
            Open submission
          </Link>
        ) : null}
        {liquidateHref ? <EApprovalLiquidateLink href={liquidateHref} className="ml-auto" /> : null}
      </div>
    </article>
  );
}
