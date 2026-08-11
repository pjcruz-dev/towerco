"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Bell, Check, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import { TenantNotificationRow } from "@/components/notifications/tenant-notification-row";
import { Button } from "@/components/ui/button";
import {
  useTenantNotificationUnreadCount,
  useTenantNotifications,
} from "@/hooks/use-tenant-notifications";
import {
  markAllTenantNotificationsRead,
  markTenantNotificationRead,
} from "@/lib/api/modules/tenant-notifications-api";
import type { TenantNotificationRow as TenantNotificationRowType } from "@/modules/notifications/types";
import {
  countUnreadByTab,
  filterNotificationsByTab,
  type NotificationTab,
} from "@/modules/notifications/display";
import { cn } from "@/lib/utils";

type Props = {
  enabled?: boolean;
};

const TABS: { id: NotificationTab; label: string }[] = [
  { id: "action", label: "Action required" },
  { id: "update", label: "Updates" },
  { id: "all", label: "All" },
];

export function TenantNotificationBell({ enabled = true }: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [tab, setTab] = useState<NotificationTab>("action");
  const queryClient = useQueryClient();
  const unreadQuery = useTenantNotificationUnreadCount(enabled);
  const listQuery = useTenantNotifications(enabled && open);
  const unreadCount = unreadQuery.data ?? 0;

  const notifications = listQuery.data?.data ?? [];

  const filtered = useMemo(
    () => filterNotificationsByTab(notifications, tab),
    [notifications, tab],
  );

  const tabUnreadCounts = useMemo(
    () => ({
      action: countUnreadByTab(notifications, "action"),
      update: countUnreadByTab(notifications, "update"),
      all: countUnreadByTab(notifications, "all"),
    }),
    [notifications],
  );

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const onPointerDown = (event: MouseEvent) => {
      if (containerRef.current?.contains(event.target as Node)) {
        return;
      }
      setOpen(false);
    };

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
      }
    };

    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);

    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  const invalidateNotifications = () => {
    queryClient.invalidateQueries({ queryKey: ["tenant", "notifications"] });
  };

  const markReadMutation = useMutation({
    mutationFn: markTenantNotificationRead,
    onSuccess: invalidateNotifications,
  });

  const markAllReadMutation = useMutation({
    mutationFn: () => markAllTenantNotificationsRead(tab === "all" ? undefined : { category: tab }),
    onSuccess: invalidateNotifications,
  });

  const handleNavigate = (notification: TenantNotificationRowType) => {
    if (!notification.is_read) {
      markReadMutation.mutate(notification.id);
    }
    setOpen(false);
  };

  return (
    <div ref={containerRef} className="relative">
      <Button
        type="button"
        variant="outline"
        size="icon-sm"
        className="relative"
        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ""}`}
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
      >
        <Bell className="h-4 w-4" />
        {unreadCount > 0 ? (
          <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium text-primary-foreground">
            {unreadCount > 99 ? "99+" : unreadCount}
          </span>
        ) : null}
      </Button>

      {open ? (
        <div className="absolute right-0 top-full z-50 mt-2 w-[min(26rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-border bg-card shadow-lg">
          <div className="flex items-start justify-between gap-3 border-b border-border px-4 py-3">
            <div>
              <p className="text-sm font-medium text-foreground">Notifications</p>
              <p className="text-xs text-muted-foreground">
                {unreadCount > 0 ? `${unreadCount} unread` : "All caught up"}
              </p>
            </div>
            <div className="flex items-center gap-1">
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="h-8 gap-1 px-2 text-xs"
                disabled={unreadCount === 0 || markAllReadMutation.isPending}
                onClick={() => markAllReadMutation.mutate()}
              >
                <Check className="h-3.5 w-3.5" />
                Mark all read
              </Button>
              <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                className="h-8 w-8"
                aria-label="Close notifications"
                onClick={() => setOpen(false)}
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          </div>

          <div className="flex items-center gap-1 border-b border-border px-3 py-2">
            <div className="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto">
              {TABS.map((item) => {
                const count = tabUnreadCounts[item.id];
                const active = tab === item.id;

                return (
                  <button
                    key={item.id}
                    type="button"
                    className={cn(
                      "inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors",
                      active
                        ? "bg-primary/10 text-primary"
                        : "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                    )}
                    onClick={() => setTab(item.id)}
                  >
                    {item.label}
                    {count > 0 ? (
                      <span
                        className={cn(
                          "inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-medium",
                          active
                            ? "bg-primary text-primary-foreground"
                            : "bg-muted text-muted-foreground",
                        )}
                      >
                        {count > 99 ? "99+" : count}
                      </span>
                    ) : null}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="max-h-[min(24rem,50vh)] overflow-y-auto">
            {listQuery.isLoading ? (
              <p className="px-4 py-6 text-sm text-muted-foreground">Loading notifications…</p>
            ) : filtered.length === 0 ? (
              <p className="px-4 py-6 text-sm text-muted-foreground">
                {tab === "action"
                  ? "No actions required right now."
                  : tab === "update"
                    ? "No recent updates."
                    : "No notifications yet."}
              </p>
            ) : (
              <ul className="divide-y divide-border">
                {filtered.map((notification) => (
                  <li key={notification.id}>
                    <TenantNotificationRow
                      notification={notification}
                      showModuleBadge
                      onNavigate={() => handleNavigate(notification)}
                    />
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      ) : null}
    </div>
  );
}

export function TenantNavBadge({ count }: { count: number }) {
  if (count <= 0) {
    return null;
  }

  return (
    <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-medium text-primary-foreground">
      {count > 99 ? "99+" : count}
    </span>
  );
}
