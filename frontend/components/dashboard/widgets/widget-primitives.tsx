"use client";

import Link from "next/link";
import {
  Activity,
  AlertCircle,
  ArrowRight,
  Bell,
  CheckCircle2,
  ClipboardList,
  Download,
  FileText,
  Folder,
  Layers,
  Lightbulb,
  ScanLine,
  Ticket,
  TrendingDown,
  TrendingUp,
  type LucideIcon,
} from "lucide-react";
import { useEffect, useId, useMemo, useState, type ReactNode } from "react";

import { DashboardWidgetEmpty } from "@/components/dashboard/dashboard-widget";
import { buttonVariants } from "@/components/ui/button";
import type { DashboardChartDatum } from "@/components/dashboard/dashboard-chart-utils";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import type {
  DashboardKpiAccent,
  DashboardKpiIconId,
  DashboardKpiLayout,
  DashboardKpiSparkStyle,
  DashboardKpiTone,
} from "@/lib/ui/dashboard-kpi-card-options";
import {
  accentToTone,
  resolveKpiLayout,
  resolveKpiVizStyle,
} from "@/lib/ui/dashboard-kpi-card-options";
import { cn } from "@/lib/utils";

type KpiTone = DashboardKpiTone;

const toneText: Record<KpiTone, string> = {
  neutral: "text-muted-foreground",
  success: "text-emerald-600 dark:text-emerald-400",
  warning: "text-amber-600 dark:text-amber-400",
  danger: "text-red-600 dark:text-red-400",
};

const toneBar: Record<KpiTone, string> = {
  neutral: "bg-sky-500",
  success: "bg-emerald-500",
  warning: "bg-amber-500",
  danger: "bg-red-500",
};

const toneRing: Record<KpiTone, string> = {
  neutral: "#2563EB",
  success: "#16A34A",
  warning: "#D97706",
  danger: "#DC2626",
};

const accentIconWrap: Record<Exclude<DashboardKpiAccent, "auto">, string> = {
  sky: "bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400",
  emerald: "bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400",
  amber: "bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400",
  rose: "bg-rose-500/10 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400",
  slate: "bg-slate-500/10 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300",
  indigo: "bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400",
};

const accentBadge: Record<Exclude<DashboardKpiAccent, "auto">, string> = {
  sky: "bg-sky-500/10 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300",
  emerald: "bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300",
  amber: "bg-amber-500/10 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300",
  rose: "bg-rose-500/10 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300",
  slate: "bg-slate-500/10 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300",
  indigo: "bg-indigo-500/10 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300",
};

const accentBarTop: Record<Exclude<DashboardKpiAccent, "auto">, string> = {
  sky: "from-sky-500 to-sky-400/40",
  emerald: "from-emerald-500 to-emerald-400/40",
  amber: "from-amber-500 to-amber-400/40",
  rose: "from-rose-500 to-rose-400/40",
  slate: "from-slate-500 to-slate-400/40",
  indigo: "from-indigo-500 to-indigo-400/40",
};

const accentSpark: Record<Exclude<DashboardKpiAccent, "auto">, string> = {
  sky: "bg-sky-500",
  emerald: "bg-emerald-500",
  amber: "bg-amber-500",
  rose: "bg-rose-500",
  slate: "bg-slate-500",
  indigo: "bg-indigo-500",
};

const accentTint: Record<Exclude<DashboardKpiAccent, "auto">, string> = {
  sky: "bg-sky-500/[0.04]",
  emerald: "bg-emerald-500/[0.04]",
  amber: "bg-amber-500/[0.04]",
  rose: "bg-rose-500/[0.04]",
  slate: "bg-slate-500/[0.04]",
  indigo: "bg-indigo-500/[0.04]",
};

const toneIconWrap: Record<KpiTone, string> = {
  neutral: accentIconWrap.sky,
  success: accentIconWrap.emerald,
  warning: accentIconWrap.amber,
  danger: accentIconWrap.rose,
};

const toneBadge: Record<KpiTone, string> = {
  neutral: accentBadge.sky,
  success: accentBadge.emerald,
  warning: accentBadge.amber,
  danger: accentBadge.rose,
};

const toneAccent: Record<KpiTone, string> = {
  neutral: accentBarTop.sky,
  success: accentBarTop.emerald,
  warning: accentBarTop.amber,
  danger: accentBarTop.rose,
};

const toneSpark: Record<KpiTone, string> = {
  neutral: accentSpark.sky,
  success: accentSpark.emerald,
  warning: accentSpark.amber,
  danger: accentSpark.rose,
};

const toneTint: Record<KpiTone, string> = {
  neutral: accentTint.sky,
  success: accentTint.emerald,
  warning: accentTint.amber,
  danger: accentTint.rose,
};

const accentSparkStroke: Record<Exclude<DashboardKpiAccent, "auto">, string> = {
  sky: "stroke-sky-500",
  emerald: "stroke-emerald-500",
  amber: "stroke-amber-500",
  rose: "stroke-rose-500",
  slate: "stroke-slate-500",
  indigo: "stroke-indigo-500",
};

const toneSparkStroke: Record<KpiTone, string> = {
  neutral: accentSparkStroke.sky,
  success: accentSparkStroke.emerald,
  warning: accentSparkStroke.amber,
  danger: accentSparkStroke.rose,
};

const ICON_BY_ID: Record<Exclude<DashboardKpiIconId, "auto">, LucideIcon> = {
  layers: Layers,
  scan: ScanLine,
  check: CheckCircle2,
  alert: AlertCircle,
  ticket: Ticket,
  clipboard: ClipboardList,
  activity: Activity,
  file: FileText,
  folder: Folder,
};

