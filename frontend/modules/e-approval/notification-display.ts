import type { EApprovalNotificationRow } from "@/modules/e-approval/types";

export type EApprovalNotificationTab = "action" | "update" | "all";

const ACTION_TYPES = new Set([
  "approval_assigned",
  "sla_reminder",
  "sla_escalation",
  "returned",
  "manual_follow_up",
  "awaiting_dcf",
]);

export function notificationCategory(
  notification: EApprovalNotificationRow,
): "action" | "update" {
  if (notification.category === "action" || notification.category === "update") {
    return notification.category;
  }

  return ACTION_TYPES.has(notification.type) ? "action" : "update";
}

export function resolveNotificationHref(notification: EApprovalNotificationRow): string {
  if (notification.href) {
    return notification.href;
  }

  if (
    notification.type === "approval_assigned" ||
    notification.type === "sla_reminder" ||
    notification.type === "sla_escalation"
  ) {
    return "/e-approval/approvals?awaiting_me=1";
  }

  if (notification.submission_id) {
    const tab =
      notification.type === "comment_added" || notification.type === "comment_replied"
        ? "comments"
        : "workflow";
    return `/e-approval/submissions/${notification.submission_id}?tab=${tab}`;
  }

  return "/e-approval";
}

export function notificationActorLabel(notification: EApprovalNotificationRow): string {
  const name = notification.actor_name?.trim();
  if (name) {
    return name;
  }

  return "System";
}

export function notificationActionLabel(type: string): string {
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

export function notificationContextLine(notification: EApprovalNotificationRow): string | null {
  const parts: string[] = [];
  if (notification.form_name) {
    parts.push(notification.form_name);
  }
  if (notification.document_no) {
    parts.push(notification.document_no);
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
  notifications: EApprovalNotificationRow[],
  tab: EApprovalNotificationTab,
): EApprovalNotificationRow[] {
  if (tab === "all") {
    return notifications;
  }

  return notifications.filter((row) => notificationCategory(row) === tab);
}

export function countUnreadByTab(
  notifications: EApprovalNotificationRow[],
  tab: EApprovalNotificationTab,
): number {
  return filterNotificationsByTab(notifications, tab).filter((row) => !row.is_read).length;
}
