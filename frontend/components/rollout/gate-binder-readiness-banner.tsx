"use client";

import Link from "next/link";

import type { DocumentBinderGateSummary } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  gate: DocumentBinderGateSummary | null | undefined;
  /** When true, final approve is blocked until binder is complete. */
  blocksApprove?: boolean;
  className?: string;
  compact?: boolean;
};

export function gateBinderBlocksFinalApprove(
  gate: DocumentBinderGateSummary | null | undefined,
  isFinalStep?: boolean,
): boolean {
  if (!isFinalStep || !gate?.applies) return false;
  if (!gate.site_linked) return true;
  return !gate.complete;
}

export function GateBinderReadinessBanner({
  gate,
  blocksApprove = false,
  className,
  compact = false,
}: Props) {
  if (!gate?.applies) return null;

  if (gate.complete && gate.site_linked) {
    if (compact) return null;
    const met = gate.summary?.met ?? gate.items?.filter((i) => i.met).length ?? 0;
    const required = gate.summary?.required ?? gate.items?.length ?? met;
    return (
      <p className={cn("text-[11px] text-emerald-700 dark:text-emerald-400", className)}>
        Site binder ready ({met}/{required} final docs)
      </p>
    );
  }

  const missing =
    gate.missing_labels.length > 0
      ? gate.missing_labels.join(", ")
      : !gate.site_linked
        ? "link rollout to a site binder"
        : "required folders";

  return (
    <div
      className={cn(
        "rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11px] leading-snug text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100",
        className,
      )}
    >
      <p className="font-medium">
        {blocksApprove ? "Complete site binder before final approve" : "Site binder incomplete"}
      </p>
      <p className="mt-0.5 text-amber-900/90 dark:text-amber-100/90">
        {!gate.site_linked
          ? "Link this rollout on Sites → Documents (Linked rollout), then upload final docs in the binder."
          : `Upload final documents in the site binder (not on the gate form) to: ${missing}. Lease package files can be imported; SAQ photos do not count.`}
      </p>
      {gate.checklist_href ? (
        <Link
          href={gate.checklist_href}
          className="mt-1 inline-flex font-medium text-amber-950 underline underline-offset-2 hover:no-underline dark:text-amber-50"
        >
          Open site binder
        </Link>
      ) : null}
    </div>
  );
}
