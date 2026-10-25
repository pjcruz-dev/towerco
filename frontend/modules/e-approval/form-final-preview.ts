export type FormFinalPreviewSettings = {
  enabled: boolean;
};

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

export function formFinalPreviewFromMetadata(metadata: unknown): FormFinalPreviewSettings {
  const root = asRecord(metadata);
  const raw = asRecord(root?.final_preview);

  return { enabled: raw?.enabled === true };
}

export function mergeFormFinalPreviewIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormFinalPreviewSettings,
): Record<string, unknown> {
  const next = { ...metadata };
  if (!settings.enabled) {
    delete next.final_preview;
    return next;
  }

  next.final_preview = { enabled: true, step_order: 3 };
  return next;
}
