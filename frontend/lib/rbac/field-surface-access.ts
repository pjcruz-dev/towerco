import type { FieldAccessLevel } from "@/lib/api/modules/admin-roles-api";

/** Metacoresoft field-level security. Default when a role has no row is full. */
export const FIELD_ACCESS_LEVELS: Array<{ value: FieldAccessLevel; label: string }> = [
  { value: "full", label: "Full access" },
  { value: "view", label: "View only (read-only)" },
  { value: "table_record", label: "Table + Record view only" },
  { value: "record", label: "Record view only" },
  { value: "form", label: "Form only (fill-in)" },
  { value: "hide", label: "Hide from this role" },
];

export type FieldSurface = "form" | "table" | "record";

/**
 * Where a level is visible.
 * full / view: form, table, and record.
 * table_record: table and record.
 * record: record only.
 * form: the fill-in form only.
 * hide: nowhere.
 */
export function fieldVisibleOnSurface(level: FieldAccessLevel, surface: FieldSurface): boolean {
  switch (level) {
    case "hide":
      return false;
    case "form":
      return surface === "form";
    case "record":
      return surface === "record";
    case "table_record":
      return surface === "table" || surface === "record";
    case "view":
    case "full":
      return true;
    default:
      return true;
  }
}

/** Fill-in is allowed for full access and form-only. Every other level is read-only. */
export function fieldEditableOnForm(level: FieldAccessLevel): boolean {
  return level === "full" || level === "form";
}

/** Form column on Field Level Security. */
export type FormSurfaceChoice = "full" | "view" | "form" | "hide";

/** Table and record columns. "full" means the surface is shown. */
export type ListSurfaceChoice = "full" | "hide";

export const FORM_SURFACE_OPTIONS: Array<{ value: FormSurfaceChoice; label: string }> = [
  { value: "full", label: "Full access" },
  { value: "view", label: "View only" },
  { value: "form", label: "Form only" },
  { value: "hide", label: "Hide" },
];

export const LIST_SURFACE_OPTIONS: Array<{ value: ListSurfaceChoice; label: string }> = [
  { value: "full", label: "Full access" },
  { value: "hide", label: "Hide" },
];

/** Split a stored level into the three Field Level Security columns. */
export function surfacesFromLevel(level: FieldAccessLevel): {
  form: FormSurfaceChoice;
  table: ListSurfaceChoice;
  record: ListSurfaceChoice;
} {
  switch (level) {
    case "hide":
      return { form: "hide", table: "hide", record: "hide" };
    case "form":
      return { form: "form", table: "hide", record: "hide" };
    case "record":
      return { form: "hide", table: "hide", record: "full" };
    case "table_record":
      return { form: "hide", table: "full", record: "full" };
    case "view":
      return { form: "view", table: "full", record: "full" };
    default:
      return { form: "full", table: "full", record: "full" };
  }
}

/**
 * Fold the three columns back into the stored level.
 * Table-only is stored as table and record, because that is the level the API enforces.
 */
export function levelFromSurfaces(
  form: FormSurfaceChoice,
  table: ListSurfaceChoice,
  record: ListSurfaceChoice,
): FieldAccessLevel {
  const tableOn = table === "full";
  const recordOn = record === "full";

  if (form === "hide" && !tableOn && !recordOn) return "hide";
  if (form === "form" && !tableOn && !recordOn) return "form";
  if (form === "hide" && !tableOn && recordOn) return "record";
  if (form === "hide" && tableOn) return "table_record";
  if (form === "view") return "view";
  if (form === "full" && !tableOn && !recordOn) return "form";
  return "full";
}
