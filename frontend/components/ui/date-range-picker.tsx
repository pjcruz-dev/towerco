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
  "aria-invalid"?: boolean;
};

function parseIsoDate(value: string): Date | null {
  const trimmed = value.trim();
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
  onSelectDay,
  onHoverDay,
}: {
  month: Date;
  from: Date | null;
  to: Date | null;
  hover: Date | null;
  selectingEnd: boolean;
  today: Date;
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

          return (
            <button
              key={`${month.getFullYear()}-${month.getMonth()}-${toIsoDate(cell)}`}
              type="button"
              onClick={() => onSelectDay(cell)}
              onMouseEnter={() => onHoverDay(cell)}
              onMouseLeave={() => onHoverDay(null)}
              className={cn(
                "relative flex h-8 w-full items-center justify-center text-sm transition-colors",
                "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:z-10",
                !inMonth && "text-muted-foreground/40",
                inMonth && !isEndpoint && "text-foreground",
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
  "aria-invalid": ariaInvalid,
}: Props) {
  const fromDate = useMemo(() => parseIsoDate(value.from), [value.from]);
  const toDate = useMemo(() => parseIsoDate(value.to), [value.to]);
  const [open, setOpen] = useState(false);
  const [draftFrom, setDraftFrom] = useState<Date | null>(fromDate);
  const [draftTo, setDraftTo] = useState<Date | null>(toDate);
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
    if (fromDate && toDate) {
      return `${formatDisplay(fromDate)} – ${formatDisplay(toDate)}`;
    }
    if (fromDate) {
      return `${formatDisplay(fromDate)} – …`;
    }
    return null;
  }, [fromDate, toDate]);

  const resetDraftFromValue = () => {
    setDraftFrom(fromDate);
    setDraftTo(toDate);
    setSelectingEnd(false);
    setHoverDay(null);
    setLeftMonth(startOfMonth(fromDate ?? new Date()));
  };

  const handleSelectDay = (day: Date) => {
    if (!selectingEnd || !draftFrom) {
      setDraftFrom(day);
      setDraftTo(null);
      setSelectingEnd(true);
      return;
    }

    let nextFrom = draftFrom;
    let nextTo = day;
    if (isBeforeDay(day, draftFrom)) {
      nextFrom = day;
      nextTo = draftFrom;
    }

    setDraftFrom(nextFrom);
    setDraftTo(nextTo);
    setSelectingEnd(false);
    setHoverDay(null);
    onChange({ from: toIsoDate(nextFrom), to: toIsoDate(nextTo) });
    setOpen(false);
  };

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

      <PopoverContent align="start" side="bottom" className="w-auto max-w-[calc(100vw-2rem)] p-3">
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
          <MonthGrid
            month={leftMonth}
            from={draftFrom}
            to={draftTo}
            hover={hoverDay}
            selectingEnd={selectingEnd}
            today={today}
            onSelectDay={handleSelectDay}
            onHoverDay={setHoverDay}
          />
          <MonthGrid
            month={rightMonth}
            from={draftFrom}
            to={draftTo}
            hover={hoverDay}
            selectingEnd={selectingEnd}
            today={today}
            onSelectDay={handleSelectDay}
            onHoverDay={setHoverDay}
          />
        </div>

        <div className="mt-3 flex items-center justify-between border-t border-border pt-2">
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-7 px-2 text-xs"
            onClick={() => {
              setDraftFrom(null);
              setDraftTo(null);
              setSelectingEnd(false);
              onChange({ from: "", to: "" });
              setOpen(false);
            }}
          >
            Clear
          </Button>
          {draftFrom && draftTo ? (
            <p className="text-xs text-muted-foreground">
              {formatDisplay(draftFrom)} – {formatDisplay(draftTo)}
            </p>
          ) : draftFrom ? (
            <p className="text-xs text-muted-foreground">{formatDisplay(draftFrom)} – …</p>
          ) : (
            <span />
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
