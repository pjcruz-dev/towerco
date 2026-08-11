export type FormRevisionRouting = "restart_from_start" | "resume_returning_step";

export type FormRevisionEditorSettings = {
  routing: FormRevisionRouting;
  materialFields: string[];
  approverCanForceFullRestart: boolean;
};

export const DEFAULT_FORM_REVISION_EDITOR_SETTINGS: FormRevisionEditorSettings = {
  routing: "restart_from_start",
  materialFields: [],
  approverCanForceFullRestart: false,
};

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

export function parseFormRevisionConfig(metadata: unknown): FormRevisionEditorSettings {
  const root = asRecord(metadata);
  const raw = asRecord(root?.revision);
  if (!raw) {
    return { ...DEFAULT_FORM_REVISION_EDITOR_SETTINGS };
  }

  const routingRaw = String(raw.routing ?? "").trim().toLowerCase();
  const routing: FormRevisionRouting =
    routingRaw === "resume_returning_step" ? "resume_returning_step" : "restart_from_start";

  const materialFields = Array.isArray(raw.material_fields)
    ? raw.material_fields.filter((item): item is string => typeof item === "string" && item.trim() !== "")
    : Array.isArray(raw.materialFields)
      ? raw.materialFields.filter((item): item is string => typeof item === "string" && item.trim() !== "")
      : [];

  const forceRaw = raw.approver_can_force_full_restart ?? raw.approverCanForceFullRestart;
  const approverCanForceFullRestart = forceRaw === undefined ? false : Boolean(forceRaw);

  return {
    routing,
    materialFields: [...new Set(materialFields.map((item) => item.trim()))],
    approverCanForceFullRestart,
  };
}

export function formRevisionSettingsFromMetadata(metadata: unknown): FormRevisionEditorSettings {
  return parseFormRevisionConfig(metadata);
}

function revisionSettingsAreDefault(settings: FormRevisionEditorSettings): boolean {
  return (
    settings.routing === "restart_from_start" &&
    settings.materialFields.length === 0 &&
    !settings.approverCanForceFullRestart
  );
}

export function mergeFormRevisionIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormRevisionEditorSettings,
): Record<string, unknown> {
  const next = { ...metadata };

  if (revisionSettingsAreDefault(settings)) {
    delete next.revision;
    return next;
  }

  next.revision = {
    routing: settings.routing,
    material_fields: settings.materialFields,
    approver_can_force_full_restart: settings.approverCanForceFullRestart,
  };

  return next;
}

export function revisionRoutingLabel(routing: FormRevisionRouting): string {
  return routing === "resume_returning_step"
    ? "Resume at returning step"
    : "Restart from step 1";
}

export function isResumeRevisionRouting(routing: string | null | undefined): boolean {
  return routing === "resume_returning_step";
}

export function revisionRoutingReasonLabel(reason: string | null | undefined): string {
  switch (reason) {
    case "resume_returning_step":
      return "Resumed at the step that requested revision";
    case "form_restart_setting":
      return "Form is set to restart from step 1";
    case "approver_force_full_restart":
      return "Approver required full re-approval from step 1";
    case "material_fields_changed":
      return "Material fields changed — restarted from step 1";
    case "missing_return_step":
      return "Return step was not available — restarted from step 1";
    case "return_step_condition_failed":
      return "Return step no longer applies — restarted from step 1";
    case "default_restart":
      return "Restarted from step 1";
    default:
      return reason ? reason.replaceAll("_", " ") : "Restarted from step 1";
  }
}

type ResubmitOutlookInput = {
  status?: string | null;
  forceFullRestart?: boolean;
  routing?: FormRevisionRouting | string | null;
  returnedFromStep?: number | null;
  materialFieldCount?: number;
};

/** Banner copy while the request is returned / rejected — what will happen on resubmit. */
export function describeResubmitRoutingOutlook(input: ResubmitOutlookInput): string {
  if (input.status === "rejected") {
    return "On resubmit, the workflow always restarts from step 1.";
  }

  if (input.forceFullRestart) {
    return "On resubmit, the workflow will restart from step 1 (approver required full re-approval).";
  }

  if (!isResumeRevisionRouting(input.routing)) {
    return "On resubmit, the workflow will restart from step 1. Earlier approvals become prior-cycle history.";
  }

  const step = input.returnedFromStep != null && input.returnedFromStep > 0 ? input.returnedFromStep : null;
  const material =
    (input.materialFieldCount ?? 0) > 0
      ? " Changing a material field forces a full restart from step 1."
      : "";

  if (step != null) {
    return `On resubmit, approval resumes at step ${step}. Earlier approvals stay valid.${material}`;
  }

  return `On resubmit, approval resumes at the step that requested revision. Earlier approvals stay valid.${material}`;
}

type AppliedRoutingInput = {
  routing?: string | null;
  reason?: string | null;
  currentStep?: number | null;
};

/** Trail / path note after a resubmit has already applied routing. */
export function describeRevisionRoutingApplied(input: AppliedRoutingInput): string {
  const reason = revisionRoutingReasonLabel(input.reason);
  const resumed = isResumeRevisionRouting(input.routing);
  const mode = resumed ? "Resume" : "Full restart";
  const step =
    input.currentStep != null && input.currentStep > 0 ? ` · now at step ${input.currentStep}` : "";

  return `Last resubmit · ${mode}: ${reason}${step}`;
}

/** Short toast line after a successful resubmit. */
export function describeResubmitToastMessage(input: AppliedRoutingInput): string {
  const reason = revisionRoutingReasonLabel(input.reason);
  if (input.currentStep != null && input.currentStep > 0) {
    return `${reason} (now at step ${input.currentStep})`;
  }
  return reason;
}
