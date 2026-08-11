import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

/** Matches `e_approval_form_fields.name` varchar(100). */
export const E_APPROVAL_FIELD_API_KEY_MAX_LENGTH = 100;

/** Slugify a label into a stable API key segment (snake_case). */
export function slugifyLabelToApiKey(label: string): string {
  const slug = label
    .trim()
    .toLowerCase()
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .replace(/_+/g, "_");

  if (!slug) {
    return "field";
  }

  if (/^\d/.test(slug)) {
    return `field_${slug}`;
  }

  return slug;
}

function truncateApiKey(key: string, maxLength = E_APPROVAL_FIELD_API_KEY_MAX_LENGTH): string {
  if (key.length <= maxLength) {
    return key;
  }

  return key.slice(0, maxLength).replace(/_+$/g, "") || key.slice(0, maxLength);
}

export function suggestApiKeyFromLabel(label: string, taken: Set<string>): string {
  const base = truncateApiKey(slugifyLabelToApiKey(label), E_APPROVAL_FIELD_API_KEY_MAX_LENGTH - 4);
  if (!taken.has(base)) {
    return base;
  }

  let n = 2;
  while (true) {
    const suffix = `_${n}`;
    const candidate = `${truncateApiKey(base, E_APPROVAL_FIELD_API_KEY_MAX_LENGTH - suffix.length)}${suffix}`;
    if (!taken.has(candidate)) {
      return candidate;
    }
    n += 1;
  }
}

export function collectFieldApiKeys(fields: EApprovalFormFieldInput[], exceptIndex?: number): Set<string> {
  const keys = new Set<string>();
  fields.forEach((f, i) => {
    if (exceptIndex !== undefined && i === exceptIndex) {
      return;
    }
    const name = f.name?.trim();
    if (name) {
      keys.add(name);
    }
  });
  return keys;
}

/** Lock API keys after publish or once submissions exist (existing persisted fields stay named). */
export function isFormApiKeysLocked(status: string, submissionsCount: number): boolean {
  return status === "published" || submissionsCount > 0;
}

export function isFieldApiKeyEditable(
  locked: boolean,
  field: EApprovalFormFieldInput,
): boolean {
  if (!locked) {
    return true;
  }
  return !field.id;
}
