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

type Props = {
  value: string;
  onChange: (value: string) => void;
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

function sameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
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

export function DatePicker({
  value,
  onChange,
  disabled,
  readOnly,
  placeholder = "Select date",
  className,
  id,
  "aria-invalid": ariaInvalid,
}: Props) {
  const selected = useMemo(() => parseIsoDate(value), [value]);
  const [open, setOpen] = useState(false);
  const [visibleMonth, setVisibleMonth] = useState(() => startOfMonth(selected ?? new Date()));

  const cells = useMemo(() => buildMonthCells(visibleMonth), [visibleMonth]);
  const today = useMemo(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
  }, []);

  const locked = disabled || readOnly;

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        if (locked) {
          return;
        }
        setOpen(next);
        if (next) {
          setVisibleMonth(startOfMonth(selected ?? new Date()));
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
              !selected && "text-muted-foreground",
              className,
            )}
          >
            <span className="truncate">{selected ? formatDisplay(selected) : placeholder}</span>
            <CalendarIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
          </button>
        }
      />

      <PopoverContent align="start" side="bottom" className="w-[18.5rem] p-3">
        <div className="flex items-center justify-between gap-2">
          <p className="text-sm font-medium text-foreground">
            {MONTHS[visibleMonth.getMonth()]} {visibleMonth.getFullYear()}
          </p>
          <div className="flex items-center gap-1">
            <Button
              type="button"
              size="icon"
              variant="ghost"
              className="h-7 w-7"
              onClick={() =>
                setVisibleMonth(new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1))
              }
              aria-label="Previous month"
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              size="icon"
              variant="ghost"
              className="h-7 w-7"
              onClick={() =>
                setVisibleMonth(new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1))
              }
              aria-label="Next month"
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div className="mt-3 grid grid-cols-7 gap-1">
          {WEEKDAYS.map((day) => (
            <div key={day} className="py-1 text-center text-[11px] font-medium text-muted-foreground">
              {day}
            </div>
          ))}
          {cells.map((cell) => {
            const inMonth = cell.getMonth() === visibleMonth.getMonth();
            const isSelected = selected ? sameDay(cell, selected) : false;
            const isToday = sameDay(cell, today);

            return (
              <button
                key={toIsoDate(cell)}
                type="button"
                onClick={() => {
                  onChange(toIsoDate(cell));
                  setOpen(false);
                }}
                className={cn(
                  "flex h-8 w-8 items-center justify-center rounded-md text-sm transition-colors",
                  "hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                  !inMonth && "text-muted-foreground/50",
                  inMonth && !isSelected && "text-foreground",
                  isToday && !isSelected && "ring-1 ring-border",
                  isSelected && "bg-primary text-primary-foreground hover:bg-primary/90",
                )}
              >
                {cell.getDate()}
              </button>
            );
          })}
        </div>

        <div className="mt-3 flex items-center justify-between border-t border-border pt-2">
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-7 px-2 text-xs"
            onClick={() => {
              onChange("");
              setOpen(false);
            }}
          >
            Clear
          </Button>
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-7 px-2 text-xs"
            onClick={() => {
              onChange(toIsoDate(today));
              setVisibleMonth(startOfMonth(today));
              setOpen(false);
            }}
          >
            Today
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}
