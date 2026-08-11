import { cn } from "@/lib/utils";

export type StatusTone = "success" | "warning" | "danger" | "info" | "neutral";

const toneClasses: Record<StatusTone, string> = {
  success:
    "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400",
  warning:
    "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-400",
  danger:
    "border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400",
  info: "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-400",
  neutral: "border-border bg-muted text-muted-foreground",
};

export function statusToneClassName(tone: StatusTone, className?: string): string {
  return cn(
    "inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize",
    toneClasses[tone],
    className,
  );
}

/** Controlled document register status chips. */
export function controlledDocumentStatusTone(status: string): StatusTone {
  return status === "obsolete" ? "neutral" : "success";
}

/** Rollout lifecycle status chips. */
export function rolloutStatusTone(status: string): StatusTone {
  switch (status) {
    case "completed":
      return "success";
    case "permitting":
      return "warning";
    case "cancelled":
      return "danger";
    case "saq":
      return "info";
    default:
      return "neutral";
  }
}
