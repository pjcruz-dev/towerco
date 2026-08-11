import {
  getMasterDataLookupKey,
  parseSelectChoices,
  type SelectChoice,
  type SelectChoiceCompanionInput,
} from "@/modules/e-approval/field-options";
import {
  companionSizeHasPartial,
  companionSizeIsComplete,
  formatCompanionSizeDisplay,
} from "@/modules/e-approval/field-companion-size";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type CheckboxCompanionMap = Record<string, Record<string, string>>;

export type CheckboxFieldState = {
  selected: string[];
  companions: CheckboxCompanionMap;
};

/** Multi-select checklist when choices or master-data lookup are configured. */
export function isCheckboxMulti(field: EApprovalFormFieldInput): boolean {
  if (getMasterDataLookupKey(field)) {
    return true;
  }

  return parseSelectChoices(field).length > 0;
}

export function isCheckboxTruthy(value: string): boolean {
  const normalized = value.trim().toLowerCase();

  return normalized === "true" || normalized === "1" || normalized === "yes" || normalized === "on";
}

function uniqueStrings(values: string[]): string[] {
  const seen = new Set<string>();
  const out: string[] = [];
  for (const raw of values) {
    const trimmed = raw.trim();
    if (trimmed === "" || seen.has(trimmed)) {
      continue;
    }
    seen.add(trimmed);
    out.push(trimmed);
  }

  return out;
}

function parseCsvSelected(value: string): string[] {
  return uniqueStrings(value.split(","));
}

function normalizeCompanions(raw: unknown): CheckboxCompanionMap {
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return {};
  }

  const out: CheckboxCompanionMap = {};
  for (const [choiceValue, companionRaw] of Object.entries(raw as Record<string, unknown>)) {
    const choiceKey = choiceValue.trim();
    if (choiceKey === "" || !companionRaw || typeof companionRaw !== "object" || Array.isArray(companionRaw)) {
      continue;
    }
    const fields: Record<string, string> = {};
    for (const [inputKey, inputValue] of Object.entries(companionRaw as Record<string, unknown>)) {
      const key = inputKey.trim();
      if (key === "") {
        continue;
      }
      fields[key] = inputValue == null ? "" : String(inputValue);
    }
    out[choiceKey] = fields;
  }

  return out;
}

/** Parse CSV (`a,b`) or JSON (`{ selected, companions }`) checkbox payloads. */
export function parseCheckboxState(value: string): CheckboxFieldState {
  const trimmed = value.trim();
  if (trimmed === "") {
    return { selected: [], companions: {} };
  }

  if (trimmed.startsWith("{")) {
    try {
      const decoded = JSON.parse(trimmed) as unknown;
      if (decoded && typeof decoded === "object" && !Array.isArray(decoded)) {
        const record = decoded as Record<string, unknown>;
        const selectedRaw = record.selected;
        const selected = Array.isArray(selectedRaw)
          ? uniqueStrings(selectedRaw.map((item) => String(item ?? "")))
          : [];

        return {
          selected,
          companions: normalizeCompanions(record.companions),
        };
      }
    } catch {
      /* fall through to CSV */
    }
  }

  return { selected: parseCsvSelected(trimmed), companions: {} };
}

/** Split selected choice values from CSV or JSON payloads. */
export function parseCheckboxValues(value: string): string[] {
  return parseCheckboxState(value).selected;
}

export function serializeCheckboxValues(values: string[]): string {
  return uniqueStrings(values).join(",");
}

/** Prefer CSV when no companion payload is needed; JSON otherwise. */
export function serializeCheckboxState(state: CheckboxFieldState): string {
  const selected = uniqueStrings(state.selected);
  const companions: CheckboxCompanionMap = {};
  for (const choice of selected) {
    const fields = state.companions[choice];
    if (!fields) {
      continue;
    }
    const cleaned: Record<string, string> = {};
    for (const [key, value] of Object.entries(fields)) {
      const trimmedKey = key.trim();
      if (trimmedKey === "" || value.trim() === "") {
        continue;
      }
      cleaned[trimmedKey] = value;
    }
    if (Object.keys(cleaned).length > 0) {
      companions[choice] = cleaned;
    }
  }

  if (Object.keys(companions).length === 0) {
    return selected.join(",");
  }

  return JSON.stringify({ selected, companions });
}

