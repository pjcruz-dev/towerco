/** Dropdown choice catalog for Dynamic Entity select / multiselect fields. */

export type DynSelectBadgeStyle =
  | "none"
  | "info"
  | "warning"
  | "primary"
  | "success"
  | "danger";

export type DynSelectChoice = {
  value: string;
  label: string;
  badge: DynSelectBadgeStyle;
};

export type DynSelectOptionsConfig = {
  choices: DynSelectChoice[];
  default: string | null;
};

export const DYN_SELECT_BADGE_OPTIONS: Array<{ value: DynSelectBadgeStyle; label: string }> = [
  { value: "none", label: "None" },
  { value: "info", label: "Info (Cyan)" },
  { value: "warning", label: "Warning (Yellow)" },
  { value: "primary", label: "Primary (Blue)" },
  { value: "success", label: "Success (Green)" },
  { value: "danger", label: "Danger (Red)" },
];

export function dynSelectBadgeClass(badge: DynSelectBadgeStyle | string | null | undefined): string {
  switch (badge) {
    case "info":
      return "bg-sky-500/15 text-sky-800 dark:text-sky-300";
    case "warning":
      return "bg-amber-500/15 text-amber-800 dark:text-amber-300";
    case "primary":
      return "bg-blue-500/15 text-blue-800 dark:text-blue-300";
    case "success":
      return "bg-emerald-500/15 text-emerald-800 dark:text-emerald-300";
    case "danger":
      return "bg-red-500/15 text-red-700 dark:text-red-300";
    default:
      return "bg-slate-500/15 text-slate-700 dark:text-slate-300";
  }
}

function asBadge(raw: unknown): DynSelectBadgeStyle {
  const v = String(raw ?? "none").toLowerCase();
  if (v === "info" || v === "warning" || v === "primary" || v === "success" || v === "danger") {
    return v;
  }
  return "none";
}

function choiceFromUnknown(raw: unknown): DynSelectChoice | null {
  if (typeof raw === "string") {
    const value = raw.trim();
    if (!value) return null;
    return { value, label: value, badge: "none" };
  }
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) return null;
  const row = raw as Record<string, unknown>;
  const label = String(row.label ?? row.value ?? "").trim();
  const value = String(row.value ?? row.label ?? "").trim() || label;
  if (!value && !label) return null;
  return {
    value: value || label,
    label: label || value,
    badge: asBadge(row.badge ?? row.badge_style ?? row.style),
  };
}

/** Parse field.options / options_json into a rich choice catalog. */
export function parseDynSelectOptions(options: unknown): DynSelectOptionsConfig {
  if (Array.isArray(options)) {
    return {
      choices: options.map(choiceFromUnknown).filter((c): c is DynSelectChoice => c !== null),
      default: null,
    };
  }
  if (!options || typeof options !== "object") {
    return { choices: [], default: null };
  }
  const o = options as Record<string, unknown>;
  const rawChoices = Array.isArray(o.choices) ? o.choices : [];
  const choices = rawChoices.map(choiceFromUnknown).filter((c): c is DynSelectChoice => c !== null);
  const defaultRaw = o.default ?? o.default_value ?? o.defaultChoice;
  const defaultValue =
    typeof defaultRaw === "string" && defaultRaw.trim() !== "" ? defaultRaw.trim() : null;
  return { choices, default: defaultValue };
}

/** Persist rich select options (Metacoresoft-parity shape). */
export function serializeDynSelectOptions(config: DynSelectOptionsConfig): Record<string, unknown> | null {
  const choices = config.choices
    .map((c) => ({
      value: c.value.trim() || c.label.trim(),
      label: c.label.trim() || c.value.trim(),
      badge: c.badge === "none" ? undefined : c.badge,
    }))
    .filter((c) => c.value !== "");
  if (choices.length === 0) return null;
  const payload: Record<string, unknown> = {
    choices: choices.map((c) =>
      c.badge
        ? { value: c.value, label: c.label, badge: c.badge }
        : { value: c.value, label: c.label },
    ),
  };
  if (config.default && choices.some((c) => c.value === config.default)) {
    payload.default = config.default;
  }
  return payload;
}

export function dynSelectChoiceValues(options: unknown): string[] {
  return parseDynSelectOptions(options).choices.map((c) => c.value);
}

export function dynSelectChoiceLabel(options: unknown, stored: string): string {
  const hit = parseDynSelectOptions(options).choices.find(
    (c) => c.value === stored || c.label === stored,
  );
  return hit?.label ?? stored;
}

export function dynSelectChoiceBadgeClass(options: unknown, stored: string): string | null {
  const hit = parseDynSelectOptions(options).choices.find(
    (c) => c.value === stored || c.label === stored,
  );
  if (!hit || hit.badge === "none") return null;
  return dynSelectBadgeClass(hit.badge);
}

export function emptyDynSelectChoice(): DynSelectChoice {
  return { value: "", label: "", badge: "none" };
}
