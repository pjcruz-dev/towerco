"use client";

import { useMemo, useState } from "react";
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { fieldControlClassName } from "@/lib/ui/field-control";
import { cn } from "@/lib/utils";

const WEEKDAYS = ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"] as const;
const MONTHS = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
] as const;

export type DateRangePickerValue = {
  from: string;
  to: string;
};

type Props = {
  value: DateRangePickerValue;
  onChange: (value: DateRangePickerValue) => void;
  disabled?: boolean;
  readOnly?: boolean;
  placeholder?: string;
  className?: string;
  id?: string;
  /** Each end stores `yyyy-MM-ddTHH:mm`. A date with no time stays `yyyy-MM-dd`. */
  withTime?: boolean;
  /** Days before this `yyyy-MM-dd` cannot be chosen. */
  minDate?: string;
  "aria-invalid"?: boolean;
};

function splitEndpoint(value: string): { date: string; time: string } {
  const match = /^(\d{4}-\d{2}-\d{2})(?:T(\d{2}:\d{2}))?/.exec(value.trim());
  if (!match) {
    return { date: "", time: "" };
  }
  return { date: match[1] ?? "", time: match[2] ?? "" };
}

function joinEndpoint(date: string, time: string, withTime: boolean): string {
  if (!date) {
    return "";
  }
  if (!withTime || !time) {
    return date;
  }
  return `${date}T${time}`;
}

function parseIsoDate(value: string): Date | null {
  const trimmed = splitEndpoint(value).date;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
    return null;
  }
  const [y, m, d] = trimmed.split("-").map(Number);
  if (!y || !m || !d) {
    return null;
  }
  const date = new Date(y, m - 1, d);
  if (date.getFullYear() !== y || date.getMonth() !== m - 1 || date.getDate() !== d) {
    return null;
  }
  return date;
}

