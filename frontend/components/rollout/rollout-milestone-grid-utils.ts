import type { RolloutDetail, RolloutMilestoneCycle } from "@/modules/rollout/types";
import type { MilestoneZoomScale } from "@/lib/rollout/milestone-preferences";

export type MilestoneTimelineSegment = RolloutMilestoneCycle & {
  start_date: string | null;
  end_date: string | null;
};

export type MilestoneTimelineRange = {
  start: Date;
  end: Date;
  totalDays: number;
  ticks: Date[];
  scale: MilestoneZoomScale;
};

const DAY_MS = 86_400_000;

function parseDate(value: string): Date {
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, month - 1, day);
}

function formatDateKey(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function addCalendarDays(date: Date, days: number): Date {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

export function calendarDaysBetween(start: Date, end: Date): number {
  return Math.max(0, Math.round((end.getTime() - start.getTime()) / DAY_MS));
}

export function buildMilestoneTimelineSegments(
  cycles: RolloutMilestoneCycle[],
  detail: Pick<RolloutDetail, "endorsement_date" | "tssr_approved_date" | "target_rfi_working_date">,
): MilestoneTimelineSegment[] {
  let chainEnd: string | null = null;

  return cycles.map((cycle) => {
    let startDate: string | null = null;

    if (cycle.anchor === "day_one") {
      chainEnd = null;
      startDate = detail.tssr_approved_date;
    } else if (chainEnd) {
      startDate = chainEnd;
    } else {
      startDate = detail.endorsement_date;
    }

    const endDate = cycle.target_date;
    if (endDate) {
      chainEnd = endDate;
    }

    return {
      ...cycle,
      start_date: startDate,
      end_date: endDate,
    };
  });
}

function buildFullProgramRange(segments: MilestoneTimelineSegment[]): MilestoneTimelineRange | null {
  const dated = segments.filter((segment) => segment.start_date && segment.end_date);
  if (dated.length === 0) {
    return null;
  }

  let min = parseDate(dated[0].start_date!);
  let max = parseDate(dated[0].end_date!);

  for (const segment of dated) {
    const start = parseDate(segment.start_date!);
    const end = parseDate(segment.end_date!);
    if (start < min) min = start;
    if (end > max) max = end;
  }

  const paddedStart = addCalendarDays(min, -3);
  const paddedEnd = addCalendarDays(max, 7);
  const totalDays = Math.max(1, calendarDaysBetween(paddedStart, paddedEnd));
  const tickCount = Math.min(8, Math.max(4, Math.ceil(totalDays / 14)));

  return {
    start: paddedStart,
    end: paddedEnd,
    totalDays,
    ticks: buildTicks(paddedStart, totalDays, tickCount),
    scale: "full",
  };
}

function buildTicks(start: Date, totalDays: number, tickCount: number): Date[] {
  const ticks: Date[] = [];
  for (let index = 0; index <= tickCount; index += 1) {
    const offset = Math.round((totalDays / tickCount) * index);
    ticks.push(addCalendarDays(start, offset));
  }
  return ticks;
}

function resolveFocusDate(fullRange: MilestoneTimelineRange): Date {
  const today = parseDate(formatDateKey(new Date()));
  if (today >= fullRange.start && today <= fullRange.end) {
    return today;
  }
  if (today < fullRange.start) {
    return fullRange.start;
  }
  return fullRange.end;
}

export function buildMilestoneTimelineRange(
  segments: MilestoneTimelineSegment[],
  zoom: MilestoneZoomScale = "full",
): MilestoneTimelineRange | null {
  const fullRange = buildFullProgramRange(segments);
  if (!fullRange) {
    return null;
  }

  if (zoom === "full") {
    return fullRange;
  }

  const windowDays = zoom === "week" ? 7 : 30;
  const focus = resolveFocusDate(fullRange);
  let start = addCalendarDays(focus, -Math.floor(windowDays / 2));
  let end = addCalendarDays(start, windowDays);

  if (start < fullRange.start) {
    start = fullRange.start;
    end = addCalendarDays(start, windowDays);
  }

  if (end > fullRange.end) {
    end = fullRange.end;
    start = addCalendarDays(end, -windowDays);
    if (start < fullRange.start) {
      start = fullRange.start;
    }
  }

  const totalDays = Math.max(1, calendarDaysBetween(start, end));
  const tickCount = zoom === "week" ? Math.min(windowDays, 7) : Math.min(6, Math.max(4, Math.ceil(totalDays / 7)));

  return {
    start,
    end,
    totalDays,
    ticks: buildTicks(start, totalDays, tickCount),
    scale: zoom,
  };
}

export function segmentPosition(
  segment: MilestoneTimelineSegment,
  range: MilestoneTimelineRange,
): { leftPct: number; widthPct: number } | null {
  if (!segment.start_date || !segment.end_date) {
    return null;
  }

  const start = parseDate(segment.start_date);
  const end = parseDate(segment.end_date);
  const visibleStart = start < range.start ? range.start : start;
  const visibleEnd = end > range.end ? range.end : end;

  if (visibleEnd < range.start || visibleStart > range.end) {
    return null;
  }

  const leftDays = calendarDaysBetween(range.start, visibleStart);
  const spanDays = Math.max(1, calendarDaysBetween(visibleStart, visibleEnd));
  const leftPct = (leftDays / range.totalDays) * 100;
  const widthPct = Math.max(3, (spanDays / range.totalDays) * 100);

  return { leftPct, widthPct };
}

export function segmentProgressFill(segment: MilestoneTimelineSegment, todayKey?: string): number {
  if (!segment.start_date || !segment.end_date) {
    return 0;
  }

  const today = parseDate(todayKey ?? formatDateKey(new Date()));
  const start = parseDate(segment.start_date);
  const end = parseDate(segment.end_date);

  if (segment.status === "overdue") {
    return 100;
  }

  if (segment.status === "completed") {
    if (today < start) {
      return 0;
    }
    if (today >= end) {
      return 100;
    }
    const total = calendarDaysBetween(start, end);
    const elapsed = calendarDaysBetween(start, today);
    return total <= 0 ? 100 : Math.min(100, Math.max(8, (elapsed / total) * 100));
  }

  if (segment.status === "pending") {
    return 0;
  }

  if (today <= start) {
    return 0;
  }

  if (today >= end) {
    return 100;
  }

  const total = calendarDaysBetween(start, end);
  const elapsed = calendarDaysBetween(start, today);

  if (total <= 0) {
    return 100;
  }

  return Math.min(100, Math.max(8, (elapsed / total) * 100));
}

export function formatTimelineDate(date: Date): string {
  return date.toLocaleDateString(undefined, { month: "numeric", day: "numeric", year: "numeric" });
}

export function formatTimelineDateShort(date: Date): string {
  return date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

/** Compact header label for week-scale columns (avoids year + "Start ·" overlap). */
export function formatTimelineDateWeekTick(date: Date): string {
  return date.toLocaleDateString(undefined, { month: "numeric", day: "numeric" });
}

export function todayPosition(range: MilestoneTimelineRange): number | null {
  const today = parseDate(formatDateKey(new Date()));
  if (today < range.start || today > range.end) {
    return null;
  }

  return (calendarDaysBetween(range.start, today) / range.totalDays) * 100;
}

export function gridBarExportColors(status: RolloutMilestoneCycle["status"]): {
  track: string;
  fill: string;
  text: string;
} {
  switch (status) {
    case "completed":
      return { track: "#22c55e", fill: "#22c55e", text: "#ffffff" };
    case "overdue":
      return { track: "#f59e0b", fill: "#f59e0b", text: "#1e293b" };
    case "at_risk":
      return { track: "#fef3c7", fill: "#fbbf24", text: "#1e293b" };
    case "active":
      return { track: "#dbeafe", fill: "#3b82f6", text: "#ffffff" };
    default:
      return { track: "#e2e8f0", fill: "#94a3b8", text: "#475569" };
  }
}

export function gridBarFillTone(status: RolloutMilestoneCycle["status"]): string {
  switch (status) {
    case "completed":
      return "bg-green-500 dark:bg-green-600";
    case "overdue":
      return "bg-amber-500 dark:bg-amber-400";
    case "at_risk":
      return "bg-amber-400 dark:bg-amber-300";
    case "active":
      return "bg-blue-500 dark:bg-blue-600";
    default:
      return "bg-slate-400 dark:bg-slate-500";
  }
}

export function gridBarTrackTone(status: RolloutMilestoneCycle["status"]): string {
  switch (status) {
    case "completed":
      return "bg-green-500 dark:bg-green-600";
    case "overdue":
      return "bg-amber-500 dark:bg-amber-400";
    case "at_risk":
      return "bg-amber-100 dark:bg-amber-950/60";
    case "active":
      return "bg-blue-100 dark:bg-blue-950/60";
    default:
      return "bg-slate-200 dark:bg-slate-700/60";
  }
}

export function gridBarLabel(status: RolloutMilestoneCycle["status"]): string {
  switch (status) {
    case "completed":
      return "Completed";
    case "overdue":
      return "Delayed";
    case "at_risk":
      return "At risk";
    case "active":
      return "In progress";
    default:
      return "Scheduled";
  }
}

/** Compact label for narrow milestone bars (avoids "In progr…" truncation). */
export function gridBarLabelCompact(status: RolloutMilestoneCycle["status"]): string {
  switch (status) {
    case "completed":
      return "Done";
    case "overdue":
      return "Delayed";
    case "at_risk":
      return "At risk";
    case "active":
      return "Active";
    default:
      return "Planned";
  }
}

export function timelineTrackMinWidthPx(range: MilestoneTimelineRange, compact = false): number {
  const pxPerDay = compact
    ? range.scale === "full"
      ? 7
      : range.scale === "month"
        ? 14
        : 24
    : range.scale === "full"
      ? 10
      : range.scale === "month"
        ? 18
        : 32;
  const minBase = compact
    ? range.scale === "full"
      ? 520
      : range.scale === "week"
        ? 448
        : 360
    : range.scale === "full"
      ? 720
      : range.scale === "week"
        ? 512
        : 420;
  const weekColumnMin = compact ? 56 : 64;
  const weekWidth =
    range.scale === "week" ? Math.max(minBase, range.ticks.length * weekColumnMin) : 0;

  return Math.max(weekWidth, minBase, Math.round(range.totalDays * pxPerDay));
}

export function tickLabelStyle(
  tickPct: number,
  index: number,
  totalTicks: number,
): { left: string; transform: string; textAlign: "left" | "center" | "right" } {
  if (index === 0) {
    return { left: "0%", transform: "translateX(0)", textAlign: "left" };
  }
  if (index === totalTicks - 1) {
    return { left: "100%", transform: "translateX(-100%)", textAlign: "right" };
  }
  return { left: `${tickPct}%`, transform: "translateX(-50%)", textAlign: "center" };
}

export function gridBarTextTone(status: RolloutMilestoneCycle["status"], fillPct: number): string {
  if (status === "pending") {
    return "text-slate-600 dark:text-slate-300";
  }

  if (fillPct >= 45 || status === "completed" || status === "overdue") {
    return "text-white";
  }

  return "text-slate-800 dark:text-slate-100";
}

export function zoomScaleLabel(scale: MilestoneZoomScale): string {
  switch (scale) {
    case "week":
      return "Week";
    case "month":
      return "Month";
    default:
      return "Full program";
  }
}
