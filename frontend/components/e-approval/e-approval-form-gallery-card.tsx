"use client";

import Link from "next/link";
import { FileText } from "lucide-react";

import { EApprovalFormListActions } from "@/components/e-approval/e-approval-form-list-actions";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import type { EApprovalFormListRow } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

function formatCategory(category: string): string {
  const trimmed = category.trim();
  if (!trimmed) return "General";
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
}

function formatUpdatedAt(value: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { dateStyle: "medium" });
}

type Props = {
  form: EApprovalFormListRow;
  canManage: boolean;
};

export function EApprovalFormGalleryCard({ form, canManage }: Props) {
  const href = `/e-approval/forms/${form.id}`;

  return (
    <article
      className={cn(
        "group flex h-full flex-col rounded-xl border border-border bg-card shadow-sm transition-colors",
        "hover:border-primary/30 hover:shadow-md",
      )}
    >
      <Link href={href} className="flex flex-1 flex-col p-4 outline-none focus-visible:ring-2 focus-visible:ring-ring">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <FileText className="h-5 w-5" aria-hidden />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="line-clamp-2 text-base font-medium leading-snug text-foreground group-hover:text-primary">
              {form.name}
            </h3>
            <p className="mt-1 text-xs text-muted-foreground">
              {formatCategory(form.category)} · Schema v{form.schema_version}
            </p>
          </div>
        </div>

        <p className="mt-3 line-clamp-2 min-h-[2.5rem] text-sm text-muted-foreground">
          {form.description?.trim() || "No description yet. Open the form to add fields and workflow steps."}
        </p>

        <div className="mt-4 flex items-center justify-between gap-2 border-t border-border pt-3">
          <EApprovalStatusBadge status={form.status} kind="form" />
          <span className="text-xs text-muted-foreground">Created {formatUpdatedAt(form.created_at)}</span>
        </div>
      </Link>

      <div className="border-t border-border bg-muted/20 px-4 py-3">
        <EApprovalFormListActions
          formId={form.id}
          formName={form.name}
          canManage={canManage}
          layout="stacked"
        />
      </div>
    </article>
  );
}
