import { KpiStripSkeleton } from "@/components/ui/page-skeletons";
import { WidgetKpiTile } from "@/components/dashboard/widgets/widget-primitives";
import { DashboardWidgetEmpty } from "@/components/dashboard/dashboard-widget";
import type { ProjectOneKpi } from "@/modules/project-one/types";
import { cn } from "@/lib/utils";

type KpiStripItem =
  | (ProjectOneKpi & { href?: string | null })
  | (Omit<ProjectOneKpi, "key" | "value"> & { id?: string; value: string | number; href?: string | null });

function kpiKey(item: KpiStripItem): string {
  if ("key" in item && item.key) return item.key;
  if ("id" in item && item.id) return item.id;
  return item.label;
}

export function KpiStrip({
  items,
  isLoading = false,
  skeletonCount = 4,
  dataHelp,
  className,
}: {
  items: KpiStripItem[];
  isLoading?: boolean;
  skeletonCount?: number;
  /** Stable hook for live Help tours (`[data-help="…"]`). */
  dataHelp?: string;
  className?: string;
}) {
  if (isLoading) {
    return <KpiStripSkeleton count={skeletonCount} />;
  }

  if (items.length === 0) {
    return (
      <section data-help={dataHelp}>
        <DashboardWidgetEmpty message="KPI data will appear here once metrics are available." />
      </section>
    );
  }

  const cols =
    items.length >= 5
      ? "xl:grid-cols-5"
      : items.length === 4
        ? "xl:grid-cols-4"
        : items.length === 3
          ? "xl:grid-cols-3"
          : "xl:grid-cols-2";

  return (
    <section
      data-help={dataHelp}
      className={cn("grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2", cols, className)}
    >
      {items.map((item) => (
        <WidgetKpiTile
          key={kpiKey(item)}
          label={item.label}
          value={item.value}
          change={item.change}
          tone={item.tone ?? "neutral"}
          href={item.href}
        />
      ))}
    </section>
  );
}
