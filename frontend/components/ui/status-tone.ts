/**
 * Single source for status colours. Implements docs/design-system/DESIGN_SYSTEM.md
 * section 17.1 (status colour map, light and dark) and 17.2 / 17.3 (dots, notices).
 *
 * Use it through <StatusBadge>, or spread `statusToneClasses[tone].dot|alert|icon`
 * into status dots, inline notices and icons. Do not write a new per-file colour map.
 */
import { cn } from "@/lib/utils";

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

/**
 * Standalone status chip className (pill + tone), for `<span>`-style chips that
 * are not rendered through <StatusBadge>. Identical output to the previous
 * `lib/ui/status-tone` helper so existing chips are visually unchanged.
 */
export function statusToneClassName(tone: StatusTone, className?: string): string {
  return cn(
    "inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize",
    statusToneClasses[tone].badge,
    className,
  );
}

/** Ticketing SLA chip: on_track / at_risk / breached. */
export function ticketingSlaTone(status: string): StatusTone {
  switch (status) {
    case "on_track":
      return "success";
    case "at_risk":
      return "warning";
    default:
      return "danger";
  }
}

export function controlledDocumentStatusTone(status: string): StatusTone {
  if (status === "obsolete") {
    return "neutral";
  }
  if (status === "pending") {
    return "warning";
  }

  return "success";
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

export function subscriptionStatusTone(status: string): StatusTone {
  switch (status.toLowerCase()) {
    case "active":
      return "success";
    case "trial":
      return "info";
    case "past_due":
    case "grace":
      return "warning";
    case "canceled":
    case "cancelled":
    case "blocked":
    case "suspended":
      return "danger";
    default:
      return "neutral";
  }
}

export function accessModeTone(mode: string | null | undefined): StatusTone {
  switch (mode) {
    case "full":
    case null:
    case undefined:
      return "success";
    case "grace":
    case "read_only":
      return "warning";
    case "blocked":
      return "danger";
    default:
      return "neutral";
  }
}

export function planTierTone(tier: string | null | undefined): StatusTone {
  switch (tier) {
    case "professional":
    case "enterprise":
      return "info";
    default:
      return "neutral";
  }
}

export function environmentStatusTone(environment: string | null | undefined): StatusTone {
  switch ((environment ?? "production").toLowerCase()) {
    case "production":
      return "success";
    case "staging":
    case "local":
      return "info";
    case "test":
      return "warning";
    default:
      return "neutral";
  }
}

/** Category labels, not lifecycle status. Flattened onto the five approved tones. */
export function moduleStatusTone(module: string): StatusTone {
  const key = module.toLowerCase().replace(/[\s-]+/g, "_");
  switch (key) {
    case "e_approval":
    case "e_forms":
    case "eforms":
      return "success";
    case "ticketing":
    case "document_register":
    case "document_control":
    case "doc_extract":
    case "docextract":
      return "info";
    case "documents":
    case "asset_management":
    case "asset":
      return "warning";
    default:
      return "neutral";
  }
}

export function assetStatusTone(status: string): StatusTone {
  switch (status) {
    case "AVAILABLE":
    case "ACTIVE":
    case "SIGNED":
    case "COMPLETE":
      return "success";
    case "ASSIGNED":
      return "info";
    case "UNDER_REPAIR":
    case "REPLACEMENT_PENDING":
    case "LIFECYCLE_REVIEW":
    case "REPLACEMENT_PLANNING":
    case "PENDING_SIGNATURE":
    case "OPEN":
      return "warning";
    case "LOST":
    case "STOLEN":
    case "EOL":
    case "MISSING":
      return "danger";
    case "INCOMPLETE":
    case "DAMAGED":
    case "FOR_REPAIR":
      return "warning";
    case "REPAIRED":
    case "RETURNED":
      return "success";
    default:
      return "neutral";
  }
}
