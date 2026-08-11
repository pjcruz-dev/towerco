import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import {
  isComposeFillableFieldType,
  resolveEffectiveStepSource,
  type FormComposeStepSource,
} from "@/modules/e-approval/form-compose-structural";

import { buildFormComposeSteps } from "@/modules/e-approval/form-compose-steps";

export type FormComposeMode = "single_page" | "stepped";

export type { FormComposeStepSource };

export type FormComposeConfig = {
  mode: FormComposeMode;
  stepSource: FormComposeStepSource;
  showProgress: boolean;
  validateOnNext: boolean;
  allowBack: boolean;
  /** Append a read-only Review & submit step before the requestor can submit. */
  includeReviewStep: boolean;
};

export type FormComposeEditorSettings = {
  mode: FormComposeMode;
  stepSource: FormComposeStepSource;
  showProgress: boolean;
  validateOnNext: boolean;
  allowBack: boolean;
  includeReviewStep: boolean;
};

export const DEFAULT_FORM_COMPOSE_EDITOR_SETTINGS: FormComposeEditorSettings = {
  mode: "single_page",
  stepSource: "auto",
  showProgress: true,
  validateOnNext: true,
  allowBack: true,
  includeReviewStep: false,
};

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

function readBoolean(raw: Record<string, unknown>, camel: string, snake: string, defaultValue: boolean): boolean {
  if (raw[camel] !== undefined) {
    return Boolean(raw[camel]);
  }
  if (raw[snake] !== undefined) {
    return Boolean(raw[snake]);
  }

  return defaultValue;
}

function parseStepSource(raw: Record<string, unknown> | null): FormComposeStepSource {
  if (!raw) {
    return "sections";
  }

  const value = String(raw.step_source ?? raw.stepSource ?? "sections").trim().toLowerCase();
  if (value === "page_breaks" || value === "page_break") {
    return "page_breaks";
  }
  if (value === "auto") {
    return "auto";
  }

  return "sections";
}

export function parseFormComposeConfig(metadata: unknown): FormComposeConfig {
  const root = asRecord(metadata);
  const raw = asRecord(root?.compose);
  if (!raw) {
    return {
      mode: "single_page",
      stepSource: "sections",
      showProgress: true,
      validateOnNext: true,
      allowBack: true,
      includeReviewStep: false,
    };
  }

  const modeRaw = String(raw.mode ?? "").trim().toLowerCase();

  return {
    mode: modeRaw === "stepped" ? "stepped" : "single_page",
    stepSource: parseStepSource(raw),
    showProgress: readBoolean(raw, "showProgress", "show_progress", true),
    validateOnNext: readBoolean(raw, "validateOnNext", "validate_on_next", true),
    allowBack: readBoolean(raw, "allowBack", "allow_back", true),
    includeReviewStep: readBoolean(raw, "includeReviewStep", "include_review_step", false),
  };
}

export function formComposeSettingsFromMetadata(metadata: unknown): FormComposeEditorSettings {
  const parsed = parseFormComposeConfig(metadata);

  return {
    mode: parsed.mode,
    stepSource: parsed.stepSource,
    showProgress: parsed.showProgress,
    validateOnNext: parsed.validateOnNext,
    allowBack: parsed.allowBack,
    includeReviewStep: parsed.includeReviewStep,
  };
}

function composeSettingsAreDefault(settings: FormComposeEditorSettings): boolean {
  return (
    settings.mode === "single_page" &&
    settings.stepSource === "auto" &&
    settings.showProgress &&
    settings.validateOnNext &&
    settings.allowBack &&
    !settings.includeReviewStep
  );
}

export function mergeFormComposeIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormComposeEditorSettings,
): Record<string, unknown> {
  const next = { ...metadata };

  if (composeSettingsAreDefault(settings)) {
    delete next.compose;
    return next;
  }

  next.compose = {
    mode: settings.mode,
    step_source: settings.stepSource,
    show_progress: settings.showProgress,
    validate_on_next: settings.validateOnNext,
    allow_back: settings.allowBack,
    include_review_step: settings.includeReviewStep,
  };

  return next;
}

export function shouldUseSteppedCompose(config: FormComposeConfig, fields: EApprovalFormFieldInput[]): boolean {
  if (config.mode !== "stepped") {
    return false;
  }

  return buildFormComposeSteps(fields, config.stepSource).length >= 2;
}

export type FormComposeReadiness = {
  ready: boolean;
  message?: string;
  stepCount: number;
};

function steppedReadinessMessage(
  fields: EApprovalFormFieldInput[],
  stepSource: FormComposeStepSource,
): string {
  const effective = resolveEffectiveStepSource(fields, stepSource);

  if (effective === "page_breaks") {
    return "Stepped mode needs at least two steps. Add Page break fields on the canvas to split the form.";
  }

  return "Stepped mode needs at least two sections. Add Section fields on the canvas to define steps.";
}

export function formComposeReadiness(
  settings: FormComposeEditorSettings,
  fields: EApprovalFormFieldInput[],
): FormComposeReadiness {
  const stepCount = buildFormComposeSteps(fields, settings.stepSource).length;

  if (settings.mode !== "stepped") {
    return { ready: true, stepCount };
  }

  if (stepCount < 2) {
    return {
      ready: false,
      stepCount,
      message: steppedReadinessMessage(fields, settings.stepSource),
    };
  }

  return { ready: true, stepCount };
}

export type FormComposeDesignSummary = {
  mode: FormComposeMode;
  modeLabel: string;
  stepCount: number;
  fillableFieldCount: number;
  steppedActive: boolean;
  ready: boolean;
  stepSourceLabel: string;
};

function stepSourceLabel(settings: FormComposeEditorSettings, fields: EApprovalFormFieldInput[]): string {
  if (settings.mode !== "stepped") {
    return "";
  }

  const effective = resolveEffectiveStepSource(fields, settings.stepSource);
  if (settings.stepSource === "auto") {
    return effective === "page_breaks" ? "Auto · page breaks" : "Auto · sections";
  }

  return effective === "page_breaks" ? "Page breaks" : "Sections";
}

export function buildFormComposeDesignSummary(
  settings: FormComposeEditorSettings,
  fields: EApprovalFormFieldInput[],
): FormComposeDesignSummary {
  const readiness = formComposeReadiness(settings, fields);
  const config: FormComposeConfig = {
    mode: settings.mode,
    stepSource: settings.stepSource,
    showProgress: settings.showProgress,
    validateOnNext: settings.validateOnNext,
    allowBack: settings.allowBack,
    includeReviewStep: settings.includeReviewStep,
  };
  const steppedActive = shouldUseSteppedCompose(config, fields);
  const fillableFieldCount = fields.filter((field) => isComposeFillableFieldType(field.type)).length;
  const sourceLabel = stepSourceLabel(settings, fields);

  return {
    mode: settings.mode,
    modeLabel: steppedActive
      ? `Stepped · ${readiness.stepCount} steps${sourceLabel ? ` · ${sourceLabel}` : ""}`
      : "Single page",
    stepCount: readiness.stepCount,
    fillableFieldCount,
    steppedActive,
    ready: readiness.ready,
    stepSourceLabel: sourceLabel,
  };
}