export function toggleCheckboxValue(current: string, choiceValue: string, checked: boolean): string {
  const state = parseCheckboxState(current);
  const key = choiceValue.trim();
  if (key === "") {
    return serializeCheckboxState(state);
  }

  const selected = new Set(state.selected);
  if (checked) {
    selected.add(key);
  } else {
    selected.delete(key);
    delete state.companions[key];
  }

  return serializeCheckboxState({
    selected: [...selected],
    companions: state.companions,
  });
}

export function setCheckboxCompanionValue(
  current: string,
  choiceValue: string,
  inputKey: string,
  inputValue: string,
): string {
  const state = parseCheckboxState(current);
  const choice = choiceValue.trim();
  const key = inputKey.trim();
  if (choice === "" || key === "") {
    return serializeCheckboxState(state);
  }

  const selected = new Set(state.selected);
  selected.add(choice);
  const companions = { ...state.companions };
  companions[choice] = { ...(companions[choice] ?? {}), [key]: inputValue };

  return serializeCheckboxState({
    selected: [...selected],
    companions,
  });
}

export function getCheckboxCompanionValue(
  value: string,
  choiceValue: string,
  inputKey: string,
): string {
  return parseCheckboxState(value).companions[choiceValue]?.[inputKey] ?? "";
}

export function choiceHasCompanionInputs(choice: SelectChoice): boolean {
  return Array.isArray(choice.inputs) && choice.inputs.length > 0;
}

/** Resolve stored values to human labels, including companion suffixes when present. */
export function resolveCheckboxDisplayLabels(value: string, choices: SelectChoice[]): string {
  const state = parseCheckboxState(value);
  if (state.selected.length === 0) {
    return "";
  }

  const byValue = new Map(choices.map((c) => [c.value, c]));

  return state.selected
    .map((selectedValue) => {
      const choice = byValue.get(selectedValue);
      const label = choice?.label || selectedValue;
      const inputs = choice?.inputs ?? [];
      if (inputs.length === 0) {
        return label;
      }

      const parts = inputs
        .map((input) => formatCompanionPart(input, state.companions[selectedValue]?.[input.key] ?? ""))
        .filter((part) => part !== "");

      return parts.length > 0 ? `${label} — ${parts.join(", ")}` : label;
    })
    .join("; ");
}

function formatCompanionPart(input: SelectChoiceCompanionInput, raw: string): string {
  const trimmed = raw.trim();
  if (trimmed === "") {
    return "";
  }
  if (input.type === "size") {
    const size = formatCompanionSizeDisplay(trimmed);
    if (size === "") {
      return "";
    }
    const suffix = input.suffix?.trim() ?? "";

    return suffix ? `${size} ${suffix}` : size;
  }
  const suffix = input.suffix?.trim() ?? "";

  return suffix ? `${trimmed} ${suffix}` : trimmed;
}

export function validateCheckboxCompanions(
  value: string,
  choices: SelectChoice[],
  fieldLabel: string,
): string | null {
  const state = parseCheckboxState(value);
  const byValue = new Map(choices.map((c) => [c.value, c]));

  for (const selectedValue of state.selected) {
    const choice = byValue.get(selectedValue);
    if (!choice?.inputs?.length) {
      continue;
    }

    for (const input of choice.inputs) {
      const raw = (state.companions[selectedValue]?.[input.key] ?? "").trim();
      if (input.type === "size") {
        if (input.required && !companionSizeIsComplete(raw)) {
          return `${fieldLabel}: enter size or NA for ${choice.label}.`;
        }
        if (raw !== "" && companionSizeHasPartial(raw)) {
          return `${fieldLabel}: enter both width and height for ${choice.label}, or mark NA.`;
        }
        continue;
      }
      if (input.required && raw === "") {
        const hint = input.suffix?.trim() || input.placeholder?.trim() || input.key;

        return `${fieldLabel}: enter a value for ${choice.label} (${hint}).`;
      }
      if (raw !== "" && input.type === "number" && !Number.isFinite(Number(raw))) {
        return `${fieldLabel}: ${choice.label} must be a valid number.`;
      }
    }
  }

  return null;
}
