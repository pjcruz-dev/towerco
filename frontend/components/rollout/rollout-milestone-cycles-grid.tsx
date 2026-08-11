"use client";

import { Download, FileImage, FileText } from "lucide-react";

import { Spinner } from "@/components/ui/spinner";
import { useMemo, useRef, useState } from "react";

import { AcronymLabel } from "@/components/help/acronym-label";
import { RolloutMilestoneCycleLabel } from "@/components/rollout/rollout-milestone-cycle-label";
import { Button } from "@/components/ui/button";
import { useIsMobile } from "@/hooks/use-mobile";
import { useLocalStorageState } from "@/hooks/use-local-storage-state";
import {
  MILESTONE_ZOOM_SCALES,
  MILESTONE_ZOOM_STORAGE_KEY,
  type MilestoneZoomScale,
} from "@/lib/rollout/milestone-preferences";
import { cn } from "@/lib/utils";

import { exportMilestoneGridAsPdf, exportMilestoneGridAsPng } from "./rollout-milestone-grid-export";
import {
  buildMilestoneTimelineRange,
  buildMilestoneTimelineSegments,
  calendarDaysBetween,
  formatTimelineDate,
  formatTimelineDateShort,
  formatTimelineDateWeekTick,
  gridBarExportColors,
  gridBarLabel,
  gridBarLabelCompact,
  segmentPosition,
  segmentProgressFill,
  tickLabelStyle,
  timelineTrackMinWidthPx,
  todayPosition,
  zoomScaleLabel,
  type MilestoneTimelineSegment,
} from "./rollout-milestone-grid-utils";
import type { RolloutDetail } from "@/modules/rollout/types";

/** Milestone name | duration | timeline (timeline grows with program length). */
const GRID_COLS_DESKTOP = "grid-cols-[minmax(13rem,16rem)_5.5rem_minmax(0,1fr)]";
const GRID_COLS_COMPACT = "grid-cols-[minmax(9rem,12rem)_4.5rem_minmax(0,1fr)]";

type Props = {
  detail: RolloutDetail;
  /** When set, used as initial zoom if user has no saved preference (e.g. month on tablet). */
  defaultZoom?: MilestoneZoomScale;
};

