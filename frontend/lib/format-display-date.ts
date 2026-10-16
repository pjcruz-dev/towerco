const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/;
const DATE_TIME =
  /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/;

const DATE_OPTIONS: Intl.DateTimeFormatOptions = {
  year: "numeric",
  month: "short",
  day: "numeric",
};

const DATE_TIME_OPTIONS: Intl.DateTimeFormatOptions = {
  ...DATE_OPTIONS,
  hour: "numeric",
  minute: "2-digit",
};

function calendarDate(year: number, month: number, day: number): string {
  return new Date(year, month - 1, day).toLocaleDateString(undefined, DATE_OPTIONS);
}

/**
 * Turns a stored date or timestamp into a local, readable value.
 * Date-only strings stay on that calendar day. Timestamps with an offset
 * convert to the viewer's local time. Returns null when the value is not a date.
 */
export function formatDisplayDate(value: unknown, kind?: "date" | "datetime"): string | null {
  if (value == null || value === "") return null;
  const raw = typeof value === "string" ? value.trim() : value instanceof Date ? value.toISOString() : null;
  if (raw == null) return null;

  const dateOnly = DATE_ONLY.exec(raw);
  if (dateOnly) {
    return calendarDate(Number(dateOnly[1]), Number(dateOnly[2]), Number(dateOnly[3]));
  }

  const dateTime = DATE_TIME.exec(raw);
  if (!dateTime && kind !== "date" && kind !== "datetime") return null;
  if (!dateTime) return null;

  const year = Number(dateTime[1]);
  const month = Number(dateTime[2]);
  const day = Number(dateTime[3]);
  if (kind === "date") return calendarDate(year, month, day);

  const hasOffset = Boolean(dateTime[7]);
  const instant = hasOffset
    ? new Date(raw)
    : new Date(year, month - 1, day, Number(dateTime[4]), Number(dateTime[5]), Number(dateTime[6] ?? "0"));
  if (Number.isNaN(instant.getTime())) return null;
  return instant.toLocaleString(undefined, DATE_TIME_OPTIONS);
}
