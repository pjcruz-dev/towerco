"use client";

import { ArrowRight, Copy, ExternalLink, FileText, Link2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import type { EApprovalFormListRow } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

function formatCategory(category: string): string {
  const trimmed = category.trim();
  if (!trimmed) return "General";
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
}

type Props = {
  form: EApprovalFormListRow;
  onStart: () => void;
  onStartFocused?: () => void;
  onCopyExternalLink?: () => void;
  copyingExternalLink?: boolean;
};

export function EApprovalSubmissionFormPickerCard({
  form,
  onStart,
  onStartFocused,
  onCopyExternalLink,
  copyingExternalLink = false,
}: Props) {
  const canCopyExternal = Boolean(form.has_shareable_public_link && onCopyExternalLink);

  return (
    <article
      className={cn(
        "group flex h-full flex-col rounded-xl border border-border bg-card shadow-sm transition-all",
        "hover:border-primary/40 hover:shadow-md",
      )}
    >
      <button
        type="button"
        className="flex flex-1 flex-col p-4 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
        onClick={onStart}
      >
        <div className="flex items-start gap-3">
          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <FileText className="h-5 w-5" aria-hidden />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="line-clamp-2 text-base font-medium leading-snug text-foreground group-hover:text-primary">
              {form.name}
            </h3>
            <p className="mt-1 text-xs text-muted-foreground">
              {formatCategory(form.category)} · Schema v{form.schema_version}
              {canCopyExternal ? (
                <span className="ml-1.5 inline-flex items-center gap-0.5 text-sky-700 dark:text-sky-400">
                  <Link2 className="h-3 w-3" aria-hidden />
                  External link
                </span>
              ) : null}
            </p>
          </div>
        </div>

        <p className="mt-3 line-clamp-3 min-h-[3.75rem] text-sm text-muted-foreground">
          {form.description?.trim() || "Start a new approval request using this published form."}
        </p>
      </button>

      <div className="space-y-2 border-t border-border bg-muted/20 px-4 py-3">
        <Button type="button" className="w-full gap-1.5" onClick={onStart}>
          Start request
          <ArrowRight className="h-4 w-4" />
        </Button>
        {onStartFocused ? (
          <Button type="button" variant="outline" className="w-full gap-1.5 text-xs" onClick={onStartFocused}>
            Open focused view
            <ExternalLink className="h-3.5 w-3.5" />
          </Button>
        ) : null}
        {canCopyExternal ? (
          <Button
            type="button"
            variant="outline"
            className="w-full gap-1.5 text-xs"
            disabled={copyingExternalLink}
            onClick={onCopyExternalLink}
          >
            <Copy className="h-3.5 w-3.5" />
            {copyingExternalLink ? "Copying…" : "Copy external link"}
          </Button>
        ) : null}
      </div>
    </article>
  );
}
