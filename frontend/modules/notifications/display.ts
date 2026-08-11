import type { TenantNotificationRow } from "@/modules/notifications/types";

export type NotificationTab = "action" | "update" | "all";

export type NotificationModuleFilter = "all" | "e_approval" | "project_one";

const E_APPROVAL_ACTION_TYPES = new Set([
  "approval_assigned",
  "sla_reminder",
  "sla_escalation",
  "returned",
  "manual_follow_up",
  "awaiting_dcf",
]);

const PROJECT_ONE_ACTION_TYPES = new Set(["gate_submitted", "gate_escalated"]);

export function notificationModuleLabel(module: string): string {
  switch (module) {
    case "e_approval":
      return "E-Approval";
    case "project_one":
      return "PROJECT-ONE";
    default:
      return module;
  }
}

export function notificationCategory(notification: TenantNotificationRow): "action" | "update" {
  if (notification.category === "action" || notification.category === "update") {
    return notification.category;
  }

  if (notification.module === "project_one") {
    return PROJECT_ONE_ACTION_TYPES.has(notification.type) ? "action" : "update";
  }

  return E_APPROVAL_ACTION_TYPES.has(notification.type) ? "action" : "update";
}

export function resolveNotificationHref(notification: TenantNotificationRow): string {
  if (notification.href) {
    return notification.href;
  }

  if (notification.module === "project_one") {
    if (notification.type === "gate_submitted" || notification.type === "gate_escalated") {
      return "/project-one/gate-approvals?awaiting_me=1";
    }

    return "/project-one/gate-approvals";
  }

  if (
    notification.type === "approval_assigned" ||
    notification.type === "sla_reminder" ||
    notification.type === "sla_escalation"
  ) {
    return "/e-approval/approvals?awaiting_me=1";
  }

  const submissionId = notification.submission_id ?? (notification.subject_type === "submission" ? notification.subject_id : null);

  if (submissionId) {
    const tab =
      notification.type === "comment_added" || notification.type === "comment_replied"
        ? "comments"
        : "workflow";
    return `/e-approval/submissions/${submissionId}?tab=${tab}`;
  }

  return notification.module === "e_approval" ? "/e-approval" : "/dashboard";
}

export function notificationActorLabel(notification: TenantNotificationRow): string {
  const name = notification.actor_name?.trim();
  if (name) {
    return name;
  }

  return "System";
}

export function notificationActionLabel(type: string, module?: string): string {
  if (module === "project_one" || type.startsWith("gate_")) {
    switch (type) {
      case "gate_submitted":
        return "requested gate approval";
      case "gate_step_approved":
        return "advanced gate approval";
      case "gate_approved":
        return "approved a rollout gate";
      case "gate_rejected":
        return "rejected gate approval";
      case "gate_escalated":
        return "gate approval needs attention";
      default:
        return "updated rollout gate approval";
    }
  }

  switch (type) {
    case "approval_assigned":
      return "assigned you an approval";
    case "sla_reminder":
      return "SLA reminder — approval pending";
    case "sla_escalation":
      return "SLA escalation — approval pending";
    case "returned":
      return "returned your request for revision";
    case "manual_follow_up":
      return "sent a follow-up on your request";
    case "awaiting_dcf":
      return "document control is required";
    case "approved":
      return "approved your request";
    case "rejected":
      return "rejected your request";
    case "approval_rerouted":
      return "rerouted an approval step";
    case "comment_added":
      return "commented on a request";
    case "comment_replied":
      return "replied on a request";
    default:
      return "updated your request";
  }
}

export function notificationContextLine(notification: TenantNotificationRow): string | null {
  const parts: string[] = [];

  const formOrGate = notification.form_name ?? notification.context_secondary;
  const docOrRef = notification.document_no ?? notification.context_primary;

  if (formOrGate) {
    parts.push(formOrGate);
  }
  if (docOrRef) {
    parts.push(docOrRef);
  }

  return parts.length > 0 ? parts.join(" · ") : null;
}

export function initialsFromName(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

export function formatNotificationRelativeTime(value: string | null): string {
  if (!value) {
    return "";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }

  const diffMs = Date.now() - date.getTime();
  const diffMinutes = Math.floor(diffMs / 60_000);

  if (diffMinutes < 1) {
    return "Just now";
  }
  if (diffMinutes < 60) {
    return `${diffMinutes}m ago`;
  }

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) {
    return `${diffHours}h ago`;
  }

  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) {
    return `${diffDays}d ago`;
  }

  return date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

export function formatNotificationAbsoluteTime(value: string | null): string {
  if (!value) {
    return "";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }

  return date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

export function filterNotificationsByTab(
  notifications: TenantNotificationRow[],
  tab: NotificationTab,
): TenantNotificationRow[] {
  if (tab === "all") {
    return notifications;
  }

  return notifications.filter((row) => notificationCategory(row) === tab);
}

export function filterNotificationsByModule(
  notifications: TenantNotificationRow[],
  module: NotificationModuleFilter,
): TenantNotificationRow[] {
  if (module === "all") {
    return notifications;
  }

  return notifications.filter((row) => row.module === module);
}

export function countUnreadByTab(
  notifications: TenantNotificationRow[],
  tab: NotificationTab,
): number {
  return filterNotificationsByTab(notifications, tab).filter((row) => !row.is_read).length;
}
