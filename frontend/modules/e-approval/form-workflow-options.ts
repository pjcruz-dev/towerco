export type FormWorkflowOptionSettings = {
  optionalApprover: boolean;
  sendApprovedFiles: boolean;
  requestComments: boolean;
  mandatoryTraining: boolean;
  /** Empty means every request on this form. Otherwise a field name on the form. */
  trainingField: string;
  /** One matching value per line. Used only when a field is chosen. */
  trainingValues: string;
};

export const DEFAULT_FORM_WORKFLOW_OPTIONS: FormWorkflowOptionSettings = {
  optionalApprover: false,
  sendApprovedFiles: false,
  requestComments: false,
  mandatoryTraining: false,
  trainingField: "",
  trainingValues: "",
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
    mandatoryTraining: trainingEnabled(options?.mandatory_training),
    trainingField: trainingFieldName(options?.mandatory_training),
    trainingValues: trainingValuesText(options?.mandatory_training),
  };
}

export function mergeFormWorkflowOptionsIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormWorkflowOptionSettings,
): Record<string, unknown> {
  const next = { ...metadata };
  if (
    !settings.optionalApprover
    && !settings.sendApprovedFiles
    && !settings.requestComments
    && !settings.mandatoryTraining
  ) {
    delete next.workflow_options;
    return next;
  }

  next.workflow_options = {
    optional_approver: settings.optionalApprover,
    request_comments: settings.requestComments,
    file_distribution: {
      enabled: settings.sendApprovedFiles,
    },
    mandatory_training: {
      enabled: settings.mandatoryTraining,
      field: settings.trainingField.trim(),
      values: trainingTypesList(settings.trainingValues),
    },
  };

  return next;
}

function trainingEnabled(value: unknown): boolean {
  const node = asRecord(value);
  return node?.enabled === true;
}

function trainingFieldName(value: unknown): string {
  const node = asRecord(value);
  const field = typeof node?.field === "string" ? node.field.trim() : "";
  if (field !== "") {
    return field;
  }
  const legacy = node?.document_types;
  return Array.isArray(legacy) && legacy.length > 0 ? "document_type" : "";
}

function trainingValuesText(value: unknown): string {
  const node = asRecord(value);
  const listed = Array.isArray(node?.values)
    ? node.values
    : Array.isArray(node?.document_types)
      ? node.document_types
      : [];

  return listed
    .map((type) => (typeof type === "string" ? type.trim() : ""))
    .filter((type) => type !== "")
    .join("\n");
}

export function trainingTypesList(text: string): string[] {
  return text
    .split(/\n|,/)
    .map((type) => type.trim())
    .filter((type) => type !== "");
}

export function mandatoryTrainingApplies(
  settings: FormWorkflowOptionSettings,
  fieldValue: string | null | undefined,
): boolean {
  if (!settings.mandatoryTraining) {
    return false;
  }
  if (settings.trainingField.trim() === "") {
    return true;
  }
  const types = trainingTypesList(settings.trainingValues);
  if (types.length === 0) {
    return true;
  }
  const hay = (fieldValue ?? "").trim().toLowerCase();
  if (hay === "") {
    return false;
  }

  return types.some((type) => {
    const needle = type.toLowerCase();
    return hay === needle || (needle.length >= 3 && hay.includes(needle));
  });
}
