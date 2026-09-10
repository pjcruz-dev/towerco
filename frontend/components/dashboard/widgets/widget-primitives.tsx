"use client";

import Link from "next/link";
import { ArrowRight } from "lucide-react";
import type { ReactNode } from "react";

import { DashboardWidgetEmpty } from "@/components/dashboard/dashboard-widget";
import { buttonVariants } from "@/components/ui/button";
import type { DashboardChartDatum } from "@/components/dashboard/dashboard-chart-utils";
import { cn } from "@/lib/utils";

const toneText: Record<"neutral" | "success" | "warning" | "danger", string> = {
  neutral: "text-muted-foreground",
  success: "text-emerald-600 dark:text-emerald-400",
  warning: "text-amber-600 dark:text-amber-400",
  danger: "text-red-600 dark:text-red-400",
};

const toneBar: Record<"neutral" | "success" | "warning" | "danger", string> = {
  neutral: "bg-sky-500",
  success: "bg-emerald-500",
  warning: "bg-amber-500",
  danger: "bg-red-500",
};

const toneRing: Record<"neutral" | "success" | "warning" | "danger", string> = {
  neutral: "#2563EB",
  success: "#16A34A",
  warning: "#D97706",
  danger: "#DC2626",
};

export function WidgetKpiTile({
  label,
  value,
  change,
  tone = "neutral",
  href,
  className,
}: {
  label: string;
  value: string | number;
  change?: string | null;
  tone?: "neutral" | "success" | "warning" | "danger";
  href?: string | null;
  className?: string;
}) {
  const body = (
    <>
      <p className="truncate text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </p>
      <p className="mt-2 text-2xl font-semibold tabular-nums tracking-tight text-foreground">{value}</p>
      {change ? <p className={cn("mt-1.5 truncate text-xs", toneText[tone])}>{change}</p> : null}
    </>
  );

  const shell = cn(
    "min-w-0 rounded-xl border border-border bg-card p-3.5 shadow-sm transition-colors",
    href && "hover:border-border hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40",
    className,
  );

  if (href) {
    return (
      <Link href={href} className={shell}>
        {body}
      </Link>
    );
  }

  return <article className={shell}>{body}</article>;
}

