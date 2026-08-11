export type FormOutboundEditorSettings = {
  emailPackageOnApprove: boolean;
};

export const DEFAULT_FORM_OUTBOUND_EDITOR_SETTINGS: FormOutboundEditorSettings = {
  emailPackageOnApprove: false,
};

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

export function parseFormOutboundConfig(metadata: unknown): FormOutboundEditorSettings {
  const root = asRecord(metadata);
  const raw = asRecord(root?.outbound);
  if (!raw) {
    return { ...DEFAULT_FORM_OUTBOUND_EDITOR_SETTINGS };
  }

  return {
    emailPackageOnApprove: Boolean(raw.email_package_on_approve ?? raw.emailPackageOnApprove ?? false),
  };
}

function outboundSettingsAreDefault(settings: FormOutboundEditorSettings): boolean {
  return !settings.emailPackageOnApprove;
}

export function mergeFormOutboundIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormOutboundEditorSettings,
): Record<string, unknown> {
  const next = { ...metadata };

  if (outboundSettingsAreDefault(settings)) {
    delete next.outbound;
    return next;
  }

  next.outbound = {
    email_package_on_approve: settings.emailPackageOnApprove,
  };

  return next;
}
