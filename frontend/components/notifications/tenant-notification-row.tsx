"use client";

import { Check } from "lucide-react";
import Link from "next/link";

import type { TenantNotificationRow } from "@/modules/notifications/types";
import {
  formatNotificationAbsoluteTime,
  formatNotificationRelativeTime,
  initialsFromName,
  notificationActionLabel,
  notificationActorLabel,
  notificationContextLine,
  notificationModuleLabel,
  resolveNotificationHref,
} from "@/modules/notifications/display";
import { cn } from "@/lib/utils";

type Props = {
  notification: TenantNotificationRow;
  onNavigate?: () => void;
  onMarkRead?: () => void;
  className?: string;
  showModuleBadge?: boolean;
};

export function TenantNotificationRow({
  notification,
  onNavigate,
  onMarkRead,
  className,
  showModuleBadge = false,
}: Props) {
  const actor = notificationActorLabel(notification);
  const action = notificationActionLabel(notification.type, notification.module);
  const context = notificationContextLine(notification);
  const preview = notification.body_preview?.trim();
  const href = resolveNotificationHref(notification);

  return (
    <div className={cn("group relative", className)}>
      <Link
        href={href}
        className={cn(
          "block px-4 py-3.5 pr-10 transition-colors hover:bg-muted/60",
          !notification.is_read && "bg-primary/5 hover:bg-primary/8",
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
            {showModuleBadge ? (
              <p className="mb-1 text-[11px] font-medium text-muted-foreground">
                {notificationModuleLabel(notification.module)}
              </p>
            ) : null}

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

            <div className="mt-2 flex items-center gap-2 text-[11px] text-muted-foreground">
              <span>{formatNotificationRelativeTime(notification.created_at)}</span>
              <span className="opacity-50">·</span>
              <span>{formatNotificationAbsoluteTime(notification.created_at)}</span>
            </div>
          </div>
        </div>
      </Link>

      {/* Unread dot / mark-read button */}
      <div className="absolute right-3 top-3.5 flex items-center">
        {!notification.is_read && onMarkRead ? (
          <button
            type="button"
            aria-label="Mark as read"
            title="Mark as read"
            className="flex h-6 w-6 items-center justify-center rounded-full opacity-0 transition-opacity hover:bg-muted group-hover:opacity-100"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              onMarkRead();
            }}
          >
            <Check className="h-3.5 w-3.5 text-muted-foreground" />
          </button>
        ) : !notification.is_read ? (
          <span className="h-2 w-2 rounded-full bg-primary" aria-label="Unread" />
        ) : null}
      </div>
    </div>
  );
}
