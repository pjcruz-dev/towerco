"use client";

import type { EApprovalAuditRow } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

function formatAt(iso: string | null): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" });
}

type Props = {
  row: EApprovalAuditRow;
};

export function EApprovalAuditGalleryCard({ row }: Props) {
  return (
    <article className={cn("rounded-xl border border-border bg-card p-4 shadow-sm")}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <p className="font-mono text-xs font-medium text-foreground">{row.action}</p>
        <time className="text-xs text-muted-foreground">{formatAt(row.created_at)}</time>
      </div>
      <dl className="mt-3 grid gap-2 text-sm">
        <div>
          <dt className="text-xs font-medium text-muted-foreground">User</dt>
          <dd className="mt-0.5">{row.user?.name ?? "—"}</dd>
        </div>
        <div>
          <dt className="text-xs font-medium text-muted-foreground">Target</dt>
          <dd className="mt-0.5 font-mono text-xs break-all">{row.target_id ?? "—"}</dd>
        </div>
        {row.remarks ? (
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Remarks</dt>
            <dd className="mt-0.5 text-muted-foreground">{row.remarks}</dd>
          </div>
        ) : null}
      </dl>
    </article>
  );
}
