"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

import { EApprovalComposeReviewSummary } from "@/components/e-approval/e-approval-compose-review-summary";
import { EApprovalComposeStepNav } from "@/components/e-approval/e-approval-compose-step-nav";
import { EApprovalFormFieldsLayout } from "@/components/e-approval/e-approval-form-fields-layout";
import { Button } from "@/components/ui/button";
import {
  parseFormComposeConfig,
  shouldUseSteppedCompose,
  type FormComposeConfig,
} from "@/modules/e-approval/form-compose-config";
import {
  appendComposeReviewStep,
  isComposeReviewStepId,
} from "@/modules/e-approval/form-compose-review";
import {
  buildFormComposeSteps,
  filterFieldErrorsForComposeStep,
  findComposeStepIndexForFieldName,
} from "@/modules/e-approval/form-compose-steps";
import type { ValidateSubmissionValuesOptions } from "@/modules/e-approval/field-validation";
import { validateSubmissionValues, type SubmissionValidationIssue } from "@/modules/e-approval/field-validation";
import type { EApprovalCameraPhotoMetadata } from "@/modules/e-approval/field-camera-options";
import type { EApprovalSavedAttachmentRef } from "@/modules/e-approval/draft-attachments";
import type { EApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

export type ComposeFormStepMeta = {
  stepped: boolean;
  currentStep: number;
  totalSteps: number;
  isLastStep: boolean;
};

type Props = {
  fields: EApprovalFormFieldInput[];
  values: Record<string, string>;
  onChange: (fieldName: string, value: string) => void;
  approverOptions: { id: string; label: string }[];
  formMetadata?: Record<string, unknown> | null;
  composeConfig?: FormComposeConfig;
  approverOptionsLoading?: boolean;
  onFileChange?: (fieldName: string, files: File[]) => void;
  onCameraChange?: (
    fieldName: string,
    files: File[],
    metadataByName: Record<string, EApprovalCameraPhotoMetadata>,
  ) => void;
  fileSelections?: Record<string, File[]>;
  cameraMetadataByField?: Record<string, Record<string, EApprovalCameraPhotoMetadata>>;
  existingAttachmentsByField?: Record<string, EApprovalSavedAttachmentRef[]>;
  onRemoveSavedAttachment?: (attachmentId: string) => void | Promise<void>;
  removingSavedAttachmentId?: string | null;
  density?: "compact" | "comfortable";
  disabled?: boolean;
  fieldErrors?: Record<string, string>;
  fieldHelpOverrides?: Record<string, string>;
  planFeaturesOverride?: EApprovalPlanFeatures;
  allowRemoteLookups?: boolean;
  validationOptions?: ValidateSubmissionValuesOptions;
  /** Controlled step index (owned by parent so autosave remounts do not reset to Step 1). */
  currentStep?: number;
  onCurrentStepChange?: (step: number) => void;
  onStepMetaChange?: (meta: ComposeFormStepMeta) => void;
  onStepValidationIssues?: (issues: SubmissionValidationIssue[]) => void;
  className?: string;
  /** Fields pre-filled from registry — shown locked with an unlock toggle. */
  prefillReadOnlyFields?: Set<string>;
  /** Hide per-field registry badges when the revision summary banner is shown. */
  hidePrefillFieldBadges?: boolean;
};

export function EApprovalComposeFormFields({
  fields,
  values,
  onChange,
  approverOptions,
  formMetadata = null,
  composeConfig: composeConfigOverride,
  approverOptionsLoading,
  onFileChange,
  onCameraChange,
  fileSelections,
  cameraMetadataByField,
  existingAttachmentsByField,
  onRemoveSavedAttachment,
  removingSavedAttachmentId,
  density = "comfortable",
  disabled,
  fieldErrors,
  fieldHelpOverrides,
  planFeaturesOverride,
  allowRemoteLookups = true,
  validationOptions,
  currentStep: controlledStep,
  onCurrentStepChange,
  onStepMetaChange,
  onStepValidationIssues,
  className,
  prefillReadOnlyFields,
  hidePrefillFieldBadges,
}: Props) {
  const composeConfig = composeConfigOverride ?? parseFormComposeConfig(formMetadata);
  const contentSteps = useMemo(
    () => buildFormComposeSteps(fields, composeConfig.stepSource),
    [fields, composeConfig.stepSource],
  );
  const steps = useMemo(() => {
    if (!composeConfig.includeReviewStep || contentSteps.length < 2) {
      return contentSteps;
    }
    return appendComposeReviewStep(contentSteps);
  }, [composeConfig.includeReviewStep, contentSteps]);
  const stepped = shouldUseSteppedCompose(composeConfig, fields);
  const [uncontrolledStep, setUncontrolledStep] = useState(0);
  const [validatedSteps, setValidatedSteps] = useState<Set<number>>(() => new Set());
  const isStepControlled = controlledStep !== undefined;
  const currentStep = isStepControlled ? controlledStep : uncontrolledStep;

  const setCurrentStep = useCallback(
    (next: number | ((prev: number) => number)) => {
      const resolve = (prev: number) => (typeof next === "function" ? next(prev) : next);
      if (isStepControlled) {
        onCurrentStepChange?.(resolve(controlledStep));
        return;
      }
      setUncontrolledStep(resolve);
    },
    [controlledStep, isStepControlled, onCurrentStepChange],
  );

  const safeStepIndex = stepped ? Math.min(currentStep, Math.max(0, steps.length - 1)) : 0;
  const activeStep = stepped ? steps[safeStepIndex] : null;
  const isReviewStep = Boolean(activeStep && isComposeReviewStepId(activeStep.id));
  const isLastStep = !stepped || safeStepIndex >= steps.length - 1;
  const isFirstStep = safeStepIndex <= 0;

  useEffect(() => {
    if (!stepped || steps.length === 0) {
      return;
    }

    const maxIndex = Math.max(0, steps.length - 1);
    if (currentStep > maxIndex) {
      setCurrentStep(maxIndex);
    }
  }, [currentStep, setCurrentStep, stepped, steps.length]);

  useEffect(() => {
    onStepMetaChange?.({
      stepped,
      currentStep: safeStepIndex,
      totalSteps: steps.length,
      isLastStep,
    });
  }, [isLastStep, onStepMetaChange, safeStepIndex, stepped, steps.length]);

  useEffect(() => {
    if (!stepped || !fieldErrors) {
      return;
    }

    // Only real form fields should force a step jump — never parent_submission_id / unknown keys.
    const fieldNames = new Set(fields.map((field) => field.name));
    const firstErrorField = Object.keys(fieldErrors).find(
      (key) => key !== "_form" && fieldNames.has(key),
    );
    if (!firstErrorField) {
      return;
    }

    const targetStep = findComposeStepIndexForFieldName(contentSteps, firstErrorField);
    if (targetStep >= 0 && targetStep !== safeStepIndex) {
      setCurrentStep(targetStep);
    }
  }, [contentSteps, fieldErrors, fields, safeStepIndex, setCurrentStep, stepped]);

  const invalidateFromStep = useCallback((stepIndex: number) => {
    setValidatedSteps((prev) => {
      let changed = false;
      const next = new Set<number>();
      for (const index of prev) {
        if (index < stepIndex) {
          next.add(index);
        } else {
          changed = true;
        }
      }
      return changed ? next : prev;
    });
  }, []);

  const markStepValidated = useCallback((stepIndex: number) => {
    setValidatedSteps((prev) => {
      if (prev.has(stepIndex)) {
        return prev;
      }
      const next = new Set(prev);
      next.add(stepIndex);
      return next;
    });
  }, []);

  const validateCurrentStep = useCallback((): boolean => {
    if (!stepped || !activeStep || isReviewStep) {
      return true;
    }

    if (!composeConfig.validateOnNext) {
      markStepValidated(safeStepIndex);
      return true;
    }

    const issues = validateSubmissionValues(activeStep.fields, values, fileSelections ?? {}, validationOptions ?? {});
    if (issues.length === 0) {
      markStepValidated(safeStepIndex);
      return true;
    }

    onStepValidationIssues?.(issues);
    return false;
  }, [
    activeStep,
    composeConfig.validateOnNext,
    fileSelections,
    isReviewStep,
    markStepValidated,
    onStepValidationIssues,
    safeStepIndex,
    stepped,
    validationOptions,
    values,
  ]);

  const goToStep = useCallback(
    (nextStep: number) => {
      if (!stepped) {
        return;
      }

      const clamped = Math.max(0, Math.min(nextStep, steps.length - 1));
      if (clamped > safeStepIndex && !validateCurrentStep()) {
        return;
      }

      setCurrentStep(clamped);
    },
    [safeStepIndex, setCurrentStep, stepped, steps.length, validateCurrentStep],
  );

  const handleFieldChange = useCallback(
    (fieldName: string, value: string) => {
      const stepIndex = findComposeStepIndexForFieldName(contentSteps, fieldName);
      if (stepIndex >= 0) {
        invalidateFromStep(stepIndex);
      }
      onChange(fieldName, value);
    },
    [contentSteps, invalidateFromStep, onChange],
  );

  const handleFileChange = useCallback(
    (fieldName: string, files: File[]) => {
      const stepIndex = findComposeStepIndexForFieldName(contentSteps, fieldName);
      if (stepIndex >= 0) {
        invalidateFromStep(stepIndex);
      }
      onFileChange?.(fieldName, files);
    },
    [contentSteps, invalidateFromStep, onFileChange],
  );

  const handleCameraChange = useCallback(
    (
      fieldName: string,
      files: File[],
      metadataByName: Record<string, EApprovalCameraPhotoMetadata>,
    ) => {
      const stepIndex = findComposeStepIndexForFieldName(contentSteps, fieldName);
      if (stepIndex >= 0) {
        invalidateFromStep(stepIndex);
      }
      onCameraChange?.(fieldName, files, metadataByName);
    },
    [contentSteps, invalidateFromStep, onCameraChange],
  );

  const handleJumpToField = useCallback(
    (fieldName: string) => {
      const stepIndex = findComposeStepIndexForFieldName(contentSteps, fieldName);
      if (stepIndex >= 0) {
        setCurrentStep(stepIndex);
      }
    },
    [contentSteps, setCurrentStep],
  );

  const stepFieldErrors = useMemo(() => {
    if (!stepped || !activeStep || !fieldErrors || isReviewStep) {
      return fieldErrors;
    }

    return filterFieldErrorsForComposeStep(fieldErrors, activeStep);
  }, [activeStep, fieldErrors, isReviewStep, stepped]);

  const visibleFields = stepped && activeStep && !isReviewStep ? activeStep.fields : fields;

  return (
    <div className={cn("space-y-4", className)}>
      {stepped && composeConfig.showProgress ? (
        <EApprovalComposeStepNav
          steps={steps}
          currentStep={safeStepIndex}
          completedSteps={validatedSteps}
          allowStepSelect={composeConfig.allowBack || !composeConfig.validateOnNext}
          allowAnyStep={!composeConfig.validateOnNext}
          onStepSelect={goToStep}
        />
      ) : null}

      {isReviewStep ? (
        <EApprovalComposeReviewSummary
          fields={fields}
          values={values}
          fileSelections={fileSelections}
          onJumpToField={composeConfig.allowBack ? handleJumpToField : undefined}
        />
      ) : (
        <EApprovalFormFieldsLayout
          fields={stepped && activeStep ? visibleFields : fields}
          values={values}
          onChange={handleFieldChange}
          approverOptions={approverOptions}
          approverOptionsLoading={approverOptionsLoading}
          onFileChange={onFileChange ? handleFileChange : undefined}
          onCameraChange={onCameraChange ? handleCameraChange : undefined}
          fileSelections={fileSelections}
          cameraMetadataByField={cameraMetadataByField}
          existingAttachmentsByField={existingAttachmentsByField}
          onRemoveSavedAttachment={onRemoveSavedAttachment}
          removingSavedAttachmentId={removingSavedAttachmentId}
          density={density}
          disabled={disabled}
          fieldErrors={stepFieldErrors}
          fieldHelpOverrides={fieldHelpOverrides}
          planFeaturesOverride={planFeaturesOverride}
          allowRemoteLookups={allowRemoteLookups}
          formMetadata={formMetadata}
          prefillReadOnlyFields={prefillReadOnlyFields}
          hidePrefillFieldBadges={hidePrefillFieldBadges}
        />
      )}

      {stepped ? (
        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
          <p className="text-xs text-muted-foreground">
            {isReviewStep
              ? "Review the summary, then submit the form."
              : isLastStep
                ? "Review this step, then submit the form."
                : `Step ${safeStepIndex + 1} of ${steps.length}`}
          </p>
          <div className="flex flex-wrap justify-end gap-2">
            {composeConfig.allowBack && !isFirstStep ? (
              <Button type="button" variant="outline" size="sm" onClick={() => goToStep(safeStepIndex - 1)}>
                Back
              </Button>
            ) : null}
            {!isLastStep ? (
              <Button type="button" size="sm" onClick={() => goToStep(safeStepIndex + 1)}>
                Next
              </Button>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
