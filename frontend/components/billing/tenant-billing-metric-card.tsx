"use client";

import { cn } from "@/lib/utils";

type Props = {
  label: string;
  value: React.ReactNode;
  hint?: React.ReactNode;
  utilizationPercent?: number;
  tone?: "default" | "warning" | "danger";
  className?: string;
};

const barTone: Record<NonNullable<Props["tone"]>, string> = {
  default: "bg-primary",
  warning: "bg-amber-500",
  danger: "bg-red-500",
};

export function TenantBillingMetricCard({
  label,
  value,
  hint,
  utilizationPercent,
  tone = "default",
  className,
}: Props) {
  const showBar =
    utilizationPercent != null && Number.isFinite(utilizationPercent) && utilizationPercent >= 0;

  return (
    <article className={cn("rounded-xl border border-border bg-card p-5 shadow-sm", className)}>
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-2 text-xl font-semibold tabular-nums text-foreground">{value}</p>
      {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
      {showBar ? (
        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
          <div
            className={cn("h-full rounded-full transition-all", barTone[tone])}
            style={{ width: `${Math.min(100, utilizationPercent)}%` }}
          />
        </div>
      ) : null}
    </article>
  );
}