function toIsoDate(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function formatDisplay(date: Date): string {
  return date.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatEndpointLabel(date: Date, time: string): string {
  const clock = formatClockLabel(time);
  return clock ? `${formatDisplay(date)}, ${clock}` : formatDisplay(date);
}

const CLOCK_HOURS = ["12", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11"] as const;
const CLOCK_MINUTES = Array.from({ length: 60 }, (_, minute) => String(minute).padStart(2, "0"));

function to12Hour(time: string): { hour: string; minute: string; meridiem: "AM" | "PM" } | null {
  const match = /^(\d{2}):(\d{2})$/.exec(time);
  if (!match) {
    return null;
  }
  const hour24 = Number(match[1]);
  const meridiem = hour24 >= 12 ? "PM" : "AM";
  return { hour: String(hour24 % 12 || 12), minute: match[2] ?? "00", meridiem };
}

function to24Hour(hour: string, minute: string, meridiem: "AM" | "PM"): string {
  let hour24 = Number(hour) % 12;
  if (meridiem === "PM") {
    hour24 += 12;
  }
  return `${String(hour24).padStart(2, "0")}:${minute}`;
}

function formatClockLabel(time: string): string {
  const clock = to12Hour(time);
  return clock ? `${clock.hour}:${clock.minute} ${clock.meridiem}` : "";
}

const timeSelectClassName =
  "h-8 min-w-0 flex-1 rounded-md border border-input bg-card px-1 text-center text-sm tabular-nums text-foreground outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:bg-muted/40 disabled:opacity-60";

function RangeTimeField({
  label,
  value,
  disabled,
  onChange,
}: {
  label: string;
  value: string;
  disabled?: boolean;
  onChange: (value: string) => void;
}) {
  const clock = to12Hour(value);
  const hour = clock?.hour ?? "";
  const minute = clock?.minute ?? "";
  const meridiem = clock?.meridiem ?? "AM";
  const update = (next: { hour?: string; minute?: string; meridiem?: "AM" | "PM" }) => {
    onChange(
      to24Hour(next.hour || hour || "12", next.minute || minute || "00", next.meridiem ?? meridiem),
    );
  };

  return (
    <div className="mt-3 border-t border-border pt-3">
      <p className="mb-1.5 text-center text-xs font-medium text-foreground">{label}</p>
      <div className="flex items-center gap-1.5">
        <select
          aria-label={`${label} hour`}
          disabled={disabled}
          value={hour}
          className={timeSelectClassName}
          onChange={(event) => update({ hour: event.target.value })}
        >
          {hour === "" ? <option value="">—</option> : null}
          {CLOCK_HOURS.map((item) => (
            <option key={item} value={item}>
              {item}
            </option>
          ))}
        </select>
        <span className="text-sm text-muted-foreground" aria-hidden>
          :
        </span>
        <select
          aria-label={`${label} minute`}
          disabled={disabled}
          value={minute}
          className={timeSelectClassName}
          onChange={(event) => update({ minute: event.target.value })}
        >
          {minute === "" ? <option value="">—</option> : null}
          {CLOCK_MINUTES.map((item) => (
            <option key={item} value={item}>
              {item}
            </option>
          ))}
        </select>
        <select
          aria-label={`${label} AM or PM`}
          disabled={disabled || hour === ""}
          value={meridiem}
          className={cn(timeSelectClassName, "max-w-[4.5rem]")}
          onChange={(event) => update({ meridiem: event.target.value === "PM" ? "PM" : "AM" })}
        >
          <option value="AM">AM</option>
          <option value="PM">PM</option>
        </select>
      </div>
    </div>
  );
}

function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function addMonths(date: Date, count: number): Date {
  return new Date(date.getFullYear(), date.getMonth() + count, 1);
}

function sameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

function isBeforeDay(a: Date, b: Date): boolean {
  return toIsoDate(a) < toIsoDate(b);
}

function isAfterDay(a: Date, b: Date): boolean {
  return toIsoDate(a) > toIsoDate(b);
}

function buildMonthCells(month: Date): Date[] {
  const first = startOfMonth(month);
  const startOffset = first.getDay();
  const gridStart = new Date(first);
  gridStart.setDate(first.getDate() - startOffset);

  return Array.from({ length: 42 }, (_, index) => {
    const cell = new Date(gridStart);
    cell.setDate(gridStart.getDate() + index);
    return cell;
  });
}

function MonthGrid({
  month,
  from,
  to,
  hover,
  selectingEnd,
  today,
  minDate,
  onSelectDay,
  onHoverDay,
}: {
  month: Date;
  from: Date | null;
  to: Date | null;
  hover: Date | null;
  selectingEnd: boolean;
  today: Date;
  minDate?: string;
  onSelectDay: (day: Date) => void;
  onHoverDay: (day: Date | null) => void;
}) {
  const cells = useMemo(() => buildMonthCells(month), [month]);

  const rangeEnd =
    selectingEnd && from && hover
      ? isBeforeDay(hover, from)
        ? from
        : hover
      : to;
  const rangeStart =
    selectingEnd && from && hover
      ? isBeforeDay(hover, from)
        ? hover
        : from
      : from;

  return (
    <div className="w-[16.5rem]">
      <p className="mb-2 text-center text-sm font-medium text-foreground">
        {MONTHS[month.getMonth()]} {month.getFullYear()}
      </p>
      <div className="grid grid-cols-7 gap-0">
        {WEEKDAYS.map((day) => (
          <div key={day} className="py-1 text-center text-[11px] font-medium text-muted-foreground">
            {day}
          </div>
        ))}
        {cells.map((cell) => {
          const inMonth = cell.getMonth() === month.getMonth();
          const isFrom = rangeStart ? sameDay(cell, rangeStart) : false;
          const isTo = rangeEnd ? sameDay(cell, rangeEnd) : false;
          const isEndpoint = isFrom || isTo;
          const inRange =
            rangeStart &&
            rangeEnd &&
            !sameDay(rangeStart, rangeEnd) &&
            !isBeforeDay(cell, rangeStart) &&
            !isAfterDay(cell, rangeEnd);
          const isToday = sameDay(cell, today);
          const iso = toIsoDate(cell);
          const tooEarly = Boolean(minDate && iso < minDate);

          return (
            <button
              key={`${month.getFullYear()}-${month.getMonth()}-${iso}`}
              type="button"
              disabled={tooEarly}
              onClick={() => {
                if (!tooEarly) {
                  onSelectDay(cell);
                }
              }}
              onMouseEnter={() => {
                if (!tooEarly) {
                  onHoverDay(cell);
                }
              }}
              onMouseLeave={() => onHoverDay(null)}
              className={cn(
                "relative flex h-8 w-full items-center justify-center text-sm transition-colors",
                "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:z-10",
                tooEarly && "cursor-not-allowed text-muted-foreground/30",
                !inMonth && !tooEarly && "text-muted-foreground/40",
                inMonth && !isEndpoint && !tooEarly && "text-foreground",
                inRange && !isEndpoint && "bg-primary/15",
                isFrom && rangeEnd && !sameDay(rangeStart!, rangeEnd) && "rounded-l-md bg-primary/15",
                isTo && rangeStart && !sameDay(rangeStart, rangeEnd!) && "rounded-r-md bg-primary/15",
                isToday && !isEndpoint && "font-medium",
              )}
            >
              <span
                className={cn(
                  "flex h-8 w-8 items-center justify-center rounded-md",
                  !isEndpoint && "hover:bg-muted",
                  isToday && !isEndpoint && "ring-1 ring-border",
                  isEndpoint && "bg-primary text-primary-foreground hover:bg-primary/90",
                )}
              >
                {cell.getDate()}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

export function DateRangePicker({
  value,
  onChange,
  disabled,
  readOnly,
  placeholder = "Select date range",
  className,
  id,
  withTime = false,
  minDate,
  "aria-invalid": ariaInvalid,
}: Props) {
  const fromParts = splitEndpoint(value.from);
  const toParts = splitEndpoint(value.to);
  const fromDate = useMemo(() => parseIsoDate(value.from), [value.from]);
  const toDate = useMemo(() => parseIsoDate(value.to), [value.to]);
  const [open, setOpen] = useState(false);
  const [draftFrom, setDraftFrom] = useState<Date | null>(fromDate);
  const [draftTo, setDraftTo] = useState<Date | null>(toDate);
  const [fromTime, setFromTime] = useState(fromParts.time);
  const [toTime, setToTime] = useState(toParts.time);
  const [selectingEnd, setSelectingEnd] = useState(false);
  const [hoverDay, setHoverDay] = useState<Date | null>(null);
  const [leftMonth, setLeftMonth] = useState(() => startOfMonth(fromDate ?? new Date()));

  const today = useMemo(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
  }, []);

  const locked = disabled || readOnly;
  const rightMonth = addMonths(leftMonth, 1);

  const displayLabel = useMemo(() => {
    const fromLabel = fromDate ? formatEndpointLabel(fromDate, fromParts.time) : "";
    const toLabel = toDate ? formatEndpointLabel(toDate, toParts.time) : "";
    if (fromLabel && toLabel) {
      return `${fromLabel} – ${toLabel}`;
    }
    if (fromLabel) {
      return `${fromLabel} – …`;
    }
    return null;
  }, [fromDate, fromParts.time, toDate, toParts.time]);

  const commit = (from: Date | null, to: Date | null, nextFromTime: string, nextToTime: string) => {
    onChange({
      from: from ? joinEndpoint(toIsoDate(from), nextFromTime, withTime) : "",
      to: to ? joinEndpoint(toIsoDate(to), nextToTime, withTime) : "",
    });
  };

  const resetDraftFromValue = () => {
    setDraftFrom(fromDate);
    setDraftTo(toDate);
    setFromTime(fromParts.time);
    setToTime(toParts.time);
    setSelectingEnd(false);
    setHoverDay(null);
    setLeftMonth(startOfMonth(fromDate ?? new Date()));
  };

  const handleSelectDay = (day: Date) => {
    if (minDate && toIsoDate(day) < minDate) {
      return;
    }
    if (!selectingEnd || !draftFrom) {
      setDraftFrom(day);
      setDraftTo(null);
      setSelectingEnd(true);
      if (withTime && !fromTime) {
        setFromTime("00:00");
      }
      return;
    }

    let nextFrom = draftFrom;
    let nextTo = day;
    let nextFromTime = withTime ? fromTime || "00:00" : "";
    let nextToTime = withTime ? toTime || "00:00" : "";
    if (isBeforeDay(day, draftFrom)) {
      nextFrom = day;
      nextTo = draftFrom;
      const swappedFrom = nextToTime;
      nextToTime = nextFromTime;
      nextFromTime = swappedFrom;
    }

    setDraftFrom(nextFrom);
    setDraftTo(nextTo);
    setFromTime(nextFromTime);
    setToTime(nextToTime);
    setSelectingEnd(false);
    setHoverDay(null);
    commit(nextFrom, nextTo, nextFromTime, nextToTime);
    if (!withTime) {
      setOpen(false);
    }
  };

  const sameDayReversed =
    withTime &&
    draftFrom &&
    draftTo &&
    sameDay(draftFrom, draftTo) &&
    fromTime !== "" &&
    toTime !== "" &&
    toTime < fromTime;

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        if (locked) {
          return;
        }
        setOpen(next);
        if (next) {
          resetDraftFromValue();
        } else {
          setHoverDay(null);
          setSelectingEnd(false);
        }
      }}
    >
      <PopoverTrigger
        disabled={locked}
        render={
          <button
            id={id}
            type="button"
            disabled={locked}
            aria-invalid={ariaInvalid}
            className={cn(
              fieldControlClassName,
              "flex h-9 items-center justify-between gap-2 text-left",
              !displayLabel && "text-muted-foreground",
              className,
            )}
          >
            <span className="truncate">{displayLabel ?? placeholder}</span>
            <CalendarIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
          </button>
        }
      />

      <PopoverContent
        align="start"
        side="bottom"
        collisionPadding={16}
        className="w-auto max-w-[min(40rem,calc(100vw-2rem))] p-3"
      >
        <div className="mb-3 flex items-center justify-between gap-2">
          <p className="text-xs font-medium text-muted-foreground">
            {selectingEnd ? "Select end date" : "Select start date"}
          </p>
          <div className="flex items-center gap-1">
            <Button
              type="button"
              size="icon"
              variant="ghost"
              className="h-7 w-7"
              onClick={() => setLeftMonth(addMonths(leftMonth, -1))}
              aria-label="Previous month"
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              size="icon"
              variant="ghost"
              className="h-7 w-7"
              onClick={() => setLeftMonth(addMonths(leftMonth, 1))}
              aria-label="Next month"
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div className="flex flex-col gap-4 sm:flex-row sm:gap-6">
          <div className="w-[16.5rem]">
            <MonthGrid
              month={leftMonth}
              from={draftFrom}
              to={draftTo}
              hover={hoverDay}
              selectingEnd={selectingEnd}
              today={today}
              minDate={minDate}
              onSelectDay={handleSelectDay}
              onHoverDay={setHoverDay}
            />
            {withTime ? (
              <RangeTimeField
                label="From time"
                value={fromTime}
                disabled={!draftFrom}
                onChange={(next) => {
                  setFromTime(next);
                  if (draftFrom && draftTo) {
                    commit(draftFrom, draftTo, next, toTime);
                  }
                }}
              />
            ) : null}
          </div>
          <div className="w-[16.5rem]">
            <MonthGrid
              month={rightMonth}
              from={draftFrom}
              to={draftTo}
              hover={hoverDay}
              selectingEnd={selectingEnd}
              today={today}
              minDate={minDate}
              onSelectDay={handleSelectDay}
              onHoverDay={setHoverDay}
            />
            {withTime ? (
              <RangeTimeField
                label="To time"
                value={toTime}
                disabled={!draftTo}
                onChange={(next) => {
                  setToTime(next);
                  if (draftFrom && draftTo) {
                    commit(draftFrom, draftTo, fromTime, next);
                  }
                }}
              />
            ) : null}
          </div>
        </div>
        {sameDayReversed ? (
          <p className="mt-2 text-xs text-destructive">End time is before the start time.</p>
        ) : null}

        <div className="mt-3 flex items-center justify-between border-t border-border pt-2">
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-7 px-2 text-xs"
            onClick={() => {
              setDraftFrom(null);
              setDraftTo(null);
              setFromTime("");
              setToTime("");
              setSelectingEnd(false);
              onChange({ from: "", to: "" });
              setOpen(false);
            }}
          >
            Clear
          </Button>
          {draftFrom && draftTo ? (
            <p className="text-xs text-muted-foreground">
              {formatEndpointLabel(draftFrom, fromTime)} – {formatEndpointLabel(draftTo, toTime)}
            </p>
          ) : draftFrom ? (
            <p className="text-xs text-muted-foreground">{formatEndpointLabel(draftFrom, fromTime)} – …</p>
          ) : (
            <span />
          )}
          {withTime ? (
            <Button
              type="button"
              size="sm"
              className="h-7 px-2 text-xs"
              onClick={() => setOpen(false)}
            >
              Done
            </Button>
          ) : null}
        </div>
      </PopoverContent>
    </Popover>
  );
}
