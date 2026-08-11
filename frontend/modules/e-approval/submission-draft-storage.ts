const LOCAL_PREFIX = "e-approval:compose-local:";

export type EApprovalComposeLocalDraft = {
  values: Record<string, string>;
  parentSubmissionId?: string | null;
  savedAt: string;
};

export function localDraftKey(formId: string): string {
  return `${LOCAL_PREFIX}${formId}`;
}

export function readLocalComposeDraft(formId: string): EApprovalComposeLocalDraft | null {
  if (typeof window === "undefined") {
    return null;
  }
  try {
    const raw = window.localStorage.getItem(localDraftKey(formId));
    if (!raw) {
      return null;
    }
    const parsed = JSON.parse(raw) as EApprovalComposeLocalDraft;
    if (!parsed?.values || typeof parsed.values !== "object") {
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}

export function writeLocalComposeDraft(
  formId: string,
  values: Record<string, string>,
  parentSubmissionId?: string | null,
): void {
  if (typeof window === "undefined") {
    return;
  }
  const payload: EApprovalComposeLocalDraft = {
    values,
    parentSubmissionId,
    savedAt: new Date().toISOString(),
  };
  window.localStorage.setItem(localDraftKey(formId), JSON.stringify(payload));
}

export function clearLocalComposeDraft(formId: string): void {
  if (typeof window === "undefined") {
    return;
  }
  window.localStorage.removeItem(localDraftKey(formId));
}

export function submissionDetailToValues(
  rows: { field_name: string | null; value: string | null }[],
): Record<string, string> {
  const values: Record<string, string> = {};
  for (const row of rows) {
    if (row.field_name && row.value != null) {
      values[row.field_name] = row.value;
    }
  }
  return values;
}