function kpiIconFor(
  metricKey: string | undefined,
  tone: KpiTone,
  iconId?: DashboardKpiIconId,
): LucideIcon {
  if (iconId && iconId !== "auto") return ICON_BY_ID[iconId];
  const key = (metricKey ?? "").toLowerCase();
  if (/(fail|error|danger|reject)/.test(key)) return AlertCircle;
  if (/(ready|success|complete|done|approved)/.test(key)) return CheckCircle2;
  if (/(scan|process|pending|queue|progress)/.test(key)) return ScanLine;
  if (/(ticket|open)/.test(key)) return Ticket;
  if (/(batch|total|all)/.test(key)) return Layers;
  if (tone === "danger") return AlertCircle;
  if (tone === "success") return CheckCircle2;
  if (tone === "warning") return ScanLine;
  return ClipboardList;
}

function resolveAccentClasses(
  accent: DashboardKpiAccent | undefined,
  tone: KpiTone,
): {
  iconWrap: string;
  badge: string;
  barTop: string;
  spark: string;
  stroke: string;
  tint: string;
} {
  if (accent && accent !== "auto") {
    return {
      iconWrap: accentIconWrap[accent],
      badge: accentBadge[accent],
      barTop: accentBarTop[accent],
      spark: accentSpark[accent],
      stroke: accentSparkStroke[accent],
      tint: accentTint[accent],
    };
  }
  return {
    iconWrap: toneIconWrap[tone],
    badge: toneBadge[tone],
    barTop: toneAccent[tone],
    spark: toneSpark[tone],
    stroke: toneSparkStroke[tone],
    tint: toneTint[tone],
  };
}

function parseNumericValue(value: string | number): number | null {
  if (typeof value === "number" && Number.isFinite(value)) return value;
  const cleaned = String(value).replace(/,/g, "").trim();
  if (!/^-?\d+(\.\d+)?$/.test(cleaned)) return null;
  const n = Number(cleaned);
  return Number.isFinite(n) ? n : null;
}

function formatKpiDisplay(value: string | number, animated: number | null): string {
  if (animated !== null) {
    return Number.isInteger(animated)
      ? animated.toLocaleString()
      : animated.toLocaleString(undefined, { maximumFractionDigits: 1 });
  }
  if (typeof value === "number" && Number.isFinite(value)) return value.toLocaleString();
  return String(value);
}

function changeTone(change: string, fallback: KpiTone): KpiTone {
  const trimmed = change.trim();
  if (trimmed.startsWith("+") || /up|increase|improved/i.test(trimmed)) return "success";
  if (trimmed.startsWith("-") || /down|decrease|drop/i.test(trimmed)) return "danger";
  return fallback;
}

/** Deterministic mini-series so tiles feel charted without inventing API history. */
function sparkHeights(seed: string, value: number | null, count = 8): number[] {
  let h = 0;
  for (let i = 0; i < seed.length; i += 1) h = (h * 31 + seed.charCodeAt(i)) >>> 0;
  const magnitude = value !== null ? Math.abs(value) : 1;
  const base = magnitude === 0 ? 18 : Math.min(88, 32 + Math.log10(magnitude + 1) * 24);
  return Array.from({ length: count }, (_, i) => {
    h = (h * 1664525 + 1013904223) >>> 0;
    const wobble = (h % 37) - 18;
    return Math.max(10, Math.min(100, Math.round(base + wobble + (i % 3) * 5)));
  });
}

function usePrefersReducedMotion(): boolean {
  const [reduced, setReduced] = useState(false);
  useEffect(() => {
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    const sync = () => setReduced(mq.matches);
    sync();
    mq.addEventListener("change", sync);
    return () => mq.removeEventListener("change", sync);
  }, []);
  return reduced;
}