export function RolloutMilestoneCyclesGrid({ detail, defaultZoom }: Props) {
  const isMobile = useIsMobile();
  const exportRef = useRef<HTMLDivElement>(null);
  const [exporting, setExporting] = useState<"png" | "pdf" | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const [zoom, setZoom] = useLocalStorageState<MilestoneZoomScale>(
    MILESTONE_ZOOM_STORAGE_KEY,
    defaultZoom ?? "full",
    MILESTONE_ZOOM_SCALES,
  );

  const segments = buildMilestoneTimelineSegments(detail.milestone_cycles ?? [], detail);
  const range = buildMilestoneTimelineRange(segments, zoom);
  const todayPct = range ? todayPosition(range) : null;
  const timelineCompact = isMobile;
  const timelineMinWidth = range ? timelineTrackMinWidthPx(range, timelineCompact) : timelineCompact ? 520 : 720;
  const nameColPx = timelineCompact ? 9 * 16 : 13 * 16;
  const gridMinWidth = nameColPx + (timelineCompact ? 72 : 88) + timelineMinWidth;
  const gridCols = timelineCompact ? GRID_COLS_COMPACT : GRID_COLS_DESKTOP;

  async function handleExport(format: "png" | "pdf") {
    if (!exportRef.current || exporting) {
      return;
    }

    setExporting(format);
    setExportError(null);
    const filename = `${detail.rollout_ref}-milestones-${new Date().toISOString().slice(0, 10)}`;
    const title = `${detail.rollout_ref} · ${detail.search_ring_name ?? "Rollout milestones"}`;

    try {
      if (format === "png") {
        await exportMilestoneGridAsPng(exportRef.current, { filename, title });
      } else {
        await exportMilestoneGridAsPdf(exportRef.current, { filename, title });
      }
    } catch (error) {
      setExportError(error instanceof Error ? error.message : "Export failed. Try again.");
    } finally {
      setExporting(null);
    }
  }

  if (!range) {
    return (
      <div className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground shadow-sm">
        Set endorsement and Day-1 dates to render the milestone grid timeline.
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div className="inline-flex w-full rounded-lg border border-border bg-muted/30 p-0.5 sm:w-auto">
          {MILESTONE_ZOOM_SCALES.map((scale) => (
            <button
              key={scale}
              type="button"
              onClick={() => setZoom(scale)}
              className={cn(
                "min-h-11 flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors touch-manipulation sm:min-h-0 sm:flex-none sm:py-1.5 sm:text-xs",
                zoom === scale ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
              )}
            >
              {zoomScaleLabel(scale)}
            </button>
          ))}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            size="sm"
            variant="outline"
            className="min-h-11 flex-1 sm:min-h-0 sm:flex-none"
            disabled={exporting !== null}
            onClick={() => handleExport("png")}
          >
            {exporting === "png" ? <Spinner className="mr-1.5 size-3.5" /> : <FileImage className="mr-1.5 h-3.5 w-3.5" />}
            PNG
          </Button>
          <Button
            size="sm"
            variant="outline"
            className="min-h-11 flex-1 sm:min-h-0 sm:flex-none"
            disabled={exporting !== null}
            onClick={() => handleExport("pdf")}
          >
            {exporting === "pdf" ? <Spinner className="mr-1.5 size-3.5" /> : <FileText className="mr-1.5 h-3.5 w-3.5" />}
            PDF
          </Button>
        </div>
      </div>

      <p className="text-xs text-muted-foreground">
        {zoom === "full"
          ? "Full program scale — swipe horizontally on long programs. Bars use compact labels when narrow."
          : `${zoomScaleLabel(zoom)} view centered on today (clamped to program dates).`}
      </p>

      {exportError ? (
        <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
          {exportError}
        </p>
      ) : null}

      <div className="overflow-x-auto rounded-xl border border-border shadow-sm [-webkit-overflow-scrolling:touch]">
        <div
          ref={exportRef}
          data-milestone-grid-export=""
          className="w-full"
          style={{ minWidth: gridMinWidth, backgroundColor: "#ffffff", color: "#0f172a" }}
        >
          <div className="border-b px-4 py-3" style={{ borderColor: "#e2e8f0", backgroundColor: "#f8fafc" }}>
            <p className="text-sm font-medium" style={{ color: "#0f172a" }}>
              {detail.search_ring_name ?? detail.rollout_ref}
            </p>
            <p className="mt-0.5 text-xs" style={{ color: "#475569" }}>
              {detail.rollout_ref}
              {detail.endorsement_date ? ` · Start ${formatTimelineDateShort(new Date(`${detail.endorsement_date}T00:00:00`))}` : ""}
              {detail.target_rfi_working_date ? (
                <>
                  {" "}
                  · <AcronymLabel term="RFI / RFTI">Target RFI</AcronymLabel>{" "}
                  {formatTimelineDateShort(new Date(`${detail.target_rfi_working_date}T00:00:00`))}
                </>
              ) : null}
              {` · ${zoomScaleLabel(zoom)}`}
            </p>
          </div>

          <div
            className={cn("grid border-b text-xs font-medium", gridCols)}
            style={{ borderColor: "#e2e8f0", backgroundColor: "#f8fafc", color: "#475569" }}
          >
            <div className="px-4 py-2.5">Milestone</div>
            <div className="border-l px-2 py-2.5 text-center" style={{ borderColor: "#e2e8f0" }}>
              Duration
            </div>
            <div className="border-l px-3 py-2" style={{ borderColor: "#e2e8f0" }}>
              <p className="mb-1.5 text-[10px] font-medium uppercase tracking-wide text-slate-500">Timeline</p>
              <TimelineTickRow range={range} compact={timelineCompact} />
            </div>
          </div>

          {segments.map((segment) => (
            <MilestoneGridRow
              key={segment.phase_key}
              segment={segment}
              range={range}
              todayPct={todayPct}
              timelineMinWidth={timelineMinWidth}
              gridCols={gridCols}
            />
          ))}

          <div
            className="flex flex-wrap items-center gap-4 border-t px-4 py-2 text-[11px]"
            style={{ borderColor: "#e2e8f0", backgroundColor: "#f8fafc", color: "#475569" }}
          >
            <span className="inline-flex items-center gap-1.5">
              <Download className="h-3 w-3" />
              TowerOS milestone grid
            </span>
            <LegendSwatch color="#22c55e" label="Completed" />
            <LegendSwatch color="#3b82f6" label="In progress" />
            <LegendSwatch color="#f59e0b" label="Delayed / at risk" />
            <LegendSwatch color="#cbd5e1" label="Scheduled" />
          </div>
        </div>
      </div>
    </div>
  );
}

function TimelineTickRow({
  range,
  compact = false,
}: {
  range: NonNullable<ReturnType<typeof buildMilestoneTimelineRange>>;
  compact?: boolean;
}) {
  const ticks = range.ticks;
  const minWidth = timelineTrackMinWidthPx(range, compact);

  if (range.scale === "week") {
    const columnMin = compact ? "3.5rem" : "4rem";

    return (
      <div
        className="grid min-h-8 w-full gap-0"
        style={{
          minWidth,
          gridTemplateColumns: `repeat(${ticks.length}, minmax(${columnMin}, 1fr))`,
        }}
      >
        {ticks.map((tick, index) => (
          <div
            key={tick.toISOString()}
            className={cn(
              "min-w-0 px-0.5 text-[10px] leading-tight text-slate-600",
              index === 0 ? "text-left" : index === ticks.length - 1 ? "text-right" : "text-center",
            )}
            title={formatTimelineDate(tick)}
          >
            {index === 0 ? (
              <span className="block">
                <span className="text-[9px] font-medium uppercase tracking-wide text-slate-400">Start</span>
                <span className="block tabular-nums">{formatTimelineDateWeekTick(tick)}</span>
              </span>
            ) : (
              <span className="block truncate tabular-nums">{formatTimelineDateWeekTick(tick)}</span>
            )}
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="relative h-6 w-full" style={{ minWidth }}>
      {ticks.map((tick, index) => {
        const tickPct = (calendarDaysBetween(range.start, tick) / range.totalDays) * 100;
        const style = tickLabelStyle(tickPct, index, ticks.length);
        const label = formatTimelineDate(tick);

        return (
          <span
            key={tick.toISOString()}
            className="absolute top-0 max-w-[7rem] truncate whitespace-nowrap"
            style={{
              left: style.left,
              transform: style.transform,
              textAlign: style.textAlign,
            }}
            title={formatTimelineDate(tick)}
          >
            {index === 0 && range.scale !== "month" ? `Start · ${label}` : label}
          </span>
        );
      })}
    </div>
  );
}

function LegendSwatch({ color, label }: { color: string; label: string }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: color }} />
      {label}
    </span>
  );
}

function MilestoneGridRow({
  segment,
  range,
  todayPct,
  timelineMinWidth,
  gridCols,
}: {
  segment: MilestoneTimelineSegment;
  range: NonNullable<ReturnType<typeof buildMilestoneTimelineRange>>;
  todayPct: number | null;
  timelineMinWidth: number;
  gridCols: string;
}) {
  const position = segmentPosition(segment, range);
  const fillPct = segmentProgressFill(segment);
  const colors = gridBarExportColors(segment.status);
  const showPercent = segment.status === "active" || segment.status === "at_risk";
  const useCompactLabel = !position || position.widthPct < 14;
  const barTitle = `${segment.label} · ${gridBarLabel(segment.status)}${showPercent ? ` · ${Math.round(fillPct)}%` : ""} · ${segment.start_date ?? "?"} → ${segment.end_date ?? "?"}`;

  const barLabel = useMemo(() => {
    if (!position) {
      return "";
    }
    const status = useCompactLabel ? gridBarLabelCompact(segment.status) : gridBarLabel(segment.status);
    if (showPercent && position.widthPct >= 10) {
      return `${status} ${Math.round(fillPct)}%`;
    }
    return status;
  }, [fillPct, position, segment.status, showPercent, useCompactLabel]);

  const labelOnFill = fillPct >= 40 || segment.status === "completed" || segment.status === "overdue";

  return (
    <div className={cn("grid border-b last:border-b-0", gridCols)} style={{ borderColor: "#e2e8f0" }}>
      <div
        className="px-4 py-2.5 text-sm font-medium leading-snug"
        style={{ color: "#0f172a" }}
        title={segment.label}
      >
        <span className="line-clamp-2">
          <RolloutMilestoneCycleLabel cycle={segment} />
        </span>
      </div>
      <div
        className="flex items-center justify-center border-l px-2 py-2.5 text-xs tabular-nums"
        style={{ borderColor: "#e2e8f0", color: "#475569" }}
      >
        {segment.target_working_days} wd
      </div>
      <div className="border-l px-3 py-2.5" style={{ borderColor: "#e2e8f0" }}>
        <div className="relative h-10 w-full rounded-md" style={{ minWidth: timelineMinWidth, backgroundColor: "#f1f5f9" }}>
          {range.ticks.slice(1, -1).map((tick) => {
            const tickPct = (calendarDaysBetween(range.start, tick) / range.totalDays) * 100;
            return (
              <span
                key={tick.toISOString()}
                className="pointer-events-none absolute inset-y-0 border-l border-slate-200/80"
                style={{ left: `${tickPct}%` }}
              />
            );
          })}

          {todayPct !== null ? (
            <span
              className="pointer-events-none absolute inset-y-0 z-20 w-px"
              style={{ left: `${todayPct}%`, backgroundColor: "#2563eb" }}
              title="Today"
            />
          ) : null}

          {position ? (
            <div
              className="absolute inset-y-1 z-10 overflow-hidden rounded shadow-sm"
              style={{
                left: `${position.leftPct}%`,
                width: `${Math.max(position.widthPct, 3)}%`,
                backgroundColor: colors.track,
              }}
              title={barTitle}
            >
              <div
                className="absolute inset-y-0 left-0 transition-[width]"
                style={{ width: `${fillPct}%`, backgroundColor: colors.fill }}
              />
              {barLabel ? (
                <span
                  className="relative z-10 flex h-full items-center px-1.5 text-[10px] font-medium leading-none"
                  style={{ color: labelOnFill ? colors.text : "#1e293b" }}
                >
                  <span className="truncate">{barLabel}</span>
                </span>
              ) : null}
            </div>
          ) : (
            <div className="absolute inset-y-1 flex items-center px-2 text-[11px]" style={{ color: "#64748b" }}>
              Not in this view
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
