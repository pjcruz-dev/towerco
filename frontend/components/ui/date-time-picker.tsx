"use client";

import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

type Props = {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  readOnly?: boolean;
  className?: string;
  id?: string;
  "aria-invalid"?: boolean;
};

/** Parses `yyyy-MM-ddTHH:mm` (datetime-local) into date + time parts. */
function splitDateTime(value: string): { date: string; time: string } {
  const trimmed = value.trim();
  if (!trimmed) {
    return { date: "", time: "" };
  }
  const [datePart, timePart = ""] = trimmed.split("T");
  const time = timePart.slice(0, 5);
  return {
    date: /^\d{4}-\d{2}-\d{2}$/.test(datePart ?? "") ? (datePart ?? "") : "",
    time: /^\d{2}:\d{2}$/.test(time) ? time : "",
  };
}

function joinDateTime(date: string, time: string): string {
  if (!date) {
    return "";
  }
  return `${date}T${time || "00:00"}`;
}

export function DateTimePicker({
  value,
  onChange,
  disabled,
  readOnly,
  className,
  id,
  "aria-invalid": ariaInvalid,
}: Props) {
  const { date, time } = splitDateTime(value);
  const locked = disabled || readOnly;

  return (
    <div className={cn("flex min-w-0 flex-wrap items-center gap-2", className)}>
      <DatePicker
        id={id}
        value={date}
        onChange={(nextDate) => onChange(joinDateTime(nextDate, time))}
        disabled={locked}
        readOnly={readOnly}
        aria-invalid={ariaInvalid}
        className="min-w-[10rem] flex-1"
        placeholder="Select date"
      />
      <Input
        type="time"
        value={time}
        disabled={locked}
        readOnly={readOnly}
        aria-invalid={ariaInvalid}
        aria-label="Time"
        className="h-9 w-[8.5rem] shrink-0"
        onChange={(event) => onChange(joinDateTime(date || splitDateTime(value).date, event.target.value))}
      />
    </div>
  );
}
