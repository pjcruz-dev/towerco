import type { SelectChoice } from "@/modules/e-approval/field-options";

function normalizeLookupKey(key: string): string {
  return key.trim().toLowerCase();
}

function normalizeMappingValue(value: unknown): string {
  return value == null ? "" : String(value).trim();
}

/** Coerce persisted/API mapping rows to string values for controlled inputs. */
export function normalizeFieldMapMappings(
  mappings: Record<string, unknown> | null | undefined,
): Record<string, string> {
  const normalized: Record<string, string> = {};

  for (const [key, userId] of Object.entries(mappings ?? {})) {
    const trimmedKey = String(key ?? "").trim();
    if (trimmedKey === "") {
      continue;
    }

    normalized[trimmedKey] = normalizeMappingValue(userId);
  }

  return normalized;
}

/** Align mapping keys with dropdown option values (handles label vs code drift). */
export function canonicalizeFieldMapMappings(
  mappings: Record<string, unknown> | null | undefined,
  choices: SelectChoice[],
): Record<string, string> {
  const safeMappings = normalizeFieldMapMappings(mappings);

  if (choices.length === 0) {
    return safeMappings;
  }

  const valueByValue = new Map(choices.map((choice) => [normalizeLookupKey(choice.value), choice.value]));
  const valueByLabel = new Map(choices.map((choice) => [normalizeLookupKey(choice.label), choice.value]));

  const canonical: Record<string, string> = {};

  for (const [key, userId] of Object.entries(safeMappings)) {
    const trimmedKey = key.trim();
    if (trimmedKey === "") {
      continue;
    }

    const normalized = normalizeLookupKey(trimmedKey);
    const canonicalKey =
      valueByValue.get(normalized) ?? valueByLabel.get(normalized) ?? trimmedKey;
    const existing = canonical[canonicalKey] ?? "";

    canonical[canonicalKey] = userId !== "" ? userId : existing;
  }

  return canonical;
}

export function mergeFieldMapMappings(
  existing: Record<string, unknown> | null | undefined,
  choices: SelectChoice[],
): Record<string, string> {
  const canonicalExisting = canonicalizeFieldMapMappings(existing, choices);
  const merged: Record<string, string> = { ...canonicalExisting };

  for (const choice of choices) {
    if (!(choice.value in merged)) {
      merged[choice.value] = "";
    }
  }

  return merged;
}

export function nextUnmappedFieldValue(
  choices: SelectChoice[],
  mappings: Record<string, string>,
): string {
  const unused = choices.find((choice) => !(choice.value in mappings));
  return unused?.value ?? "";
}
