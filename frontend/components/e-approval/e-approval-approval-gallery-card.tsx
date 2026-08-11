"use client";

import Link from "next/link";
import { Inbox } from "lucide-react";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  row: EApprovalApprovalRow;
};

function submissionHref(row: EApprovalApprovalRow): string | null {
  const id = row.submission?.id;
  if (!id) {
    return null;
  }
  const pending = (row.approval_status ?? row.status) === "pending";
  return pending ? `/e-approval/submissions/${id}?tab=decide` : `/e-approval/submissions/${id}`;
}

export function EApprovalApprovalGalleryCard({ row }: Props) {
  const submission = row.submission;
  const href = submissionHref(row);

  const body = (
    <>
      <div className="flex flex-1 flex-col p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex min-w-0 items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Inbox className="h-5 w-5" aria-hidden />
            </div>
            <div className="min-w-0">
              <p className="font-mono text-sm font-medium text-foreground">
                {submission?.document_no ?? "—"}
              </p>
              <p className="mt-0.5 line-clamp-1 text-sm text-muted-foreground">
                {submission?.form_name ?? "Form"}
              </p>
            </div>
          </div>
          <EApprovalStatusBadge
            status={row.status}
            kind={row.status === "pending" ? "approval" : "submission"}
          />
        </div>
        {row.step_order != null ? (
          <p className="mt-3 text-xs text-muted-foreground">Your step: {row.step_order}</p>
        ) : null}
      </div>
      {href ? (
        <div className="border-t border-border bg-muted/20 px-4 py-3">
          <span className="text-sm font-medium text-primary">Open submission</span>
        </div>
      ) : null}
    </>
  );

  const className = cn(
    "flex h-full w-full flex-col rounded-xl border border-border bg-card text-left shadow-sm transition-colors",
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