function useCountUp(target: number | null, enabled: boolean): number | null {
  const [current, setCurrent] = useState(target);
  useEffect(() => {
    if (target === null || !enabled) {
      setCurrent(target);
      return;
    }
    let frame = 0;
    const from = 0;
    const duration = 520;
    const start = performance.now();
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - (1 - t) ** 3;
      const next = from + (target - from) * eased;
      setCurrent(Number.isInteger(target) ? Math.round(next) : Math.round(next * 10) / 10);
      if (t < 1) frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [target, enabled]);
  return current;
}

function KpiVizBars({
  heights,
  sparkClass,
  animate,
  thick,
  fill,
  metricLabel,
  metricValue,
}: {
  heights: number[];
  sparkClass: string;
  animate: boolean;
  thick?: boolean;
  fill?: boolean;
  metricLabel?: string;
  metricValue?: string | number | null;
}) {
  const [mounted, setMounted] = useState(!animate);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  return (
    <div
      className={cn(
        "flex w-full items-end",
        fill ? "h-9" : thick ? "h-9" : "h-8",
        thick ? "gap-1" : "gap-0.5",
      )}
    >
      {heights.map((height, index) => {
        const tip = [
          metricLabel,
          metricValue != null && metricValue !== "" ? `Value ${metricValue}` : null,
          `Point ${index + 1}`,
          `${Math.round(height)}%`,
        ]
          .filter(Boolean)
          .join(" · ");
        return (
          <Tooltip key={`spark-${index}`}>
            <TooltipTrigger
              delay={0}
              render={
                <div className="relative flex h-full min-w-0 flex-1 cursor-default items-end justify-center outline-none" />
              }
            >
              {!thick ? (
                <div className="absolute inset-x-[18%] bottom-0 top-0 rounded-full bg-muted/70" />
              ) : (
                <div className="absolute inset-x-0 bottom-0 top-0 rounded-md bg-muted/50" />
              )}
              <div
                className={cn(
                  "relative z-[1] transition-[height,opacity] duration-500 ease-out",
                  thick ? "w-full rounded-md" : "w-[72%] rounded-full",
                  sparkClass,
                  mounted ? "opacity-90" : "opacity-40",
                  thick && index === heights.length - 2 && "opacity-100 brightness-110",
                )}
                style={{
                  height: mounted ? `${height}%` : "12%",
                  transitionDelay: animate ? `${index * 40}ms` : "0ms",
                }}
              />
            </TooltipTrigger>
            <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
              {tip}
            </TooltipContent>
          </Tooltip>
        );
      })}
    </div>
  );
}

function KpiVizArea({
  heights,
  stroke,
  animate,
  gradientId,
  fill,
  metricLabel,
  metricValue,
}: {
  heights: number[];
  stroke: string;
  animate: boolean;
  gradientId: string;
  fill?: boolean;
  metricLabel?: string;
  metricValue?: string | number | null;
}) {
  const [mounted, setMounted] = useState(!animate);
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const coords = heights.map((h, i) => {
    const x = heights.length <= 1 ? 50 : (i / (heights.length - 1)) * 100;
    const y = 100 - (mounted ? h : h * 0.15);
    return { x, y, h };
  });
  const line = coords.map((p) => `${p.x},${p.y}`).join(" ");
  const area = `0,100 ${line} 100,100`;

  return (
    <div className="relative h-9 w-full" onMouseLeave={() => setHoverIndex(null)}>
      <svg
        viewBox="0 0 100 100"
        preserveAspectRatio="none"
        className={cn("pointer-events-none h-full w-full", stroke)}
        aria-hidden
      >
        <defs>
          <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="currentColor" stopOpacity="0.38" />
            <stop offset="100%" stopColor="currentColor" stopOpacity="0.02" />
          </linearGradient>
        </defs>
        <polygon
          points={area}
          fill={`url(#${gradientId})`}
          className="transition-opacity duration-500"
          style={{ opacity: mounted ? 1 : 0.3 }}
        />
        <polyline
          points={line}
          fill="none"
          stroke="currentColor"
          strokeWidth="2.25"
          vectorEffect="non-scaling-stroke"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="transition-opacity duration-500"
          style={{ opacity: mounted ? 1 : 0.35 }}
        />
        {hoverIndex !== null && coords[hoverIndex] ? (
          <circle
            cx={coords[hoverIndex].x}
            cy={coords[hoverIndex].y}
            r={2.75}
            className="fill-current"
          />
        ) : null}
      </svg>
      {/* HTML hit strips + portal tooltips — avoids clipped overlay inside overflow-hidden */}
      <div className="absolute inset-0 flex">
        {coords.map((point, index) => {
          const tip = [
            metricLabel,
            metricValue != null && metricValue !== "" ? `Value ${metricValue}` : null,
            `Point ${index + 1}`,
            `${Math.round(point.h)}%`,
          ]
            .filter(Boolean)
            .join(" · ");
          return (
            <Tooltip key={`area-hit-${index}`}>
              <TooltipTrigger
                delay={0}
                render={
                  <button
                    type="button"
                    className="h-full min-w-0 flex-1 cursor-default outline-none"
                    aria-label={tip}
                    onMouseEnter={() => setHoverIndex(index)}
                    onFocus={() => setHoverIndex(index)}
                  />
                }
              />
              <TooltipContent side="top" className="max-w-[16rem] px-2 py-1 text-[11px] tabular-nums">
                {tip}
              </TooltipContent>
            </Tooltip>
          );
        })}
      </div>
    </div>
  );
}

function KpiVizGauge({
  value,
  strokeClass,
  animate,
  fill,
  metricLabel,
}: {
  value: number | null;
  strokeClass: string;
  animate: boolean;
  fill?: boolean;
  metricLabel?: string;
}) {
  const [mounted, setMounted] = useState(!animate);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const pct = Math.max(
    0,
    Math.min(
      100,
      value === null ? 0 : value <= 100 ? value : Math.min(100, 40 + Math.log10(value + 1) * 18),
    ),
  );
  const shown = mounted ? pct : 0;
  const length = 126;
  const dash = (shown / 100) * length;
  const tip = [metricLabel, value != null ? `Value ${value}` : null, `${Math.round(shown)}%`]
    .filter(Boolean)
    .join(" · ");

  return (
    <Tooltip>
      <TooltipTrigger
        delay={0}
        render={
          <div
            className={cn(
              // Fixed aspect — never stretch with Full-width cards (w-full would flatten the arc)
              "relative flex h-10 w-[5.25rem] max-w-full shrink-0 cursor-default items-end justify-center outline-none",
            )}
          />
        }
      >
        <svg viewBox="0 0 100 62" className="h-full w-full" preserveAspectRatio="xMidYMid meet">
          <path
            d="M 10 54 A 40 40 0 0 1 90 54"
            fill="none"
            strokeWidth="9"
            strokeLinecap="round"
            className="stroke-muted"
          />
          <path
            d="M 10 54 A 40 40 0 0 1 90 54"
            fill="none"
            strokeWidth="9"
            strokeLinecap="round"
            className={cn(strokeClass, "transition-[stroke-dasharray] duration-700 ease-out")}
            strokeDasharray={`${dash} ${length}`}
          />
        </svg>
        <span className="absolute bottom-0.5 text-xs font-semibold tabular-nums text-foreground">
          {Math.round(shown)}%
        </span>
      </TooltipTrigger>
      <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
        {tip}
      </TooltipContent>
    </Tooltip>
  );
}

function KpiVizProgress({
  value,
  sparkClass,
  animate,
  fill,
  metricLabel,
}: {
  value: number | null;
  sparkClass: string;
  animate: boolean;
  fill?: boolean;
  metricLabel?: string;
}) {
  const [mounted, setMounted] = useState(!animate);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const pct =
    value === null
      ? 35
      : value <= 0
        ? 6
        : value <= 100
          ? Math.max(8, value)
          : Math.min(96, 28 + Math.log10(value + 1) * 22);
  const tip = [metricLabel, value != null ? `Value ${value}` : null, `${Math.round(pct)}%`]
    .filter(Boolean)
    .join(" · ");

  return (
    <Tooltip>
      <TooltipTrigger
        delay={0}
        render={
          <div className="flex w-full cursor-default flex-col justify-end gap-1 outline-none" />
        }
      >
        <div className="h-1.5 overflow-hidden rounded-full bg-muted">
          <div
            className={cn("h-full rounded-full transition-[width] duration-700 ease-out", sparkClass)}
            style={{ width: mounted ? `${pct}%` : "8%" }}
          />
        </div>
        <div className="flex justify-between text-[10px] tabular-nums text-muted-foreground">
          <span>Progress</span>
          <span>{Math.round(pct)}%</span>
        </div>
      </TooltipTrigger>
      <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
        {tip}
      </TooltipContent>
    </Tooltip>
  );
}

function KpiVizSplit({
  value,
  sparkClass,
  secondaryClass,
  animate,
  fill,
  metricLabel,
}: {
  value: number | null;
  sparkClass: string;
  secondaryClass: string;
  animate: boolean;
  fill?: boolean;
  metricLabel?: string;
}) {
  const [mounted, setMounted] = useState(!animate);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const primary =
    value === null
      ? 62
      : value <= 0
        ? 12
        : value <= 100
          ? Math.max(14, value)
          : Math.min(92, 30 + Math.log10(value + 1) * 20);
  const secondary = Math.max(10, Math.min(88, 100 - primary + 8));

  return (
    <div className="flex w-full flex-col justify-end gap-1.5">
      <div className="flex items-center justify-between gap-2 text-[10px] text-muted-foreground">
        <span>Primary</span>
        <span className="rounded-full bg-muted px-1.5 py-0.5 text-[9px] font-medium">vs</span>
        <span>Baseline</span>
      </div>
      <Tooltip>
        <TooltipTrigger
          delay={0}
          render={<div className="h-1.5 cursor-default overflow-hidden rounded-full bg-muted outline-none" />}
        >
          <div
            className={cn("h-full rounded-full transition-[width] duration-700 ease-out", sparkClass)}
            style={{ width: mounted ? `${primary}%` : "10%" }}
          />
        </TooltipTrigger>
        <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
          {[metricLabel, "Primary", value != null ? `Value ${value}` : null, `${Math.round(primary)}%`]
            .filter(Boolean)
            .join(" · ")}
        </TooltipContent>
      </Tooltip>
      <Tooltip>
        <TooltipTrigger
          delay={0}
          render={<div className="h-1.5 cursor-default overflow-hidden rounded-full bg-muted outline-none" />}
        >
          <div
            className={cn("h-full rounded-full transition-[width] duration-700 ease-out", secondaryClass)}
            style={{ width: mounted ? `${secondary}%` : "10%", transitionDelay: "80ms" }}
          />
        </TooltipTrigger>
        <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
          {[metricLabel, "Baseline", `${Math.round(secondary)}%`].filter(Boolean).join(" · ")}
        </TooltipContent>
      </Tooltip>
    </div>
  );
}

function KpiVizLine({
  heights,
  stroke,
  animate,
  metricLabel,
  metricValue,
}: {
  heights: number[];
  stroke: string;
  animate: boolean;
  metricLabel?: string;
  metricValue?: string | number | null;
}) {
  const [mounted, setMounted] = useState(!animate);
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const coords = heights.map((h, i) => {
    const x = heights.length <= 1 ? 50 : (i / (heights.length - 1)) * 100;
    const y = 100 - (mounted ? h : h * 0.15);
    return { x, y, h };
  });
  const line = coords.map((p) => `${p.x},${p.y}`).join(" ");

  return (
    <div className="relative h-9 w-full" onMouseLeave={() => setHoverIndex(null)}>
      <svg
        viewBox="0 0 100 100"
        preserveAspectRatio="none"
        className={cn("pointer-events-none h-full w-full", stroke)}
        aria-hidden
      >
        <polyline
          points={line}
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          vectorEffect="non-scaling-stroke"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="transition-opacity duration-500"
          style={{ opacity: mounted ? 1 : 0.35 }}
        />
        {coords.map((point, index) => (
          <circle
            key={`line-pt-${index}`}
            cx={point.x}
            cy={point.y}
            r={hoverIndex === index ? 3 : 1.75}
            className="fill-current transition-opacity"
            style={{ opacity: hoverIndex === index ? 1 : mounted ? 0.45 : 0.2 }}
          />
        ))}
      </svg>
      <div className="absolute inset-0 flex">
        {coords.map((point, index) => {
          const tip = [
            metricLabel,
            metricValue != null && metricValue !== "" ? `Value ${metricValue}` : null,
            `Point ${index + 1}`,
            `${Math.round(point.h)}%`,
          ]
            .filter(Boolean)
            .join(" · ");
          return (
            <Tooltip key={`line-hit-${index}`}>
              <TooltipTrigger
                delay={0}
                render={
                  <button
                    type="button"
                    className="h-full min-w-0 flex-1 cursor-default outline-none"
                    aria-label={tip}
                    onMouseEnter={() => setHoverIndex(index)}
                    onFocus={() => setHoverIndex(index)}
                  />
                }
              />
              <TooltipContent side="top" className="max-w-[16rem] px-2 py-1 text-[11px] tabular-nums">
                {tip}
              </TooltipContent>
            </Tooltip>
          );
        })}
      </div>
    </div>
  );
}

function KpiVizDots({
  heights,
  sparkClass,
  animate,
  metricLabel,
  metricValue,
}: {
  heights: number[];
  sparkClass: string;
  animate: boolean;
  metricLabel?: string;
  metricValue?: string | number | null;
}) {
  const [mounted, setMounted] = useState(!animate);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const points = heights.slice(0, 8);

  return (
    <div className="flex h-8 w-full items-end gap-1">
      {points.map((height, index) => {
        const tip = [
          metricLabel,
          metricValue != null && metricValue !== "" ? `Value ${metricValue}` : null,
          `Point ${index + 1}`,
          `${Math.round(height)}%`,
        ]
          .filter(Boolean)
          .join(" · ");
        return (
          <Tooltip key={`dot-${index}`}>
            <TooltipTrigger
              delay={0}
              render={
                <div className="relative flex h-full min-w-0 flex-1 cursor-default items-end justify-center outline-none" />
              }
            >
              <div
                className={cn(
                  "size-2 rounded-full transition-[opacity,transform] duration-500 ease-out",
                  sparkClass,
                  mounted ? "opacity-90 scale-100" : "opacity-30 scale-75",
                )}
                style={{
                  marginBottom: mounted ? `${Math.max(2, (height / 100) * 22)}px` : "2px",
                  transitionDelay: animate ? `${index * 35}ms` : "0ms",
                }}
              />
            </TooltipTrigger>
            <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
              {tip}
            </TooltipContent>
          </Tooltip>
        );
      })}
    </div>
  );
}

function KpiVizRing({
  value,
  strokeClass,
  animate,
  metricLabel,
}: {
  value: number | null;
  strokeClass: string;
  animate: boolean;
  metricLabel?: string;
}) {
  const [mounted, setMounted] = useState(!animate);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const pct = Math.max(
    0,
    Math.min(
      100,
      value === null ? 0 : value <= 100 ? value : Math.min(100, 40 + Math.log10(value + 1) * 18),
    ),
  );
  const shown = mounted ? pct : 0;
  const r = 16;
  const c = 2 * Math.PI * r;
  const dash = (shown / 100) * c;
  const tip = [metricLabel, value != null ? `Value ${value}` : null, `${Math.round(shown)}%`]
    .filter(Boolean)
    .join(" · ");

  return (
    <Tooltip>
      <TooltipTrigger
        delay={0}
        render={
          <div className="relative flex size-10 shrink-0 cursor-default items-center justify-center outline-none" />
        }
      >
        <svg viewBox="0 0 40 40" className="size-10 -rotate-90">
          <circle
            cx="20"
            cy="20"
            r={r}
            fill="none"
            strokeWidth="4"
            className="stroke-muted"
          />
          <circle
            cx="20"
            cy="20"
            r={r}
            fill="none"
            strokeWidth="4"
            strokeLinecap="round"
            className={cn(strokeClass, "transition-[stroke-dasharray] duration-700 ease-out")}
            strokeDasharray={`${dash} ${c}`}
          />
        </svg>
        <span className="absolute text-[10px] font-semibold tabular-nums text-foreground">
          {Math.round(shown)}
        </span>
      </TooltipTrigger>
      <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
        {tip}
      </TooltipContent>
    </Tooltip>
  );
}

function KpiVizSteps({
  value,
  sparkClass,
  animate,
  metricLabel,
}: {
  value: number | null;
  sparkClass: string;
  animate: boolean;
  metricLabel?: string;
}) {
  const [mounted, setMounted] = useState(!animate);
  useEffect(() => {
    if (!animate) return;
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, [animate]);

  const pct =
    value === null
      ? 40
      : value <= 0
        ? 0
        : value <= 100
          ? value
          : Math.min(100, 28 + Math.log10(value + 1) * 22);
  const segments = 7;
  const filled = mounted ? Math.round((pct / 100) * segments) : 0;
  const tip = [metricLabel, value != null ? `Value ${value}` : null, `${Math.round(pct)}%`]
    .filter(Boolean)
    .join(" · ");

  return (
    <Tooltip>
      <TooltipTrigger
        delay={0}
        render={
          <div className="flex w-full cursor-default flex-col justify-end gap-1 outline-none" />
        }
      >
        <div className="flex h-3 w-full items-stretch gap-1">
          {Array.from({ length: segments }, (_, index) => (
            <div
              key={`step-${index}`}
              className={cn(
                "min-w-0 flex-1 rounded-sm transition-colors duration-500",
                index < filled ? sparkClass : "bg-muted",
              )}
              style={{ transitionDelay: animate ? `${index * 40}ms` : "0ms" }}
            />
          ))}
        </div>
        <div className="flex justify-between text-[10px] tabular-nums text-muted-foreground">
          <span>Steps</span>
          <span>
            {filled}/{segments}
          </span>
        </div>
      </TooltipTrigger>
      <TooltipContent side="top" className="px-2 py-1 text-[11px] tabular-nums">
        {tip}
      </TooltipContent>
    </Tooltip>
  );
}

const AREA_STROKE: Record<Exclude<DashboardKpiAccent, "auto"> | KpiTone, string> = {
  sky: "text-sky-500",
  emerald: "text-emerald-500",
  amber: "text-amber-500",
  rose: "text-rose-500",
  slate: "text-slate-500",
  indigo: "text-indigo-500",
  neutral: "text-sky-500",
  success: "text-emerald-500",
  warning: "text-amber-500",
  danger: "text-rose-500",
};

function KpiCardVisual({
  style,
  heights,
  value,
  palette,
  accent,
  tone,
  animate,
  fill,
  gradientId,
  metricLabel,
}: {
  style: Exclude<DashboardKpiSparkStyle, "auto">;
  heights: number[];
  value: number | null;
  palette: { spark: string; stroke: string; tint: string };
  accent: DashboardKpiAccent;
  tone: KpiTone;
  animate: boolean;
  fill?: boolean;
  gradientId: string;
  metricLabel?: string;
}) {
  if (style === "none") return null;

  const areaKey =
    accent !== "auto"
      ? accent
      : tone === "neutral"
        ? "sky"
        : tone === "danger"
          ? "rose"
          : tone === "warning"
            ? "amber"
            : "emerald";
  const displayValue = value;

  if (style === "area") {
    return (
      <KpiVizArea
        heights={heights}
        stroke={AREA_STROKE[areaKey]}
        animate={animate}
        gradientId={gradientId}
        fill={fill}
        metricLabel={metricLabel}
        metricValue={displayValue}
      />
    );
  }
  if (style === "line") {
    return (
      <KpiVizLine
        heights={heights}
        stroke={AREA_STROKE[areaKey]}
        animate={animate}
        metricLabel={metricLabel}
        metricValue={displayValue}
      />
    );
  }
  if (style === "dots") {
    return (
      <KpiVizDots
        heights={heights}
        sparkClass={palette.spark}
        animate={animate}
        metricLabel={metricLabel}
        metricValue={displayValue}
      />
    );
  }
  if (style === "gauge") {
    return (
      <KpiVizGauge
        value={value}
        strokeClass={palette.stroke}
        animate={animate}
        fill={fill}
        metricLabel={metricLabel}
      />
    );
  }
  if (style === "ring") {
    return (
      <KpiVizRing
        value={value}
        strokeClass={palette.stroke}
        animate={animate}
        metricLabel={metricLabel}
      />
    );
  }
  if (style === "progress") {
    return (
      <KpiVizProgress
        value={value}
        sparkClass={palette.spark}
        animate={animate}
        fill={fill}
        metricLabel={metricLabel}
      />
    );
  }
  if (style === "steps") {
    return (
      <KpiVizSteps
        value={value}
        sparkClass={palette.spark}
        animate={animate}
        metricLabel={metricLabel}
      />
    );
  }
  if (style === "split") {
    return (
      <KpiVizSplit
        value={value}
        sparkClass={palette.spark}
        secondaryClass="bg-muted-foreground/35"
        animate={animate}
        fill={fill}
        metricLabel={metricLabel}
      />
    );
  }
  if (style === "thick") {
    return (
      <KpiVizBars
        heights={heights.slice(0, 6)}
        sparkClass={palette.spark}
        animate={animate}
        thick
        fill={fill}
        metricLabel={metricLabel}
        metricValue={displayValue}
      />
    );
  }
  return (
    <KpiVizBars
      heights={heights}
      sparkClass={palette.spark}
      animate={animate}
      fill={fill}
      metricLabel={metricLabel}
      metricValue={displayValue}
    />
  );
}

export function WidgetKpiTile({
  label,
  value,
  change,
  tone = "neutral",
  href,
  className,
  metricKey,
  index = 0,
  showSpark = true,
  sparkStyle = "auto",
  layout = "auto",
  accent = "auto",
  icon = "auto",
  tinted = false,
}: {
  label: string;
  value: string | number;
  change?: string | null;
  tone?: KpiTone;
  href?: string | null;
  className?: string;
  metricKey?: string;
  index?: number;
  showSpark?: boolean;
  sparkStyle?: DashboardKpiSparkStyle;
  layout?: DashboardKpiLayout;
  accent?: DashboardKpiAccent;
  icon?: DashboardKpiIconId;
  tinted?: boolean;
}) {
  const reducedMotion = usePrefersReducedMotion();
  const numeric = parseNumericValue(value);
  const animated = useCountUp(numeric, !reducedMotion && numeric !== null);
  const effectiveTone = accentToTone(accent, tone);
  const palette = resolveAccentClasses(accent, effectiveTone);
  const Icon = kpiIconFor(metricKey, effectiveTone, icon);
  const trendTone = change ? changeTone(change, effectiveTone) : effectiveTone;
  const badgeClass =
    trendTone === "danger"
      ? accentBadge.rose
      : trendTone === "success"
        ? accentBadge.emerald
        : palette.badge;
  const TrendIcon = change ? (trendTone === "danger" ? TrendingDown : TrendingUp) : null;
  const sparkSeed = `${metricKey ?? label}:${String(value)}`;
  const heights = useMemo(() => sparkHeights(sparkSeed, numeric), [sparkSeed, numeric]);
  const titleId = useId();
  const gradientUid = useId();
  const gradientId = `kpi-area-${gradientUid.replace(/:/g, "")}`;
  const resolvedViz = showSpark === false ? "none" : resolveKpiVizStyle(sparkStyle, index);
  const showViz = resolvedViz !== "none";
  /**
   * Auto stays stacked (same height Full vs ½) with a width-capped chart.
   * Gauge prefers side placement so the arc sits beside the metric without stretching.
   */
  const layoutMode =
    layout === "stack" || layout === "split"
      ? layout
      : resolveKpiLayout("auto", resolvedViz === "none" ? "bars" : resolvedViz);

  const metricBlock = (
    <>
      <p id={titleId} className="truncate text-xs font-medium text-muted-foreground">
        {label}
      </p>
      <p className="mt-0.5 text-xl font-semibold tabular-nums tracking-tight text-foreground">
        {formatKpiDisplay(value, reducedMotion ? numeric : animated)}
      </p>
    </>
  );

  const viz = showViz ? (
    <KpiCardVisual
      style={resolvedViz}
      heights={heights}
      value={numeric}
      palette={palette}
      accent={accent}
      tone={effectiveTone}
      animate={!reducedMotion}
      fill={false}
      gradientId={gradientId}
      metricLabel={label}
    />
  ) : null;

  const body = (
    <>
      <div
        className={cn("absolute inset-x-0 top-0 h-0.5 rounded-t-xl bg-gradient-to-r", palette.barTop)}
        aria-hidden
      />
      <div className="flex items-start justify-between gap-2">
        <span
          className={cn(
            "inline-flex size-7 shrink-0 items-center justify-center rounded-md transition-transform duration-300",
            palette.iconWrap,
            !reducedMotion && "group-hover:scale-[1.04]",
          )}
          aria-hidden
        >
          <Icon className="size-3.5" strokeWidth={2} />
        </span>
        {change ? (
          <span
            className={cn(
              "inline-flex max-w-[55%] items-center gap-1 truncate rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums",
              badgeClass,
            )}
          >
            {TrendIcon ? <TrendIcon className="size-3 shrink-0" aria-hidden /> : null}
            <span className="truncate">{change}</span>
          </span>
        ) : null}
      </div>
      <div
        className={cn(
          "mt-1.5 flex gap-2",
          layoutMode === "stack" && "flex-col",
          layoutMode === "split" && "flex-row items-end justify-between gap-3",
        )}
      >
        <div className="min-w-0 flex-1">{metricBlock}</div>
        {viz ? (
          <div
            className={cn(
              "shrink-0",
              // Cap chart width so Full never stretches sparks; gauge/ring stay fixed size
              resolvedViz === "gauge"
                ? "w-[5.25rem] max-w-[5.25rem]"
                : resolvedViz === "ring"
                  ? "w-10 max-w-10"
                  : "w-full max-w-[10.5rem]",
              layoutMode === "stack" && "mt-1",
            )}
          >
            {viz}
          </div>
        ) : null}
      </div>
    </>
  );

  const shell = cn(
    "@container/kpi group relative min-w-0 overflow-hidden rounded-xl border border-border bg-card p-3 shadow-sm",
    tinted && palette.tint,
    "transition-[box-shadow,border-color,opacity] duration-300 ease-out",
    "hover:border-border/80 hover:shadow-md",
    !reducedMotion && "motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-1",
    href &&
      "hover:bg-muted/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40",
    className,
  );

  const style = reducedMotion
    ? undefined
    : ({ animationDelay: `${Math.min(index, 8) * 55}ms`, animationFillMode: "both" } as const);

  if (href) {
    return (
      <Link href={href} className={shell} style={style} aria-labelledby={titleId}>
        {body}
      </Link>
    );
  }

  return (
    <article className={shell} style={style} aria-labelledby={titleId}>
      {body}
    </article>
  );
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
    <ul className="max-h-[16rem] divide-y divide-border overflow-auto rounded-xl border border-border bg-card shadow-sm">
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
            {item.href ? (
              <ArrowRight className="mt-1 size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
            ) : null}
          </div>
        );

        return (
          <li key={item.id} className="group bg-card transition-colors hover:bg-muted/30">
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

function shortcutIconFor(href: string): LucideIcon {
  const path = href.toLowerCase();
  if (path.includes("ticket")) return Ticket;
  if (path.includes("e-approval") || path.includes("approval")) return FileText;
  if (path.includes("doc-extract") || path.includes("extract")) return ScanLine;
  if (path.includes("export")) return Download;
  if (path.includes("project")) return ClipboardList;
  if (path.includes("asset") || path.includes("tower") || path.includes("fiber")) return Layers;
  return Activity;
}

export function WidgetShortcutGrid({
  items,
}: {
  items: Array<{ href: string; label: string; description?: string }>;
}) {
  if (items.length === 0) return <DashboardWidgetEmpty message="No shortcuts configured." />;

  // Dedupe by href so React keys stay unique when bags merge overlapping links
  const seen = new Set<string>();
  const unique = items.filter((item) => {
    const href = item.href?.trim();
    if (!href || seen.has(href)) return false;
    seen.add(href);
    return true;
  });

  return (
    <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
      {unique.map((item) => {
        const Icon = shortcutIconFor(item.href);
        return (
          <Link
            key={item.href}
            href={item.href}
            className={cn(
              "group relative flex min-w-0 items-start gap-2.5 overflow-hidden rounded-xl border border-border bg-card p-3 shadow-sm",
              "transition-[box-shadow,border-color,background-color] duration-200",
              "hover:border-border/80 hover:bg-muted/25 hover:shadow-md",
            )}
          >
            <span
              className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-sky-500 to-sky-400/40"
              aria-hidden
            />
            <span
              className="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-sky-500/10 text-sky-600 transition-transform duration-200 group-hover:scale-[1.04] dark:bg-sky-500/15 dark:text-sky-400"
              aria-hidden
            >
              <Icon className="size-3.5" strokeWidth={2} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium text-foreground">{item.label}</p>
              {item.description ? (
                <p className="mt-0.5 line-clamp-2 text-xs leading-snug text-muted-foreground">
                  {item.description}
                </p>
              ) : null}
            </div>
            <ArrowRight className="mt-0.5 size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
          </Link>
        );
      })}
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
    neutral: "border-sky-200/80 bg-sky-50/70 dark:border-sky-900/50 dark:bg-sky-950/25",
    success: "border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-900/50 dark:bg-emerald-950/25",
    warning: "border-amber-200/80 bg-amber-50/70 dark:border-amber-900/50 dark:bg-amber-950/25",
    danger: "border-red-200/80 bg-red-50/70 dark:border-red-900/50 dark:bg-red-950/25",
  };
  const toneBar: Record<"neutral" | "success" | "warning" | "danger", string> = {
    neutral: "from-sky-500 to-sky-400/40",
    success: "from-emerald-500 to-emerald-400/40",
    warning: "from-amber-500 to-amber-400/40",
    danger: "from-red-500 to-red-400/40",
  };
  const toneIcon: Record<"neutral" | "success" | "warning" | "danger", string> = {
    neutral: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
    success: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
    warning: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
    danger: "bg-red-500/10 text-red-600 dark:text-red-400",
  };
  const toneIconCmp: Record<"neutral" | "success" | "warning" | "danger", LucideIcon> = {
    neutral: Bell,
    success: CheckCircle2,
    warning: AlertCircle,
    danger: AlertCircle,
  };

  return (
    <ul className="space-y-2">
      {items.map((item) => {
        const tone = item.tone ?? "warning";
        const Icon = toneIconCmp[tone];
        const shell = cn(
          "group relative flex w-full items-start gap-2.5 overflow-hidden rounded-xl border px-3 py-2.5 text-left shadow-sm",
          "transition-[box-shadow,opacity] duration-200 hover:shadow-md",
          toneShell[tone],
        );
        const body = (
          <>
            <span
              className={cn("absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r", toneBar[tone])}
              aria-hidden
            />
            <span
              className={cn(
                "inline-flex size-7 shrink-0 items-center justify-center rounded-md",
                toneIcon[tone],
              )}
              aria-hidden
            >
              <Icon className="size-3.5" strokeWidth={2} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium text-foreground">{item.title}</p>
              {item.subtitle ? (
                <p className="mt-0.5 text-xs text-muted-foreground">{item.subtitle}</p>
              ) : null}
            </div>
            {item.href ? (
              <ArrowRight className="mt-1 size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
            ) : null}
          </>
        );
        if (item.href) {
          return (
            <li key={item.id}>
              <Link href={item.href} className={shell}>
                {body}
              </Link>
            </li>
          );
        }
        return (
          <li key={item.id} className={shell}>
            {body}
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
    <div className="relative overflow-hidden rounded-xl border border-border bg-card p-3 shadow-sm">
      <span
        className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-amber-500 to-amber-400/40"
        aria-hidden
      />
      <div className="flex items-start gap-2.5">
        <span
          className="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400"
          aria-hidden
        >
          <Lightbulb className="size-3.5" strokeWidth={2} />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-medium text-muted-foreground">{tipTitle}</p>
          <p className="mt-1 text-sm leading-relaxed text-foreground">{tipBody}</p>
        </div>
      </div>
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
      className={cn(
        "group relative flex items-center justify-between gap-3 overflow-hidden rounded-xl border border-border bg-card p-3 shadow-sm",
        "transition-[box-shadow,border-color,background-color] duration-200",
        "hover:border-border/80 hover:bg-muted/25 hover:shadow-md",
      )}
    >
      <span
        className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-slate-500 to-slate-400/40"
        aria-hidden
      />
      <div className="flex min-w-0 items-start gap-2.5">
        <span
          className="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-slate-500/10 text-slate-600 dark:text-slate-300"
          aria-hidden
        >
          <Download className="size-3.5" strokeWidth={2} />
        </span>
        <div className="min-w-0">
          <p className="text-sm font-medium text-foreground">{label}</p>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {message?.trim() ||
              (typeof count === "number"
                ? `${count} export${count === 1 ? "" : "s"} in recent history`
                : "Open download history and async jobs")}
          </p>
        </div>
      </div>
      <ArrowRight className="size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
    </Link>
  );
}

export function WidgetHeroBanner({
  title,
  description,
  ctaHref,
  ctaLabel,
  showCta = true,
  compact = false,
}: {
  title: string;
  description?: string;
  ctaHref?: string;
  ctaLabel?: string;
  showCta?: boolean;
  compact?: boolean;
}) {
  return (
    <div
      className={cn(
        "relative overflow-hidden rounded-xl border border-border bg-card shadow-sm",
        compact ? "px-4 py-3" : "px-5 py-6",
      )}
    >
      <div
        className="pointer-events-none absolute inset-y-0 right-0 w-1/3 bg-gradient-to-l from-sky-500/8 to-transparent"
        aria-hidden
      />
      <p
        className={cn(
          "font-semibold tracking-tight text-foreground",
          compact ? "text-lg" : "text-xl md:text-2xl",
        )}
      >
        {title}
      </p>
      {description ? (
        <p
          className={cn(
            "max-w-2xl leading-relaxed text-muted-foreground",
            compact ? "mt-1 text-xs" : "mt-1.5 text-sm",
          )}
        >
          {description}
        </p>
      ) : null}
      {showCta && ctaHref && ctaLabel ? (
        <Link
          href={ctaHref}
          className={cn(buttonVariants({ size: "sm" }), compact ? "mt-2.5 inline-flex" : "mt-4 inline-flex")}
        >
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
