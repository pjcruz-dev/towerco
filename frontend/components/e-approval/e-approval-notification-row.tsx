"use client";

import Link from "next/link";

import type { EApprovalNotificationRow } from "@/modules/e-approval/types";
import {
  formatNotificationAbsoluteTime,
  formatNotificationRelativeTime,
  initialsFromName,
  notificationActionLabel,
  notificationActorLabel,
  notificationContextLine,
  resolveNotificationHref,
} from "@/modules/e-approval/notification-display";
import { cn } from "@/lib/utils";

type Props = {
  notification: EApprovalNotificationRow;
  onNavigate?: () => void;
  className?: string;
};

export function EApprovalNotificationRow({ notification, onNavigate, className }: Props) {
  const actor = notificationActorLabel(notification);
  const action = notificationActionLabel(notification.type);
  const context = notificationContextLine(notification);
  const preview = notification.body_preview?.trim();
  const href = resolveNotificationHref(notification);

  return (
    <Link
      href={href}
      className={cn(
        "block px-4 py-3 transition-colors hover:bg-muted/60",
        !notification.is_read && "bg-primary/5",
        className,
      )}
      onClick={onNavigate}
    >
      <div className="flex gap-3">
        <div
          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border bg-muted text-xs font-medium text-muted-foreground"
          aria-hidden
        >
          {initialsFromName(actor)}
        </div>

        <div className="min-w-0 flex-1">
          <p className="text-sm text-foreground">
            <span className="font-medium">{actor}</span>{" "}
            <span className="text-muted-foreground">{action}</span>
          </p>

          {context ? (
            <p className="mt-0.5 truncate text-xs text-muted-foreground">{context}</p>
          ) : null}

          {preview ? (
            <div className="mt-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-foreground">
              <p className="line-clamp-2">{preview}</p>
            </div>
          ) : null}

          {!preview && notification.message ? (
            <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">{notification.message}</p>
          ) : null}

          <div className="mt-2 flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
            <span>{formatNotificationRelativeTime(notification.created_at)}</span>
            <span>{formatNotificationAbsoluteTime(notification.created_at)}</span>
          </div>
        </div>

        {!notification.is_read ? (
          <span
            className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"
            aria-label="Unread"
          />
        ) : null}
      </div>
    </Link>
  );
}
