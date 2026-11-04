export type FormWorkflowOptionSettings = {
  optionalApprover: boolean;
  sendApprovedFiles: boolean;
  requestComments: boolean;
};

export const DEFAULT_FORM_WORKFLOW_OPTIONS: FormWorkflowOptionSettings = {
  optionalApprover: false,
  sendApprovedFiles: false,
  requestComments: false,
};

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

export function formWorkflowOptionsFromMetadata(metadata: unknown): FormWorkflowOptionSettings {
  const root = asRecord(metadata);
  const options = asRecord(root?.workflow_options);
  const distribution = asRecord(options?.file_distribution);

  return {
    optionalApprover: options?.optional_approver === true,
    sendApprovedFiles: distribution?.enabled === true,
    requestComments: options?.request_comments === true,
  };
}

export function mergeFormWorkflowOptionsIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormWorkflowOptionSettings,
): Record<string, unknown> {
  const next = { ...metadata };
  if (!settings.optionalApprover && !settings.sendApprovedFiles && !settings.requestComments) {
    delete next.workflow_options;
    return next;
  }

  next.workflow_options = {
    optional_approver: settings.optionalApprover,
    request_comments: settings.requestComments,
    file_distribution: {
      enabled: settings.sendApprovedFiles,
    },
  };

  return next;
}
