import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function normalizeOptions(field: EApprovalFormFieldInput): Record<string, unknown> {
  if (field.options && typeof field.options === "object" && !Array.isArray(field.options)) {
    return { ...(field.options as Record<string, unknown>) };
  }

  return {};
}

export function parseRatingMaxStars(field: EApprovalFormFieldInput): number {
  const raw = normalizeOptions(field).max_stars;
  const n = typeof raw === "number" ? raw : Number(raw);
  if (!Number.isFinite(n)) {
    return 5;
  }

  return Math.max(1, Math.min(10, Math.round(n)));
}

export function patchRatingOptions(field: EApprovalFormFieldInput, maxStars: number): Record<string, unknown> {
  const opts = normalizeOptions(field);

  return { ...opts, max_stars: Math.max(1, Math.min(10, Math.round(maxStars))) };
}

export function parseTagSuggestions(field: EApprovalFormFieldInput): string[] {
  const raw = normalizeOptions(field).tag_suggestions;
  if (!Array.isArray(raw)) {
    return [];
  }

  return raw.map((t) => String(t).trim()).filter(Boolean);
}

export function patchTagSuggestions(field: EApprovalFormFieldInput, suggestions: string[]): Record<string, unknown> {
  const opts = normalizeOptions(field);
  const cleaned = suggestions.map((s) => s.trim()).filter(Boolean);

  return { ...opts, tag_suggestions: cleaned };
}

export function parseTagsAllowCustom(field: EApprovalFormFieldInput): boolean {
  const raw = normalizeOptions(field).allow_custom;
  return raw !== false;
}

export function parseLocationAllowGeolocation(field: EApprovalFormFieldInput): boolean {
  const raw = normalizeOptions(field).allow_geolocation;
  return raw !== false;
}

export type EApprovalLocationValue = {
  lat: string;
  lng: string;
  label?: string;
};

export function parseLocationValue(raw: string): EApprovalLocationValue | null {
  const trimmed = raw.trim();
  if (!trimmed) {
    return null;
  }

  try {
    const parsed = JSON.parse(trimmed) as Record<string, unknown>;
    const lat = String(parsed.lat ?? "").trim();
    const lng = String(parsed.lng ?? "").trim();
    if (lat && lng) {
      return {
        lat,
        lng,
        label: typeof parsed.label === "string" ? parsed.label.trim() : undefined,
      };
    }
  } catch {
    // fall through
  }

  const match = /^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/.exec(trimmed);
  if (match) {
    return { lat: match[1]!, lng: match[2]! };
  }

  return null;
}

export function serializeLocationValue(loc: EApprovalLocationValue): string {
  return JSON.stringify({
    lat: loc.lat,
    lng: loc.lng,
    ...(loc.label ? { label: loc.label } : {}),
  });
}

export type EApprovalDateRangeValue = {
  from: string;
  to: string;
};

const ISO_DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export function isIsoDateString(value: string): boolean {
  if (!ISO_DATE_RE.test(value)) {
    return false;
  }
  const parsed = new Date(`${value}T00:00:00`);
  return !Number.isNaN(parsed.getTime());
}

export function parseDateRangeValue(raw: string): EApprovalDateRangeValue {
  const trimmed = raw.trim();
  if (!trimmed) {
    return { from: "", to: "" };
  }

  try {
    const parsed = JSON.parse(trimmed) as Record<string, unknown>;
    return {
      from: String(parsed.from ?? "").trim(),
      to: String(parsed.to ?? "").trim(),
    };
  } catch {
    // fall through
  }

  // Legacy / accidental pipe format
  if (trimmed.includes("|")) {
    const [from = "", to = ""] = trimmed.split("|", 2);
    return { from: from.trim(), to: to.trim() };
  }

  return { from: "", to: "" };
}

export function serializeDateRangeValue(range: EApprovalDateRangeValue): string {
  const from = range.from.trim();
  const to = range.to.trim();
  if (!from && !to) {
    return "";
  }

  return JSON.stringify({ from, to });
}

export function dateRangeHasValue(raw: string): boolean {
  const { from, to } = parseDateRangeValue(raw);
  return from !== "" || to !== "";
}

export function dateRangeIsComplete(raw: string): boolean {
  const { from, to } = parseDateRangeValue(raw);
  return from !== "" && to !== "";
}

/** Human-readable date range for review, detail, and print (e.g. 2026-09-22 – 2026-10-20). */
export function formatDateRangeDisplay(raw: string, empty = "—"): string {
  const trimmed = raw.trim();
  if (!trimmed) {
    return empty;
  }

  const { from, to } = parseDateRangeValue(trimmed);
  if (!from && !to) {
    return trimmed;
  }

  const fromDisplay = from || empty;
  const toDisplay = to || empty;
  return `${fromDisplay} – ${toDisplay}`;
}

export function validateDateRangeValue(raw: string, required: boolean, label: string): string | null {
  const { from, to } = parseDateRangeValue(raw);
  const hasAny = from !== "" || to !== "";

  if (required && (!from || !to)) {
    return `${label} requires a start and end date.`;
  }

  if (!hasAny) {
    return null;
  }

  if ((from && !to) || (!from && to)) {
    return `${label} requires both start and end dates.`;
  }

  if (!isIsoDateString(from) || !isIsoDateString(to)) {
    return `${label} must use valid dates.`;
  }

  if (to < from) {
    return `${label}: end date must be on or after the start date.`;
  }

  return null;
}

export function parseTagsValue(raw: string): string[] {
  const trimmed = raw.trim();
  if (!trimmed) {
    return [];
  }

  if (trimmed.startsWith("[")) {
    try {
      const parsed = JSON.parse(trimmed) as unknown;
      if (Array.isArray(parsed)) {
        return parsed.map((t) => String(t).trim()).filter(Boolean);
      }
    } catch {
      // fall through
    }
  }

  return trimmed
    .split(",")
    .map((t) => t.trim())
    .filter(Boolean);
}

export function serializeTagsValue(tags: string[]): string {
  return tags.join(", ");
}

export function isSignatureDataUrl(value: string | null | undefined): boolean {
  return Boolean(value?.trim().startsWith("data:image/"));
}
