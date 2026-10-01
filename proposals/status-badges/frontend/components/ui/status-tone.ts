/**
 * Single source for status colours. Implements docs/design-system/DESIGN_SYSTEM.md
 * section 17.1 (status colour map, light and dark) and 17.2 / 17.3 (dots, notices).
 *
 * Use it through <StatusBadge>, or spread `statusToneClasses[tone].dot|alert|icon`
 * into status dots, inline notices and icons. Do not write a new per-file colour map.
 */
export type StatusTone = "success" | "warning" | "danger" | "info" | "neutral";

export const statusToneClasses: Record<
  StatusTone,
  { badge: string; dot: string; alert: string; icon: string }
> = {
  success: {
    badge:
      "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400",
    dot: "bg-emerald-500",
    alert:
      "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200",
    icon: "text-emerald-600 dark:text-emerald-400",
  },
  warning: {
    badge:
      "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-400",
    dot: "bg-amber-500",
    alert:
      "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200",
    icon: "text-amber-600 dark:text-amber-400",
  },
  danger: {
    badge:
      "border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400",
    dot: "bg-red-500",
    alert:
      "border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200",
    icon: "text-red-600 dark:text-red-400",
  },
  info: {
    badge:
      "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-400",
    dot: "bg-sky-500",
    alert:
      "border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200",
    icon: "text-sky-600 dark:text-sky-400",
  },
  neutral: {
    badge: "border-border bg-muted text-muted-foreground",
    dot: "bg-muted-foreground/50",
    alert: "border-border bg-muted text-foreground",
    icon: "text-muted-foreground",
  },
};
