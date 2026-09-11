"use client";

import type { DashboardCatalogEntry, DashboardWidgetKind } from "@/lib/ui/dashboard-widget-catalog";
import { cn } from "@/lib/utils";

/** Tiny abstract wireframe so operators see shape before adding a widget. */
export function DashboardWidgetMiniPreview({
  entry,
  className,
}: {
  entry: Pick<DashboardCatalogEntry, "id" | "kind">;
  className?: string;
}) {
  const kind = entry.kind;
  const id = entry.id;

  return (
    <div
      className={cn(
        "flex h-11 w-14 shrink-0 flex-col justify-center gap-0.5 overflow-hidden rounded-md border border-border bg-muted/40 p-1",
        className,
      )}
      aria-hidden
    >
      {previewFor(kind, id)}
    </div>
  );
}

function Bar({ className }: { className?: string }) {
  return <div className={cn("rounded-[2px] bg-foreground/20", className)} />;
}

function previewFor(kind: DashboardWidgetKind, id: string) {
  if (id === "page_shortcuts" || kind === "shortcuts") {
    return (
      <>
        <div className="flex gap-0.5">
          <Bar className="h-3 flex-1" />
          <Bar className="h-3 flex-1" />
          <Bar className="h-3 flex-1" />
        </div>
        <Bar className="h-1.5 w-full" />
      </>
    );
  }
  if (id === "page_kpi_strip" || kind === "kpi_metric_row" || kind === "kpi_hero_chart") {
    return (
      <div className="flex h-full items-stretch gap-0.5">
        <Bar className="flex-1" />
        <Bar className="flex-1" />
        <Bar className="flex-1" />
      </div>
    );
  }
  if (id === "page_attention" || kind === "page_attention" || kind === "alerts") {
    return (
      <>
        <Bar className="h-2 w-full bg-amber-500/40" />
        <Bar className="h-2 w-4/5 bg-amber-500/25" />
      </>
    );
  }
  if (id === "page_activity" || kind === "list_activity" || kind === "list_users" || kind === "list_leaderboard") {
    return (
      <>
        <Bar className="h-1.5 w-full" />
        <Bar className="h-1.5 w-[90%]" />
        <Bar className="h-1.5 w-[80%]" />
        <Bar className="h-1.5 w-[70%]" />
      </>
    );
  }
  if (id === "page_tip" || kind === "page_tip") {
    return (
      <>
        <Bar className="h-1.5 w-1/2" />
        <Bar className="h-4 w-full opacity-60" />
      </>
    );
  }
  if (id === "page_exports" || kind === "page_exports") {
    return (
      <div className="flex h-full items-center gap-1">
        <Bar className="h-5 w-5 rounded" />
        <div className="flex flex-1 flex-col gap-0.5">
          <Bar className="h-1.5 w-full" />
          <Bar className="h-1 w-2/3" />
        </div>
      </div>
    );
  }
  if (id === "hero_banner" || kind === "hero_banner") {
    return (
      <>
        <Bar className="h-2 w-3/4" />
        <Bar className="h-1.5 w-full opacity-50" />
        <Bar className="mt-0.5 h-2 w-1/3" />
      </>
    );
  }
  if (kind.startsWith("chart_") || kind === "bar_list" || kind === "funnel_stages") {
    return (
      <div className="flex h-full items-end gap-0.5 px-0.5">
        <Bar className="h-3 w-1.5" />
        <Bar className="h-4 w-1.5" />
        <Bar className="h-6 w-1.5" />
        <Bar className="h-4 w-1.5" />
        <Bar className="h-7 w-1.5" />
      </div>
    );
  }
  if (kind === "chart_donut" || kind === "kpi_gauge") {
    return (
      <div className="mx-auto size-7 rounded-full border-[3px] border-foreground/15 border-t-foreground/40" />
    );
  }
  if (kind === "table" || kind === "filters") {
    return (
      <>
        <Bar className="h-1.5 w-full" />
        <Bar className="h-1 w-full opacity-40" />
        <Bar className="h-1 w-full opacity-40" />
        <Bar className="h-1 w-full opacity-40" />
      </>
    );
  }
  return (
    <>
      <Bar className="h-2 w-2/3" />
      <Bar className="h-4 w-full opacity-50" />
    </>
  );
}
