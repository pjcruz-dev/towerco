import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

export function PageHeaderSkeleton({ actionCount = 1 }: { actionCount?: number }) {
  return (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="space-y-2">
        <Skeleton className="h-8 w-56 max-w-full" />
        <Skeleton className="h-4 w-96 max-w-full" />
      </div>
      {actionCount > 0 ? (
        <div className="flex gap-2">
          {Array.from({ length: actionCount }).map((_, index) => (
            <Skeleton key={`header-action-${index}`} className="h-9 w-24" />
          ))}
        </div>
      ) : null}
    </header>
  );
}

export function KpiStripSkeleton({ count = 4, className }: { count?: number; className?: string }) {
  return (
    <section className={cn("grid gap-3 sm:grid-cols-2 xl:grid-cols-4", className)}>
      {Array.from({ length: count }).map((_, index) => (
        <article
          key={`kpi-skeleton-${index}`}
          className="rounded-xl border border-border bg-card p-4 shadow-sm"
        >
          <Skeleton className="h-3 w-20" />
          <Skeleton className="mt-3 h-8 w-16" />
          <Skeleton className="mt-2 h-3 w-28" />
        </article>
      ))}
    </section>
  );
}

export function MetricCardSkeleton({ className }: { className?: string }) {
  return (
    <div className={cn("rounded-xl border border-border bg-card p-5 shadow-sm", className)}>
      <Skeleton className="h-3 w-24" />
      <Skeleton className="mt-3 h-7 w-20" />
      <Skeleton className="mt-2 h-3 w-full max-w-[180px]" />
    </div>
  );
}

export function SectionCardSkeleton({
  fields = 4,
  className,
}: {
  fields?: number;
  className?: string;
}) {
  return (
    <section className={cn("overflow-hidden rounded-xl border border-border bg-card shadow-sm", className)}>
      <div className="space-y-2 border-b border-border px-4 py-3">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-3 w-64 max-w-full" />
      </div>
      <div className="grid gap-4 p-4 sm:grid-cols-2">
        {Array.from({ length: fields }).map((_, index) => (
          <div key={`section-field-${index}`} className="space-y-2">
            <Skeleton className="h-3 w-28" />
            <Skeleton className="h-10 w-full" />
          </div>
        ))}
      </div>
    </section>
  );
}

export function NavTilesSkeleton({ count = 2 }: { count?: number }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={`nav-tile-${index}`}
          className="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm"
        >
          <Skeleton className="h-9 w-9 shrink-0 rounded-lg" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-4 w-28" />
            <Skeleton className="h-3 w-40" />
          </div>
        </div>
      ))}
    </div>
  );
}

export function TableBlockSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
      <div className="space-y-2 border-b border-border px-4 py-3">
        <Skeleton className="h-5 w-36" />
        <Skeleton className="h-3 w-52" />
      </div>
      <div className="divide-y divide-border">
        {Array.from({ length: rows }).map((_, index) => (
          <div key={`table-row-${index}`} className="flex items-center justify-between gap-3 px-4 py-3">
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-4 w-20" />
          </div>
        ))}
      </div>
    </div>
  );
}

export function BillingPageSkeleton() {
  return (
    <div className="space-y-6">
      <NavTilesSkeleton count={2} />
      <KpiStripSkeleton count={4} />
      <div className="grid gap-4 lg:grid-cols-2">
        <SectionCardSkeleton fields={3} />
        <Skeleton className="h-40 rounded-xl" />
      </div>
      <SectionCardSkeleton fields={4} />
      <TableBlockSkeleton rows={4} />
    </div>
  );
}

export function SettingsPageSkeleton() {
  return (
    <div className="space-y-6">
      <NavTilesSkeleton count={2} />
      <SectionCardSkeleton fields={4} />
      <SectionCardSkeleton fields={3} />
      <Skeleton className="h-16 w-full rounded-xl" />
    </div>
  );
}

export function DashboardContentSkeleton() {
  return (
    <div className="space-y-6">
      <KpiStripSkeleton count={5} />
      <NavTilesSkeleton count={3} />
      <div className="grid gap-4 lg:grid-cols-2">
        <SectionCardSkeleton fields={3} />
        <TableBlockSkeleton rows={4} />
      </div>
    </div>
  );
}

export function PlatformBillingPageSkeleton() {
  return (
    <div className="flex flex-col gap-6">
      <KpiStripSkeleton count={4} />
      <div className="grid gap-4 lg:grid-cols-2">
        <TableBlockSkeleton rows={4} />
        <TableBlockSkeleton rows={4} />
      </div>
      <TableBlockSkeleton rows={6} />
      <SectionCardSkeleton fields={6} />
    </div>
  );
}

export function DashboardPageSkeleton() {
  return (
    <div className="space-y-6">
      <PageHeaderSkeleton actionCount={1} />
      <KpiStripSkeleton count={5} />
      <div className="grid gap-4 lg:grid-cols-2">
        <Skeleton className="h-56 rounded-xl" />
        <TableBlockSkeleton rows={5} />
      </div>
      <TableBlockSkeleton rows={4} />
    </div>
  );
}

export function PageLoadingShell({
  label = "Loading",
  className,
}: {
  label?: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex min-h-[40vh] flex-col items-center justify-center gap-3 text-sm text-muted-foreground",
        className,
      )}
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      <div className="flex gap-1.5">
        <Skeleton className="h-2 w-2 rounded-full" />
        <Skeleton className="h-2 w-2 rounded-full" />
        <Skeleton className="h-2 w-2 rounded-full" />
      </div>
      <span>{label}</span>
    </div>
  );
}
