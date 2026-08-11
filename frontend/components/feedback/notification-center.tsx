"use client";

import { useEffect } from "react";
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  useNotificationStore,
  type AppNotification,
} from "@/stores/notification-store";

const AUTO_DISMISS_MS = 5000;

const levelStyles: Record<
  AppNotification["level"],
  { container: string; icon: typeof AlertCircle }
> = {
  error: {
    container:
      "border-red-300 bg-red-50 text-red-950 shadow-lg dark:border-red-900/70 dark:bg-red-950 dark:text-red-50",
    icon: AlertCircle,
  },
  warning: {
    container:
      "border-amber-300 bg-amber-50 text-amber-950 shadow-lg dark:border-amber-900/70 dark:bg-amber-950 dark:text-amber-50",
    icon: AlertTriangle,
  },
  success: {
    container:
      "border-emerald-300 bg-emerald-50 text-emerald-950 shadow-lg dark:border-emerald-900/70 dark:bg-emerald-950 dark:text-emerald-50",
    icon: CheckCircle2,
  },
  info: {
    container:
      "border-sky-300 bg-sky-50 text-sky-950 shadow-lg dark:border-sky-900/70 dark:bg-sky-950 dark:text-sky-50",
    icon: Info,
  },
};

function NotificationToast({
  item,
  onDismiss,
}: {
  item: AppNotification;
  onDismiss: (id: string) => void;
}) {
  const config = levelStyles[item.level] ?? levelStyles.info;
  const Icon = config.icon;

  useEffect(() => {
    const timer = window.setTimeout(() => onDismiss(item.id), AUTO_DISMISS_MS);
    return () => window.clearTimeout(timer);
  }, [item.id, onDismiss]);

  return (
    <div
      className={`pointer-events-auto rounded-lg border p-3 ${config.container}`}
      role={item.level === "error" ? "alert" : "status"}
    >
      <div className="flex items-start gap-3">
        <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium">{item.title}</p>
          {item.message ? (
            <p className="mt-1 text-xs leading-relaxed opacity-90">{item.message}</p>
          ) : null}
        </div>
        <Button
          variant="ghost"
          size="icon-xs"
          className="shrink-0 opacity-70 hover:opacity-100"
          onClick={() => onDismiss(item.id)}
          aria-label="Dismiss notification"
        >
          <X className="size-3" />
        </Button>
      </div>
    </div>
  );
}

export function NotificationCenter() {
  const items = useNotificationStore((state) => state.items);
  const dismiss = useNotificationStore((state) => state.dismiss);

  if (items.length === 0) {
    return null;
  }

  return (
    <div
      className="pointer-events-none fixed inset-x-4 top-4 z-[100] flex flex-col gap-2 sm:inset-x-auto sm:right-4 sm:left-auto sm:w-96"
      aria-live="polite"
    >
      {items.map((item) => (
        <NotificationToast key={item.id} item={item} onDismiss={dismiss} />
      ))}
    </div>
  );
}
