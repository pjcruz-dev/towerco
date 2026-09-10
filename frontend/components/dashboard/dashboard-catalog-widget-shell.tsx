"use client";

import type { ReactNode } from "react";

import { DashboardWidget, DashboardWidgetEmpty } from "@/components/dashboard/dashboard-widget";
import { Button } from "@/components/ui/button";
import type { DashboardCatalogEntry } from "@/lib/ui/dashboard-widget-catalog";
import { cn } from "@/lib/utils";

type ShellProps = {
  entry: DashboardCatalogEntry;
  title?: string;
  children?: ReactNode;
  className?: string;
  action?: ReactNode;
  /** When true, show calm empty state until a renderer is wired */
  unbound?: boolean;
};

/**
 * Shared chrome for catalog widgets. Bound widgets pass children;
 * unbound picks show a quiet placeholder.
 */
export function DashboardCatalogWidgetShell({
  entry,
  title,
  children,
  className,
  action,
  unbound = false,
}: ShellProps) {
  return (
    <DashboardWidget
      title={title?.trim() || entry.label}
      description={entry.description}
      className={cn("h-full", className)}
      action={action}
    >
      {unbound ? (
        <DashboardWidgetEmpty message={`${entry.label} — connect module data or remove from the board.`} />
      ) : (
        children
      )}
    </DashboardWidget>
  );
}

export function DashboardCatalogUnboundNote({ onRemove }: { onRemove?: () => void }) {
  if (!onRemove) return null;
  return (
    <Button type="button" size="sm" variant="ghost" className="h-7 text-xs" onClick={onRemove}>
      Remove
    </Button>
  );
}
