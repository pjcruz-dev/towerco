"use client";

import { AlertCircle, AlertTriangle, CheckCircle2, Info, type LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

export type OperationalAlertLevel = "info" | "success" | "warning" | "error";

const levelConfig: Record<
  OperationalAlertLevel,
  { container: string; icon: LucideIcon }
> = {
  error: {
    container: "border-destructive/30 bg-destructive/5 text-foreground",
    icon: AlertCircle,
  },
  warning: {
    container: "border-amber-500/30 bg-amber-500/5 text-foreground dark:border-amber-900/40 dark:bg-amber-950/30",
    icon: AlertTriangle,
  },
  success: {
    container: "border-emerald-500/30 bg-emerald-500/5 text-foreground",
    icon: CheckCircle2,
  },
  info: {
    container: "border-primary/25 bg-primary/5 text-foreground",
    icon: Info,
  },
};

type Props = {
  level: OperationalAlertLevel;
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
  className?: string;
};

/** Inline operational alert — matches NotificationCenter tone, for forms and panels. */
export function OperationalAlert({ level, title, description, actions, className }: Props) {
  const config = levelConfig[level];
  const Icon = config.icon;

  return (
    <div
      className={cn("rounded-lg border px-3 py-2.5 text-sm", config.container, className)}
      role={level === "error" ? "alert" : "status"}
    >
      <div className="flex items-start gap-2.5">
        <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
        <div className="min-w-0 flex-1">
          <p className="font-medium leading-snug">{title}</p>
          {description ? <div className="mt-1 text-xs leading-relaxed text-muted-foreground">{description}</div> : null}
          {actions ? <div className="mt-2 flex flex-wrap gap-2">{actions}</div> : null}
        </div>
      </div>
    </div>
  );
}
