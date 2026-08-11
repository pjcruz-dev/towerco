"use client";

import Link from "next/link";
import { FileStack, User } from "lucide-react";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
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
};

export function EApprovalSubmissionGalleryCard({ submission }: Props) {
  const href = `/e-approval/submissions/${submission.id}`;
  const currentUserId = useAuthStore((state) => state.user?.id ?? null);
  const continueHref = `/e-approval/request/${submission.form_id}`;
  const canContinueEditing =
    submission.status === "draft" && submission.requestor?.id != null && submission.requestor.id === currentUserId;

  return (
    <article
      className={cn(
        "group flex h-full flex-col rounded-xl border border-border bg-card shadow-sm transition-colors",
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
              <p className="font-mono text-sm font-medium text-foreground group-hover:text-primary">
                {submission.document_no}
              </p>
              <p className="mt-0.5 line-clamp-1 text-sm text-muted-foreground">{submission.form_name ?? "Form"}</p>
            </div>
          </div>
          <EApprovalStatusBadge status={submission.status} kind="submission" />
        </div>

        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
          <span className="inline-flex items-center gap-1">
            <User className="h-3.5 w-3.5" aria-hidden />
            {submission.requestor?.name ?? "Unknown requestor"}
          </span>
          <span>Step {submission.current_step}</span>
          <span>Submitted {formatSubmittedAt(submission.created_at)}</span>
        </div>
      </Link>

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border bg-muted/20 px-4 py-2.5">
        {canContinueEditing ? (
          <>
            <Link href={continueHref} className="text-sm font-medium text-primary hover:underline">
              Continue editing
            </Link>
            <Link href={href} className="text-sm text-muted-foreground hover:text-foreground hover:underline">
              View draft
            </Link>
          </>
        ) : (
          <Link href={href} className="text-sm font-medium text-primary hover:underline">
            Open submission
          </Link>
        )}
      </div>
    </article>
  );
}
