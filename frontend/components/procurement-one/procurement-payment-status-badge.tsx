"use client";

import { cn } from "@/lib/utils";

const STATUS_TONE: Record<string, string> = {
  draft: "bg-muted text-muted-foreground",
  pending_approval: "bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200",
  approved: "bg-blue-100 text-blue-900 dark:bg-blue-950/50 dark:text-blue-200",
  scheduled: "bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-200",
  paid: "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200",
  reconciled: "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200",
  cancelled: "bg-red-100 text-red-900 dark:bg-red-950/50 dark:text-red-200",
  exported: "bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200",
};

export function ProcurementPaymentStatusBadge({
  status,
  label,
  className,
}: {
  status: string;
  label?: string;
  className?: string;
}) {
  const tone = STATUS_TONE[status] ?? "bg-muted text-muted-foreground";

  return (
    <span className={cn("inline-flex rounded-md px-2 py-0.5 text-xs font-medium", tone, className)}>
      {label ?? status.replaceAll("_", " ")}
    </span>
  );
}