export function WidgetProgressRows({
  rows,
  empty = "No data yet.",
}: {
  rows: Array<{ key: string; label: string; value: number; tone?: keyof typeof toneBar }>;
  empty?: string;
}) {
  if (rows.length === 0) return <DashboardWidgetEmpty message={empty} />;
  const max = Math.max(...rows.map((row) => row.value), 1);

  return (
    <ul className="space-y-3">
      {rows.map((row) => {
        const pct = Math.round((row.value / max) * 100);
        return (
          <li key={row.key} className="space-y-1.5">
            <div className="flex items-baseline justify-between gap-3 text-sm">
              <span className="min-w-0 truncate font-medium text-foreground">{row.label}</span>
              <span className="shrink-0 tabular-nums text-muted-foreground">{row.value.toLocaleString()}</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
              <div
                className={cn("h-full rounded-full transition-[width] duration-300", toneBar[row.tone ?? "neutral"])}
                style={{ width: `${Math.max(pct, row.value > 0 ? 4 : 0)}%` }}
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}

export function WidgetActivityList({
  items,
  empty = "No recent activity.",
}: {
  items: Array<{
    id: string;
    title: string;
    subtitle?: string;
    at?: string | null;
    href?: string;
    tone?: "neutral" | "success" | "warning" | "danger";
  }>;
  empty?: string;
}) {
  if (items.length === 0) return <DashboardWidgetEmpty message={empty} />;

  return (
    <ul className="max-h-[16rem] divide-y divide-border overflow-auto rounded-lg border border-border">
      {items.map((item) => {
        const content = (
          <div className="flex gap-3 px-3 py-2.5">
            <span
              className={cn(
                "mt-1.5 size-1.5 shrink-0 rounded-full",
                item.tone === "success" && "bg-emerald-500",
                item.tone === "warning" && "bg-amber-500",
                item.tone === "danger" && "bg-red-500",
                (!item.tone || item.tone === "neutral") && "bg-sky-500",
              )}
              aria-hidden
            />
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium text-foreground">{item.title}</p>
              {item.subtitle ? (
                <p className="truncate text-xs text-muted-foreground">{item.subtitle}</p>
              ) : null}
              {item.at ? (
                <p className="mt-0.5 text-[11px] text-muted-foreground">
                  {new Date(item.at).toLocaleString()}
                </p>
              ) : null}
            </div>
          </div>
        );

        return (
          <li key={item.id} className="bg-card transition-colors hover:bg-muted/30">
            {item.href ? (
              <Link href={item.href} className="block">
                {content}
              </Link>
            ) : (
              content
            )}
          </li>
        );
      })}
    </ul>
  );
}

export function WidgetPeopleList({
  people,
  ranked,
  empty = "No people metrics yet.",
}: {
  people: Array<{ id: string; name: string; value: number; meta?: string }>;
  ranked?: boolean;
  empty?: string;
}) {
  if (people.length === 0) return <DashboardWidgetEmpty message={empty} />;
  const max = Math.max(...people.map((p) => p.value), 1);

  return (
    <ul className="space-y-3">
      {people.map((person, index) => (
        <li key={person.id} className="space-y-1.5">
          <div className="flex items-center justify-between gap-2 text-sm">
            <span className="flex min-w-0 items-center gap-2 font-medium text-foreground">
              {ranked ? (
                <span className="flex size-5 shrink-0 items-center justify-center rounded-md bg-muted text-[10px] tabular-nums text-muted-foreground">
                  {index + 1}
                </span>
              ) : null}
              <span className="truncate">{person.name}</span>
            </span>
            <span className="shrink-0 tabular-nums text-muted-foreground">{person.value}</span>
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-muted">
            <div
              className="h-full rounded-full bg-sky-500"
              style={{ width: `${Math.round((person.value / max) * 100)}%` }}
            />
          </div>
          {person.meta ? <p className="text-[11px] text-muted-foreground">{person.meta}</p> : null}
        </li>
      ))}
    </ul>
  );
}

export function WidgetPipelineStrip({ data }: { data: DashboardChartDatum[] }) {
  if (data.length === 0) return <DashboardWidgetEmpty message="No pipeline stages yet." />;
  const total = data.reduce((sum, row) => sum + row.value, 0) || 1;

  return (
    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
      {data.map((stage, index) => (
        <div
          key={stage.key}
          className={cn(
            "relative min-w-[6rem] flex-1 overflow-hidden rounded-lg border border-border bg-muted/20 px-3 py-2.5",
            index < data.length - 1 && "sm:mr-1",
          )}
        >
          <p className="truncate text-[11px] font-medium text-muted-foreground">{stage.label}</p>
          <p className="mt-1 text-lg font-semibold tabular-nums text-foreground">{stage.value}</p>
          <p className="text-[11px] tabular-nums text-muted-foreground">
            {Math.round((stage.value / total) * 100)}%
          </p>
        </div>
      ))}
    </div>
  );
}

export function WidgetShortcutGrid({
  items,
}: {
  items: Array<{ href: string; label: string; description?: string }>;
}) {
  if (items.length === 0) return <DashboardWidgetEmpty message="No shortcuts configured." />;

  return (
    <div className="grid gap-2 sm:grid-cols-2">
      {items.map((item) => (
        <Link
          key={item.href}
          href={item.href}
          className="group flex items-start gap-2.5 rounded-lg border border-border bg-card px-3 py-2.5 transition-colors hover:bg-muted/40"
        >
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium text-foreground">{item.label}</p>
            {item.description ? (
              <p className="mt-0.5 text-xs leading-snug text-muted-foreground">{item.description}</p>
            ) : null}
          </div>
          <ArrowRight className="mt-0.5 size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
        </Link>
      ))}
    </div>
  );
}

export function WidgetAttentionBanner({
  items,
  empty = "No attention items right now.",
}: {
  items: Array<{
    id: string;
    title: string;
    subtitle?: string;
    href?: string;
    tone?: "neutral" | "success" | "warning" | "danger";
  }>;
  empty?: string;
}) {
  if (items.length === 0) return <DashboardWidgetEmpty message={empty} />;

  const toneShell: Record<"neutral" | "success" | "warning" | "danger", string> = {
    neutral: "border-sky-200 bg-sky-50/80 text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100",
    success:
      "border-emerald-200 bg-emerald-50/80 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100",
    warning:
      "border-amber-200 bg-amber-50/80 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100",
    danger:
      "border-red-200 bg-red-50/80 text-red-950 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100",
  };

  return (
    <ul className="space-y-2">
      {items.map((item) => {
        const shell = cn(
          "flex w-full flex-col gap-0.5 rounded-xl border px-4 py-3 text-left text-sm shadow-sm",
          toneShell[item.tone ?? "warning"],
        );
        if (item.href) {
          return (
            <li key={item.id}>
              <Link href={item.href} className={cn(shell, "transition-opacity hover:opacity-90")}>
                <span className="font-medium">{item.title}</span>
                {item.subtitle ? <span className="text-xs opacity-80">{item.subtitle}</span> : null}
              </Link>
            </li>
          );
        }
        return (
          <li key={item.id} className={shell}>
            <span className="font-medium">{item.title}</span>
            {item.subtitle ? <span className="text-xs opacity-80">{item.subtitle}</span> : null}
          </li>
        );
      })}
    </ul>
  );
}

export function WidgetPageTip({
  title,
  body,
}: {
  title?: string;
  body?: string;
}) {
  const tipTitle = title?.trim() || "Tip";
  const tipBody = body?.trim();
  if (!tipBody) {
    return <DashboardWidgetEmpty message="Edit this tip in Layout & options." />;
  }

  return (
    <div className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-3">
      <p className="text-xs font-medium text-muted-foreground">{tipTitle}</p>
      <p className="mt-1 text-sm leading-relaxed text-foreground">{tipBody}</p>
    </div>
  );
}

export function WidgetExportsTeaser({
  href,
  label = "My exports",
  count,
  message,
}: {
  href?: string;
  label?: string;
  count?: number;
  message?: string;
}) {
  if (!href) {
    return <DashboardWidgetEmpty message="Exports link not configured for this page yet." />;
  }

  return (
    <Link
      href={href}
      className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors hover:bg-muted/40"
    >
      <div className="min-w-0">
        <p className="text-sm font-medium text-foreground">{label}</p>
        <p className="mt-0.5 text-xs text-muted-foreground">
          {message?.trim() ||
            (typeof count === "number"
              ? `${count} export${count === 1 ? "" : "s"} in recent history`
              : "Open download history and async jobs")}
        </p>
      </div>
      <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
    </Link>
  );
}

export function WidgetHeroBanner({
  title,
  description,
  ctaHref,
  ctaLabel,
}: {
  title: string;
  description?: string;
  ctaHref?: string;
  ctaLabel?: string;
}) {
  return (
    <div className="relative overflow-hidden rounded-xl border border-border bg-card px-5 py-6 shadow-sm">
      <div
        className="pointer-events-none absolute inset-y-0 right-0 w-1/3 bg-gradient-to-l from-sky-500/8 to-transparent"
        aria-hidden
      />
      <p className="text-xl font-semibold tracking-tight text-foreground md:text-2xl">{title}</p>
      {description ? (
        <p className="mt-1.5 max-w-2xl text-sm leading-relaxed text-muted-foreground">{description}</p>
      ) : null}
      {ctaHref && ctaLabel ? (
        <Link href={ctaHref} className={cn(buttonVariants({ size: "sm" }), "mt-4 inline-flex")}>
          {ctaLabel}
        </Link>
      ) : null}
    </div>
  );
}

export function WidgetGauge({
  label,
  value,
  max,
  tone = "neutral",
}: {
  label: string;
  value: number;
  max: number;
  tone?: keyof typeof toneRing;
}) {
  const pct = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;
  const color = toneRing[tone];

  return (
    <div className="flex flex-col items-center justify-center gap-3 py-2">
      <div
        className="relative flex size-28 items-center justify-center rounded-full"
        style={{
          background: `conic-gradient(${color} ${pct}%, var(--border) ${pct}%)`,
        }}
      >
        <div className="flex size-[4.75rem] flex-col items-center justify-center rounded-full bg-card text-center shadow-inner">
          <span className="text-xl font-semibold tabular-nums text-foreground">{pct}%</span>
        </div>
      </div>
      <div className="text-center">
        <p className="text-sm font-medium text-foreground">{label}</p>
        <p className="mt-0.5 text-xs tabular-nums text-muted-foreground">
          {value.toLocaleString()} / {max.toLocaleString()}
        </p>
      </div>
    </div>
  );
}

export function WidgetSparkBars({
  series,
  value,
  label,
}: {
  series: DashboardChartDatum[];
  value: string | number;
  label: string;
}) {
  const max = Math.max(...series.map((row) => row.value), 1);

  return (
    <div>
      <p className="text-2xl font-semibold tabular-nums tracking-tight text-foreground">{value}</p>
      <div className="mt-3 flex h-12 items-end gap-1">
        {series.map((row) => (
          <div
            key={row.key}
            className="flex-1 rounded-sm bg-sky-500/75 transition-[height]"
            style={{ height: `${Math.max(8, Math.round((row.value / max) * 100))}%` }}
            title={`${row.label}: ${row.value}`}
          />
        ))}
      </div>
      <p className="mt-2 text-xs text-muted-foreground">{label}</p>
    </div>
  );
}

export function WidgetSingleMetric({
  value,
  label,
  change,
  tone = "neutral",
}: {
  value: string | number;
  label: string;
  change?: string | null;
  tone?: keyof typeof toneText;
}) {
  return (
    <div className="flex flex-1 flex-col justify-center py-1">
      <p className="text-3xl font-semibold tabular-nums tracking-tight text-foreground">{value}</p>
      <p className="mt-1.5 text-sm text-muted-foreground">{label}</p>
      {change ? <p className={cn("mt-1 text-xs", toneText[tone])}>{change}</p> : null}
    </div>
  );
}

export function WidgetSectionHint({ children }: { children: ReactNode }) {
  return <p className="text-[11px] leading-snug text-muted-foreground">{children}</p>;
}
