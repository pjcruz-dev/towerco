import { KpiStripSkeleton } from "@/components/ui/page-skeletons";
import type { ProjectOneKpi } from "@/modules/project-one/types";

type KpiStripItem = ProjectOneKpi | (Omit<ProjectOneKpi, "key" | "value"> & { id?: string; value: string | number });

function kpiKey(item: KpiStripItem): string {
  if ("key" in item && item.key) {
    return item.key;
  }
  if ("id" in item && item.id) {
    return item.id;
  }
  return item.label;
}

const toneClass: Record<NonNullable<ProjectOneKpi["tone"]>, string> = {
  neutral: "text-muted-foreground",
  success: "text-emerald-600 dark:text-emerald-400",
  warning: "text-amber-600 dark:text-amber-400",
  danger: "text-red-600 dark:text-red-400",
};

export function KpiStrip({
  items,
  isLoading = false,
  skeletonCount = 4,
  dataHelp,
}: {
  items: KpiStripItem[];
  isLoading?: boolean;
  skeletonCount?: number;
  /** Stable hook for live Help tours (`[data-help="…"]`). */
  dataHelp?: string;
}) {
  if (isLoading) {
    return <KpiStripSkeleton count={skeletonCount} />;
  }

  if (items.length === 0) {
    return (
      <section
        data-help={dataHelp}
        className="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground"
      >
        KPI data will appear here once the dashboard endpoint is connected.
      </section>
    );
  }

  return (
    <section data-help={dataHelp} className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      {items.map((item) => (
        <article key={kpiKey(item)} className="rounded-xl border bg-card p-4 shadow-sm">
          <p className="text-xs font-medium text-muted-foreground">
            {item.label}
          </p>
          <p className="mt-2 text-2xl font-semibold">{item.value}</p>
          {item.change ? (
            <p className={`mt-2 text-xs ${toneClass[item.tone ?? "neutral"]}`}>{item.change}</p>
          ) : null}
        </article>
      ))}
    </section>
  );
}
