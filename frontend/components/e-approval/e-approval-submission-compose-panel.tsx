"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { ControlledDocumentPicker } from "@/components/e-approval/controlled-document-picker";
import { ControlledDocumentRequestModePicker } from "@/components/e-approval/controlled-document-request-mode-picker";
import { EApprovalCashAdvancePicker } from "@/components/e-approval/e-approval-cash-advance-picker";
import { EApprovalComposeFormFields, type ComposeFormStepMeta } from "@/components/e-approval/e-approval-compose-form-fields";
import { EApprovalPurchaseRequisitionPicker } from "@/components/e-approval/e-approval-purchase-requisition-picker";
import { EApprovalFormSectionProgressNav } from "@/components/e-approval/e-approval-form-section-progress";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Button } from "@/components/ui/button";
import {
  createEApprovalSubmission,
  deleteEApprovalAttachment,
  fetchEApprovalForm,
  fetchEApprovalFormMyDraft,
  fetchEApprovalMetadata,
  fetchEApprovalApprovalPolicy,
  fetchEApprovalOpenCashAdvances,
  fetchEApprovalOpenPurchaseRequisitions,
  fetchEApprovalSubmission,
  resubmitEApprovalSubmission,
  submitEApprovalSubmissionDraft,
  updateEApprovalSubmissionDraft,
  uploadEApprovalSubmissionAttachmentsOrThrow,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import {
  mapEApprovalAssignableUsersToOptions,
  useEApprovalAssignableUsers,
} from "@/hooks/use-e-approval-assignable-users";
import { validateDuplicateApproverFields } from "@/modules/e-approval/display";
import {
  reconcileApproverFieldValues,
  requiredApproverFieldNamesForSubmit,
} from "@/modules/e-approval/approver-field-support";
import {
  controlledDocumentFieldHelp,
  parseControlledDocumentSync,
} from "@/modules/e-approval/controlled-document-sync";
import {
  clearControlledDocumentRevisionValues,
  controlledDocumentDraftLooksLikeRevision,
  filterFieldsForControlledDocumentMode,
  validateControlledDocumentRequest,
  type ControlledDocumentRequestMode,
} from "@/modules/e-approval/controlled-document-compose";
import { applyControlledDocumentLookupPrefill } from "@/modules/e-approval/controlled-document-lookup-prefill";
import { lookupControlledDocument, type ControlledDocumentLookupResult } from "@/lib/api/modules/controlled-documents-api";
import { applyComputedFieldValues } from "@/modules/e-approval/field-computed";
import { fieldDefaultValue, parseFieldValidation, validateSubmissionValues } from "@/modules/e-approval/field-validation";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import { procurementLinkCascadePatch } from "@/modules/e-approval/procurement-link-fields";
import { attachmentCountsByField } from "@/modules/procurement-one/submit-readiness";
import { groupSavedAttachmentsByField, hasPendingAttachmentFiles, pendingAttachmentsNotYetSaved } from "@/modules/e-approval/draft-attachments";
import {
  buildFormSectionProgress,
  shouldShowFormSectionProgress,
} from "@/modules/e-approval/form-section-progress";
import {
  parseFormComposeConfig,
  shouldUseSteppedCompose,
} from "@/modules/e-approval/form-compose-config";
import {
  applyParentPrefillValues,
  formRequiresParentSubmission,
  formUsesCashAdvanceParentPicker,
  formUsesPurchaseRequisitionParentPicker,
  formatSubmissionAmount,
  parentSubmissionLinkLabel,
  parentSubmissionLinkTitle,
  evaluatePurchaseOrderAmountWithPolicy,
  parseSubmissionAmount,
  validateLiquidationAmountAgainstOpenBalance,
} from "@/modules/e-approval/parent-submission-link";
import {
  clearLocalComposeDraft,
  readLocalComposeDraft,
  submissionDetailToValues,
  writeLocalComposeDraft,
} from "@/modules/e-approval/submission-draft-storage";
import type {
  EApprovalFormFieldInput,
  EApprovalOpenCashAdvance,
  EApprovalOpenPurchaseRequisition,
  EApprovalSubmissionDetail,
} from "@/modules/e-approval/types";
import { AlertCircle, CheckCircle2, GitBranch } from "lucide-react";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";
import {
  E_APPROVAL_WIDE_FORM_MAX_WIDTH_CLASS,
} from "@/modules/e-approval/form-layout";

export type EApprovalSubmissionSubmitResult = {
  submission: EApprovalSubmissionDetail;
  failedUploads: string[];
};

type Props = {
  formId: string;
  enabled?: boolean;
  onSubmitted: (result: EApprovalSubmissionSubmitResult) => void;
  onCancel?: () => void;
  onFormLoaded?: (form: { name: string }) => void;
  fullPage?: boolean;
  /** Overrides the default full-page shell width when embedded in a page layout. */
  shellClassName?: string;
  notifyOnSuccess?: boolean;
  focused?: boolean;
  /** Edit and resubmit an existing returned/rejected submission instead of creating a new request. */
  resubmitSubmissionId?: string;
  /** Pre-select controlled-document compose mode (overrides URL when set). */
  initialControlledMode?: ControlledDocumentRequestMode;
  /** Pre-fill document code for revision requests (overrides URL when set). */
  initialDocumentCode?: string;
};

function buildInitialValues(fields: EApprovalFormFieldInput[]): Record<string, string> {
  const values: Record<string, string> = {};
  for (const field of fields) {
    if (field.type === "grid") {
      values[field.name] = '{"rows":[{"0":""}]}';
    } else if (field.type === "tags" || field.type === "location") {
      values[field.name] = "";
    } else if (isComposeFillableFieldType(field.type)) {
      values[field.name] = fieldDefaultValue(field);
    }
  }
  return values;
}

function formatSavedAt(date: Date | null): string | null {
  if (!date) {
    return null;
  }
  return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

export function EApprovalSubmissionComposePanel({
  formId,
  enabled = true,
  onSubmitted,
  onCancel,
  onFormLoaded,
  fullPage = false,
  shellClassName,
  notifyOnSuccess,
  focused = false,
  resubmitSubmissionId,
  initialControlledMode,
  initialDocumentCode,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const shouldNotify = notifyOnSuccess ?? fullPage;
  const isResubmitMode = Boolean(resubmitSubmissionId);
  const [values, setValues] = useState<Record<string, string>>({});
  const [attachmentFiles, setAttachmentFiles] = useState<Record<string, File[]>>({});
  const [cameraMetadataByField, setCameraMetadataByField] = useState<
    Record<string, Record<string, import("@/modules/e-approval/field-camera-options").EApprovalCameraPhotoMetadata>>
  >({});
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [composeStepMeta, setComposeStepMeta] = useState<ComposeFormStepMeta>({
    stepped: false,
    currentStep: 0,
    totalSteps: 1,
    isLastStep: true,
  });
  const [composeCurrentStep, setComposeCurrentStep] = useState(0);
  const [parentSubmissionId, setParentSubmissionId] = useState<string | null>(null);
  const [draftSubmissionId, setDraftSubmissionId] = useState<string | null>(null);
  const [draftSavedAt, setDraftSavedAt] = useState<Date | null>(null);
  const hydratedRef = useRef(false);
  const userEditedRef = useRef(false);
  const parentPrefillAppliedRef = useRef<string | null>(null);
  const revisionManualRef = useRef(false);
  const lastControlledLookupCodeRef = useRef<string | null>(null);
  const [controlledDocumentRequestMode, setControlledDocumentRequestMode] =
    useState<ControlledDocumentRequestMode>("new");
  const [prefillReadOnlyFields, setPrefillReadOnlyFields] = useState<Set<string>>(new Set());

  const controlledDocumentDeepLink = useMemo(() => {
    const modeParam = initialControlledMode ?? searchParams.get("controlled_mode");
    const mode =
      modeParam === "revision" || modeParam === "new" ? modeParam : null;
    const documentCode =
      (initialDocumentCode ?? searchParams.get("document_code") ?? "").trim() || null;

    return { mode, documentCode };
  }, [initialControlledMode, initialDocumentCode, searchParams]);

  const formQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "submit"],
    queryFn: () => fetchEApprovalForm(formId),
    enabled: enabled && !!formId,
  });

  const draftQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "my-draft"],
    queryFn: () => fetchEApprovalFormMyDraft(formId),
    enabled: enabled && !!formId && !isResubmitMode,
    staleTime: 0,
  });

  const resubmitQuery = useQuery({
    queryKey: ["e-approval", "submission", resubmitSubmissionId],
    queryFn: () => fetchEApprovalSubmission(resubmitSubmissionId!),
    enabled: enabled && !!resubmitSubmissionId,
    staleTime: 0,
  });

  const formMetadata = formQuery.data?.metadata_json ?? null;
  const composeConfig = useMemo(() => parseFormComposeConfig(formMetadata), [formMetadata]);
  const controlledDocumentSync = useMemo(
    () => parseControlledDocumentSync(formMetadata),
    [formMetadata],
  );
  const usesCashAdvancePicker = formUsesCashAdvanceParentPicker(formMetadata);
  const usesPurchaseRequisitionPicker = formUsesPurchaseRequisitionParentPicker(formMetadata);

  const openCashAdvancesQuery = useQuery({
    queryKey: ["e-approval", "cash-advances", "open", formId],
    queryFn: () => fetchEApprovalOpenCashAdvances(formId),
    enabled: enabled && !!formId && usesCashAdvancePicker,
    staleTime: 30_000,
  });

  const openPurchaseRequisitionsQuery = useQuery({
    queryKey: ["e-approval", "purchase-requisitions", "open", formId],
    queryFn: () => fetchEApprovalOpenPurchaseRequisitions(formId),
    enabled: enabled && !!formId && usesPurchaseRequisitionPicker,
    staleTime: 30_000,
  });

  const usersQuery = useEApprovalAssignableUsers(enabled);

  const metadataQuery = useQuery({
    queryKey: ["e-approval", "metadata"],
    queryFn: () => fetchEApprovalMetadata(),
    enabled,
    staleTime: 60_000,
  });

  const approvalPolicyQuery = useQuery({
    queryKey: ["e-approval", "approval-policies"],
    queryFn: () => fetchEApprovalApprovalPolicy(),
    enabled: formMetadata?.use_approval_policy === true,
    staleTime: 60_000,
  });

  const fields = formQuery.data?.fields ?? [];

  const composeFields = useMemo(
    () => filterFieldsForControlledDocumentMode(fields, controlledDocumentSync, controlledDocumentRequestMode),
    [controlledDocumentRequestMode, controlledDocumentSync, fields],
  );

  const steppedComposeActive = useMemo(
    () => shouldUseSteppedCompose(composeConfig, composeFields),
    [composeConfig, composeFields],
  );

  const handleControlledDocumentRequestModeChange = useCallback(
    (mode: ControlledDocumentRequestMode) => {
      userEditedRef.current = true;
      setControlledDocumentRequestMode(mode);
      revisionManualRef.current = false;

      if (!controlledDocumentSync) {
        return;
      }

      if (mode === "new") {
        lastControlledLookupCodeRef.current = null;
        setPrefillReadOnlyFields(new Set());
        setValues((prev) => clearControlledDocumentRevisionValues(controlledDocumentSync, prev));
        setFieldErrors((prev) => {
          const next = { ...prev };
          delete next[controlledDocumentSync.documentCodeField];
          delete next[controlledDocumentSync.revisionFieldName];
          if (controlledDocumentSync.fieldMap.change_summary) {
            delete next[controlledDocumentSync.fieldMap.change_summary];
          }
          return next;
        });
      }
    },
    [controlledDocumentSync],
  );

  useEffect(() => {
    if (formQuery.data?.name) {
      onFormLoaded?.({ name: formQuery.data.name });
    }
  }, [formQuery.data?.name, onFormLoaded]);

  const approverOptions = useMemo(
    () => mapEApprovalAssignableUsersToOptions(usersQuery.data),
    [usersQuery.data],
  );

  const computedValues = useMemo(() => {
    const withComputed = fields.length > 0 ? applyComputedFieldValues(fields, values) : values;

    return approverOptions.length > 0
      ? reconcileApproverFieldValues(fields, withComputed, approverOptions)
      : withComputed;
  }, [approverOptions, fields, values]);

  const controlledDocumentCode = controlledDocumentSync
    ? (values[controlledDocumentSync.documentCodeField] ?? "").trim()
    : "";

  const handleControlledDocumentCodeChange = useCallback(
    (code: string) => {
      if (!controlledDocumentSync) {
        return;
      }
      userEditedRef.current = true;
      revisionManualRef.current = false;
      if (code.trim() === "") {
        lastControlledLookupCodeRef.current = null;
      }
      setValues((prev) => ({
        ...prev,
        [controlledDocumentSync.documentCodeField]: code,
      }));
      setFieldErrors((prev) => {
        const next = { ...prev };
        delete next[controlledDocumentSync.documentCodeField];
        return next;
      });
    },
    [controlledDocumentSync],
  );

  const handleControlledDocumentLookupResolved = useCallback(
    (lookup: ControlledDocumentLookupResult) => {
      if (
        !controlledDocumentSync ||
        !hydratedRef.current ||
        !lookup.exists ||
        controlledDocumentRequestMode !== "revision"
      ) {
        return;
      }

      const code = (lookup.document_code ?? "").trim();
      if (code === "" || lastControlledLookupCodeRef.current === code) {
        return;
      }

      lastControlledLookupCodeRef.current = code;
      revisionManualRef.current = false;
      setValues((prev) =>
        applyControlledDocumentLookupPrefill(controlledDocumentSync, fields, prev, lookup, {
          overwrite: true,
        }),
      );
      // Mark which fields were auto-populated so they render as locked in revision mode.
      const locked = new Set<string>([
        controlledDocumentSync.revisionFieldName,
        "previous_revision",
      ]);
      if (lookup.title) locked.add(controlledDocumentSync.fieldMap.title ?? "title");
      if (lookup.document_type) locked.add(controlledDocumentSync.fieldMap.document_type ?? "document_type");
      if (lookup.department) locked.add(controlledDocumentSync.fieldMap.department ?? "department");
      setPrefillReadOnlyFields(locked);
    },
    [controlledDocumentSync, controlledDocumentRequestMode, fields],
  );

  useEffect(() => {
    if (
      !controlledDocumentSync?.autoRevision ||
      !hydratedRef.current ||
      controlledDocumentRequestMode !== "new"
    ) {
      return;
    }

    if (revisionManualRef.current) {
      return;
    }

    const revisionField = controlledDocumentSync.revisionFieldName;
    setValues((prev) => {
      if ((prev[revisionField] ?? "") === "0") {
        return prev;
      }

      return { ...prev, [revisionField]: "0" };
    });
  }, [controlledDocumentRequestMode, controlledDocumentSync]);

  const procurementPolicy = metadataQuery.data?.finance_procurement_policy;
  const requiresParentSubmission = formRequiresParentSubmission(formMetadata);
  const parentLinkLabel = parentSubmissionLinkLabel(formMetadata);
  const parentLinkTitle = parentSubmissionLinkTitle(formMetadata);
  const parentWriteOptions = { parentSubmissionId };

  useEffect(() => {
    hydratedRef.current = false;
    userEditedRef.current = false;
    parentPrefillAppliedRef.current = null;
    revisionManualRef.current = false;
    setControlledDocumentRequestMode("new");
    setDraftSubmissionId(null);
    setParentSubmissionId(null);
    setDraftSavedAt(null);
    setFieldErrors({});
    setComposeCurrentStep(0);
  }, [formId, resubmitSubmissionId]);

  useEffect(() => {
    if (!formQuery.data?.fields || hydratedRef.current) {
      return;
    }
    if (isResubmitMode) {
      if (resubmitQuery.isLoading) {
        return;
      }
    } else if (draftQuery.isLoading) {
      return;
    }

    // Never clobber in-progress typing if the panel remounted mid-edit.
    if (userEditedRef.current) {
      hydratedRef.current = true;
      return;
    }

    const base = buildInitialValues(formQuery.data.fields);
    let mergedValues = base;
    let restoredDraft = false;
    let serverDraft: EApprovalSubmissionDetail | null | undefined = null;

    if (isResubmitMode) {
      const submission = resubmitQuery.data;
      if (!submission) {
        return;
      }
      if (submission.status !== "returned" && submission.status !== "rejected") {
        return;
      }
      setDraftSubmissionId(submission.id);
      setParentSubmissionId(submission.parent_submission_id ?? null);
      mergedValues = { ...base, ...submissionDetailToValues(submission.values) };
      setDraftSavedAt(null);
    } else {
      serverDraft = draftQuery.data;
      const localDraft = readLocalComposeDraft(formId);

      if (serverDraft) {
        setDraftSubmissionId(serverDraft.id);
        setParentSubmissionId(serverDraft.parent_submission_id ?? null);
        const serverValues = submissionDetailToValues(serverDraft.values);
        const localValues = localDraft?.values ?? {};
        // Prefer whichever draft is newer so a mid-save refetch cannot blank the form.
        const serverUpdatedAt = serverDraft.updated_at ? Date.parse(serverDraft.updated_at) : 0;
        const localSavedAt = localDraft?.savedAt ? Date.parse(localDraft.savedAt) : 0;
        mergedValues =
          localDraft && localSavedAt > serverUpdatedAt
            ? { ...base, ...serverValues, ...localValues }
            : { ...base, ...localValues, ...serverValues };
        setDraftSavedAt(serverDraft.updated_at ? new Date(serverDraft.updated_at) : new Date());
        restoredDraft = true;
      } else if (localDraft) {
        setParentSubmissionId(localDraft.parentSubmissionId ?? null);
        mergedValues = { ...base, ...localDraft.values };
        setDraftSavedAt(new Date(localDraft.savedAt));
        restoredDraft = true;
      }
    }

    const sync = parseControlledDocumentSync(formQuery.data.metadata_json ?? null);
    let discardRestoredDraftNotice = false;

    if (sync && !isResubmitMode) {
      const { mode, documentCode } = controlledDocumentDeepLink;
      let resolvedMode: ControlledDocumentRequestMode = "new";

      if (mode === "new" || mode === "revision") {
        resolvedMode = mode;
      } else if (documentCode) {
        resolvedMode = "revision";
      }

      const explicitNewIntent = mode === "new";
      const draftLooksLikeRevision = controlledDocumentDraftLooksLikeRevision(sync, mergedValues);

      if (documentCode && resolvedMode === "revision") {
        mergedValues = {
          ...mergedValues,
          [sync.documentCodeField]: documentCode,
        };
      }

      if (resolvedMode === "new") {
        if (explicitNewIntent && draftLooksLikeRevision) {
          mergedValues = clearControlledDocumentRevisionValues(sync, base);
          clearLocalComposeDraft(formId);
          discardRestoredDraftNotice = restoredDraft;
        } else {
          mergedValues = clearControlledDocumentRevisionValues(sync, mergedValues);
        }
        lastControlledLookupCodeRef.current = null;
        setPrefillReadOnlyFields(new Set());
      }

      setControlledDocumentRequestMode(resolvedMode);
    } else if (sync && isResubmitMode) {
      const looksLikeRevision = controlledDocumentDraftLooksLikeRevision(sync, mergedValues);
      setControlledDocumentRequestMode(looksLikeRevision ? "revision" : "new");
    }

    setValues(mergedValues);

    if (restoredDraft && !discardRestoredDraftNotice) {
      push({
        level: "info",
        title: "Draft restored",
        message: serverDraft
          ? `Continuing ${serverDraft.document_no}. Your progress was saved on the server.`
          : "Loaded your last saved progress from this browser.",
      });
    }

    setAttachmentFiles({});
    hydratedRef.current = true;
  }, [
    controlledDocumentDeepLink,
    formQuery.data,
    draftQuery.data,
    draftQuery.isLoading,
    formId,
    isResubmitMode,
    push,
    resubmitQuery.data,
    resubmitQuery.isLoading,
  ]);

  const sectionProgress = useMemo(
    () => buildFormSectionProgress(composeFields, computedValues),
    [composeFields, computedValues],
  );
  const showSectionProgress =
    !steppedComposeActive &&
    !controlledDocumentSync?.composeUi.hideSectionProgress &&
    shouldShowFormSectionProgress(sectionProgress);

  const showControlledDocumentRegistryUi = controlledDocumentSync && !controlledDocumentSync.composeUi.hideRegistryPicker;

  // When the registry picker UI is hidden (default for controlled-doc forms), the
  // ControlledDocumentPicker component is never mounted, so its internal lookup query
  // never fires. Run the lookup directly here so the form fields get prefilled from
  // the document registry as soon as the document code is available.
  const silentLookupEnabled =
    !showControlledDocumentRegistryUi &&
    !!controlledDocumentSync &&
    controlledDocumentRequestMode === "revision" &&
    controlledDocumentCode.length >= 3;

  const silentLookupQuery = useQuery({
    queryKey: ["documents", "controlled", "lookup", controlledDocumentCode],
    queryFn: () => lookupControlledDocument(controlledDocumentCode),
    enabled: silentLookupEnabled,
    staleTime: 30_000,
  });

  useEffect(() => {
    if (!silentLookupEnabled || !silentLookupQuery.data?.exists) {
      return;
    }
    handleControlledDocumentLookupResolved(silentLookupQuery.data);
  }, [silentLookupEnabled, silentLookupQuery.data, handleControlledDocumentLookupResolved]);

  useEffect(() => {
    if (!hydratedRef.current || fields.length === 0 || approverOptions.length === 0) {
      return;
    }

    setValues((prev) => {
      const reconciled = reconcileApproverFieldValues(fields, prev, approverOptions);
      return reconciled === prev ? prev : reconciled;
    });
  }, [approverOptions, fields]);

  const requiredApproverFieldNames = useMemo(
    () =>
      requiredApproverFieldNamesForSubmit(
        fields,
        formMetadata,
        computedValues,
        approvalPolicyQuery.data?.published_version?.config
          ?? approvalPolicyQuery.data?.defaults
          ?? null,
      ),
    [approvalPolicyQuery.data, computedValues, fields, formMetadata],
  );

  const activeSubmissionAttachments = isResubmitMode
    ? (resubmitQuery.data?.attachments ?? [])
    : (draftQuery.data?.attachments ?? []);

  const draftExistingAttachmentCounts = useMemo(
    () =>
      attachmentCountsByField(
        activeSubmissionAttachments.map((attachment) => ({
          field_name: attachment.field_name ?? "",
        })),
      ),
    [activeSubmissionAttachments],
  );

  const draftExistingAttachmentsByField = useMemo(
    () => groupSavedAttachmentsByField(activeSubmissionAttachments),
    [activeSubmissionAttachments],
  );

  const needsApproverOptions = fields.some((field) => field.type === "approver");

  const duplicateApproverWarning = useMemo(
    () => validateDuplicateApproverFields(fields, computedValues),
    [computedValues, fields],
  );

  const selectedCashAdvance = useMemo(
    () => openCashAdvancesQuery.data?.find((item) => item.id === parentSubmissionId) ?? null,
    [openCashAdvancesQuery.data, parentSubmissionId],
  );

  const selectedPurchaseRequisition = useMemo(
    () => openPurchaseRequisitionsQuery.data?.find((item) => item.id === parentSubmissionId) ?? null,
    [openPurchaseRequisitionsQuery.data, parentSubmissionId],
  );

  const selectedParentOpenBalance = selectedCashAdvance?.open_balance ?? selectedPurchaseRequisition?.open_balance;

  const poAmountEvaluation = useMemo(() => {
    if (!usesPurchaseRequisitionPicker) {
      return null;
    }

    return evaluatePurchaseOrderAmountWithPolicy(
      parseSubmissionAmount(computedValues.total_amount),
      selectedPurchaseRequisition?.open_balance,
      selectedPurchaseRequisition?.estimated_total,
      procurementPolicy,
    );
  }, [
    computedValues.total_amount,
    procurementPolicy,
    selectedPurchaseRequisition?.estimated_total,
    selectedPurchaseRequisition?.open_balance,
    usesPurchaseRequisitionPicker,
  ]);

  const balanceError = useMemo(() => {
    if (usesCashAdvancePicker) {
      return validateLiquidationAmountAgainstOpenBalance(
        parseSubmissionAmount(computedValues.total_reimbursement),
        selectedParentOpenBalance,
      );
    }
    if (usesPurchaseRequisitionPicker && poAmountEvaluation?.blocked) {
      const openBalance = selectedPurchaseRequisition?.open_balance;
      if (openBalance == null) {
        return null;
      }

      return `PO total exceeds the tenant overspend policy maximum.`;
    }

    return null;
  }, [
    poAmountEvaluation?.blocked,
    selectedParentOpenBalance,
    selectedPurchaseRequisition?.open_balance,
    computedValues.total_reimbursement,
    usesCashAdvancePicker,
    usesPurchaseRequisitionPicker,
  ]);

  const overspendWarning = poAmountEvaluation?.warning ?? null;

  const balanceFieldName = usesPurchaseRequisitionPicker
    ? "total_amount"
    : usesCashAdvancePicker
      ? "total_reimbursement"
      : null;

  const validateParentLink = useCallback((): string | null => {
    if (!requiresParentSubmission) {
      return null;
    }
    if (!parentSubmissionId) {
      return `Select an approved ${parentLinkLabel} before continuing.`;
    }

    return balanceError;
  }, [balanceError, parentLinkLabel, parentSubmissionId, requiresParentSubmission]);

  const displayedFieldErrors = useMemo(() => {
    if (!balanceError || !balanceFieldName) {
      return fieldErrors;
    }

    return {
      ...fieldErrors,
      [balanceFieldName]: balanceError,
    };
  }, [balanceError, balanceFieldName, fieldErrors]);

  const fieldHelpOverrides = useMemo(() => {
    const overrides: Record<string, string> = {
      ...(controlledDocumentFieldHelp(controlledDocumentSync) ?? {}),
    };

    if (formMetadata?.use_approval_policy === true && requiredApproverFieldNames) {
      for (const field of fields) {
        if (field.type !== "approver") {
          continue;
        }

        if (requiredApproverFieldNames.has(field.name)) {
          overrides[field.name] = "Required for the approval policy matched by this request.";
        } else if (parseFieldValidation(field).required) {
          overrides[field.name] = "Optional for the current total. Included when the high-value policy applies.";
        }
      }
    }

    if (selectedParentOpenBalance != null && balanceFieldName) {
      if (balanceFieldName === "total_reimbursement") {
        overrides.total_reimbursement = `Maximum liquidation amount for this cash advance: ${formatSubmissionAmount(selectedParentOpenBalance)}.`;
      } else if (poAmountEvaluation?.helpText) {
        overrides.total_amount = poAmountEvaluation.helpText;
      } else {
        overrides.total_amount = `Maximum PO amount for this purchase requisition: ${formatSubmissionAmount(selectedParentOpenBalance)}.`;
      }
    }

    return Object.keys(overrides).length > 0 ? overrides : undefined;
  }, [
    balanceFieldName,
    controlledDocumentSync,
    fields,
    formMetadata?.use_approval_policy,
    poAmountEvaluation?.helpText,
    requiredApproverFieldNames,
    selectedParentOpenBalance,
  ]);

  const hasBalanceBlocker = Boolean(balanceError);

  const applyParentPrefill = useCallback(
    (prefillValues: Record<string, string | null | undefined> | undefined, documentNo: string, documentField: string) => {
      setValues((prev) => {
        const next = applyParentPrefillValues(prev, prefillValues);
        next[documentField] = documentNo;
        return applyComputedFieldValues(fields, next);
      });
    },
    [fields],
  );

  const handleCashAdvanceSelect = useCallback(
    (item: EApprovalOpenCashAdvance | null) => {
      userEditedRef.current = true;
      setParentSubmissionId(item?.id ?? null);
      parentPrefillAppliedRef.current = item?.id ?? null;
      setFieldErrors((prev) => {
        const next = { ...prev };
        delete next.parent_submission_id;
        delete next.total_reimbursement;
        delete next.cash_advance_document_no;
        delete next._form;
        return next;
      });

      if (!item) {
        setValues((prev) => ({ ...prev, cash_advance_document_no: "" }));
        return;
      }

      applyParentPrefill(item.prefill_values, item.document_no, "cash_advance_document_no");
    },
    [applyParentPrefill],
  );

  const handlePurchaseRequisitionSelect = useCallback(
    (item: EApprovalOpenPurchaseRequisition | null) => {
      userEditedRef.current = true;
      setParentSubmissionId(item?.id ?? null);
      parentPrefillAppliedRef.current = item?.id ?? null;
      setFieldErrors((prev) => {
        const next = { ...prev };
        delete next.parent_submission_id;
        delete next.total_amount;
        delete next._form;
        return next;
      });

      if (!item) {
        return;
      }

      applyParentPrefill(item.prefill_values, item.document_no, "purchase_requisition_document_no");
    },
    [applyParentPrefill],
  );

  useEffect(() => {
    if (!hydratedRef.current || userEditedRef.current || !parentSubmissionId) {
      return;
    }
    if (parentPrefillAppliedRef.current === parentSubmissionId) {
      return;
    }

    const cashAdvance = openCashAdvancesQuery.data?.find((entry) => entry.id === parentSubmissionId);
    if (cashAdvance) {
      parentPrefillAppliedRef.current = parentSubmissionId;
      applyParentPrefill(cashAdvance.prefill_values, cashAdvance.document_no, "cash_advance_document_no");
      return;
    }

    const purchaseRequisition = openPurchaseRequisitionsQuery.data?.find((entry) => entry.id === parentSubmissionId);
    if (purchaseRequisition) {
      parentPrefillAppliedRef.current = parentSubmissionId;
      applyParentPrefill(
        purchaseRequisition.prefill_values,
        purchaseRequisition.document_no,
        "purchase_requisition_document_no",
      );
    }
  }, [applyParentPrefill, openCashAdvancesQuery.data, openPurchaseRequisitionsQuery.data, parentSubmissionId]);

  const removeSavedAttachmentMutation = useMutation({
    mutationFn: (attachmentId: string) => deleteEApprovalAttachment(attachmentId),
    onSuccess: () => {
      if (isResubmitMode && resubmitSubmissionId) {
        queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", resubmitSubmissionId] });
      } else {
        queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "my-draft"] });
      }
      push({ level: "success", title: "Attachment removed" });
    },
    onError: (error) => {
      push({ level: "error", title: "Could not remove attachment", message: getErrorMessage(error) });
    },
  });

  const saveDraftMutation = useMutation({
    mutationFn: async () => {
      if (isResubmitMode) {
        throw new Error("Drafts are not available while revising a returned submission.");
      }
      let submission;
      if (draftSubmissionId) {
        submission = await updateEApprovalSubmissionDraft(draftSubmissionId, computedValues, parentWriteOptions);
      } else {
        submission = await createEApprovalSubmission(formId, computedValues, { asDraft: true, ...parentWriteOptions });
      }

      const pendingUploads = pendingAttachmentsNotYetSaved(
        attachmentFiles,
        draftQuery.data?.attachments ?? submission.attachments ?? [],
      );

      if (hasPendingAttachmentFiles(pendingUploads)) {
        await uploadEApprovalSubmissionAttachmentsOrThrow(submission.id, pendingUploads, cameraMetadataByField);
        setAttachmentFiles({});
        setCameraMetadataByField({});
      }

      return submission;
    },
    onSuccess: (submission, opts) => {
      setDraftSubmissionId(submission.id);
      setParentSubmissionId(submission.parent_submission_id ?? parentSubmissionId);
      setDraftSavedAt(new Date());
      writeLocalComposeDraft(formId, computedValues, submission.parent_submission_id ?? parentSubmissionId);
      // Update cache in place — avoid invalidate/refetch tearing down the stepped form.
      queryClient.setQueryData(["e-approval", "form", formId, "my-draft"], submission);
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submissions"] });
      if (!opts?.silent) {
        push({
          level: "success",
          title: "Draft saved",
          message: submission.document_no,
        });
      }
    },
    onError: (error, opts) => {
      const message = getErrorMessage(error);
      const attachmentFieldMatch = message.match(/^([a-z0-9_]+):/i);
      if (attachmentFieldMatch) {
        setFieldErrors((prev) => ({ ...prev, [attachmentFieldMatch[1]!]: message }));
        scrollToFirstFieldError(attachmentFieldMatch[1]!);
      }
      if (!opts?.silent) {
        push({
          level: "error",
          title: attachmentFieldMatch ? "Draft saved but file upload failed" : "Could not save draft",
          message,
        });
      }
    },
  });

  const submitMutation = useMutation({
    mutationFn: async () => {
      const existingAttachments = activeSubmissionAttachments;
      const pendingUploads = pendingAttachmentsNotYetSaved(attachmentFiles, existingAttachments);
      const hasPendingAttachments = hasPendingAttachmentFiles(pendingUploads);

      if (isResubmitMode && resubmitSubmissionId) {
        if (hasPendingAttachments) {
          await uploadEApprovalSubmissionAttachmentsOrThrow(
            resubmitSubmissionId,
            pendingUploads,
            cameraMetadataByField,
          );
          setAttachmentFiles({});
          setCameraMetadataByField({});
          await queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", resubmitSubmissionId] });
        }
        const submission = await resubmitEApprovalSubmission(resubmitSubmissionId, computedValues);
        return { submission, failedUploads: [] as string[] };
      }

      if (hasPendingAttachments) {
        let submissionId = draftSubmissionId;

        if (submissionId) {
          await updateEApprovalSubmissionDraft(submissionId, computedValues, parentWriteOptions);
        } else {
          const draft = await createEApprovalSubmission(formId, computedValues, {
            asDraft: true,
            ...parentWriteOptions,
          });
          submissionId = draft.id;
          setDraftSubmissionId(draft.id);
        }

        await uploadEApprovalSubmissionAttachmentsOrThrow(submissionId, pendingUploads, cameraMetadataByField);
        setAttachmentFiles({});
        setCameraMetadataByField({});

        const submission = await submitEApprovalSubmissionDraft(
          submissionId,
          computedValues,
          parentWriteOptions,
        );

        return { submission, failedUploads: [] as string[] };
      }

      const submission = draftSubmissionId
        ? await submitEApprovalSubmissionDraft(draftSubmissionId, computedValues, parentWriteOptions)
        : await createEApprovalSubmission(formId, computedValues, parentWriteOptions);

      return { submission, failedUploads: [] as string[] };
    },
    onSuccess: ({ submission, failedUploads }) => {
      clearLocalComposeDraft(formId);
      setAttachmentFiles({});
      queryClient.invalidateQueries({ queryKey: ["e-approval", "notifications"] });
      queryClient.invalidateQueries({ queryKey: ["tenant", "notifications"] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submissions"] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "my-draft"] });
      if (resubmitSubmissionId) {
        queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", resubmitSubmissionId] });
      }

      if (shouldNotify) {
        push({
          level: "success",
          title: isResubmitMode ? "Request resubmitted" : "Submission sent",
          message: isResubmitMode
            ? `${submission.document_no} is back in the approval workflow.`
            : `${submission.document_no} — approvers have been notified.`,
        });
      }

      onSubmitted({ submission, failedUploads });
    },
    onError: (error) => {
      const message = getErrorMessage(error);
      const attachmentFieldMatch = message.match(/^([a-z0-9_]+):/i);
      if (attachmentFieldMatch) {
        setFieldErrors((prev) => ({ ...prev, [attachmentFieldMatch[1]!]: message }));
        scrollToFirstFieldError(attachmentFieldMatch[1]!);
      } else if (message.toLowerCase().includes("could not be uploaded")) {
        setFieldErrors((prev) => ({ ...prev, _form: message }));
      }

      const title = message.toLowerCase().includes("could not be uploaded")
        ? "Submit failed — attachments not uploaded"
        : isResubmitMode
          ? "Resubmit failed"
          : "Submit failed";

      push({ level: "error", title, message });
    },
  });

  const scrollToFirstFieldError = useCallback((fieldName: string) => {
    document.getElementById(`ea-field-${fieldName}`)?.scrollIntoView({ behavior: "smooth", block: "center" });
  }, []);

  const handleSubmit = () => {
    if (requiresParentSubmission && !parentSubmissionId) {
      const message = `Select an approved ${parentLinkLabel} before submitting.`;
      setFieldErrors({ parent_submission_id: message });
      push({ level: "warning", title: `${parentLinkTitle} required`, message });
      return;
    }

    const validationIssues = validateSubmissionValues(fields, computedValues, attachmentFiles, {
      approverOptions,
      requiredApproverFieldNames,
      existingAttachmentCountsByField: draftExistingAttachmentCounts,
      cameraMetadataByField,
    });
    if (validationIssues.length > 0) {
      const map: Record<string, string> = {};
      for (const issue of validationIssues) {
        map[issue.fieldName] = issue.message;
      }
      setFieldErrors(map);
      const count = validationIssues.length;
      push({
        level: "warning",
        title: count === 1 ? "1 field needs attention" : `${count} fields need attention`,
        message: "Required and invalid fields are highlighted below.",
      });
      scrollToFirstFieldError(validationIssues[0]!.fieldName);
      return;
    }

    const duplicateApproverError = validateDuplicateApproverFields(fields, computedValues);
    if (duplicateApproverError) {
      setFieldErrors({ _form: duplicateApproverError });
      push({ level: "warning", title: "Approver selection", message: duplicateApproverError });
      return;
    }

    const parentLinkError = validateParentLink();
    if (parentLinkError) {
      const errorKey =
        balanceFieldName && parentLinkError.toLowerCase().includes("balance") ? balanceFieldName : "parent_submission_id";
      setFieldErrors({ [errorKey]: parentLinkError });
      push({ level: "warning", title: `${parentLinkTitle} link`, message: parentLinkError });
      if (errorKey !== "parent_submission_id") {
        scrollToFirstFieldError(errorKey);
      }
      return;
    }

    const controlledDocumentError = validateControlledDocumentRequest(
      controlledDocumentSync,
      controlledDocumentRequestMode,
      computedValues,
    );
    if (controlledDocumentError) {
      setFieldErrors({ [controlledDocumentError.fieldName]: controlledDocumentError.message });
      push({
        level: "warning",
        title: "Document code required",
        message: controlledDocumentError.message,
      });
      scrollToFirstFieldError(controlledDocumentError.fieldName);
      return;
    }

    setFieldErrors({});
    submitMutation.mutate();
  };

  const handleSaveDraft = () => {
    if (requiresParentSubmission && !parentSubmissionId) {
      const message = `Select an approved ${parentLinkLabel} before saving a draft.`;
      setFieldErrors({ parent_submission_id: message });
      push({ level: "warning", title: `${parentLinkTitle} required`, message });
      return;
    }

    const parentLinkError = validateParentLink();
    if (parentLinkError) {
      const errorKey =
        balanceFieldName && parentLinkError.toLowerCase().includes("balance") ? balanceFieldName : "parent_submission_id";
      setFieldErrors({ [errorKey]: parentLinkError });
      push({ level: "warning", title: `${parentLinkTitle} link`, message: parentLinkError });
      return;
    }

    saveDraftMutation.mutate({ silent: false });
  };

  useEffect(() => {
    if (isResubmitMode) {
      return;
    }
    if (!hydratedRef.current || !formQuery.data?.fields || !userEditedRef.current) {
      return;
    }
    writeLocalComposeDraft(formId, computedValues, parentSubmissionId);
    if (requiresParentSubmission && !parentSubmissionId) {
      return;
    }
    if (hasBalanceBlocker) {
      return;
    }
    const timer = window.setTimeout(() => {
      if (saveDraftMutation.isPending || submitMutation.isPending) {
        return;
      }
      saveDraftMutation.mutate({ silent: true });
    }, 4000);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- debounced autosave
  }, [computedValues, formId, draftSubmissionId, parentSubmissionId, requiresParentSubmission, hasBalanceBlocker, isResubmitMode]);

  const showRevisionSummaryBanner =
    Boolean(controlledDocumentSync) &&
    controlledDocumentRequestMode === "revision" &&
    Boolean(controlledDocumentCode);

  const savedLabel = formatSavedAt(draftSavedAt);
  const isBusy = saveDraftMutation.isPending || submitMutation.isPending;

  const formBody = (
    <>
      {isResubmitMode && resubmitQuery.data?.revision_remarks ? (
        <OperationalAlert
          level="warning"
          title={
            resubmitQuery.data.status === "rejected"
              ? "Rejection remarks"
              : "Revision requested"
          }
          description={
            <>
              <p className="whitespace-pre-wrap">{resubmitQuery.data.revision_remarks}</p>
              {resubmitQuery.data.revision_remarks_by || resubmitQuery.data.revision_remarks_at ? (
                <p className="mt-2 text-xs text-muted-foreground">
                  {[resubmitQuery.data.revision_remarks_by, resubmitQuery.data.revision_remarks_at
                    ? new Date(resubmitQuery.data.revision_remarks_at).toLocaleString()
                    : null]
                    .filter(Boolean)
                    .join(" · ")}
                </p>
              ) : null}
            </>
          }
        />
      ) : null}
      {isResubmitMode && resubmitQuery.isError ? (
        <OperationalAlert
          level="error"
          title="Could not load this submission"
          description={
            getErrorMessage(resubmitQuery.error) ||
            "It may no longer be available for revision, or you do not have access."
          }
        />
      ) : null}
      {isResubmitMode &&
      resubmitQuery.data &&
      resubmitQuery.data.status !== "returned" &&
      resubmitQuery.data.status !== "rejected" ? (
        <OperationalAlert
          level="warning"
          title="This submission cannot be revised"
          description={`Current status is “${resubmitQuery.data.status}”. Only returned or rejected requests can be edited and resubmitted.`}
        />
      ) : null}
      {formQuery.data?.description ? (
        <p className="rounded-lg border border-border bg-muted/20 px-3 py-2 text-sm text-muted-foreground">
          {formQuery.data.description}
        </p>
      ) : null}
      {formQuery.isLoading ||
      (isResubmitMode
        ? resubmitQuery.isLoading && !resubmitQuery.data
        : draftQuery.isLoading && !draftQuery.data) ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, index) => (
            <div key={index} className="h-14 animate-pulse rounded-lg bg-muted/50" />
          ))}
        </div>
      ) : formQuery.isError ? (
        <OperationalAlert
          level="error"
          title="Could not load this form"
          description={
            getErrorMessage(formQuery.error) ||
            "It may be unpublished or unavailable. Confirm the API is running and you have access."
          }
        />
      ) : formQuery.data?.status === "draft" ? (
        <p className="text-sm text-destructive">This form is not published yet. Contact your administrator.</p>
      ) : fields.length === 0 ? (
        <p className="text-sm text-muted-foreground">This form has no fields yet.</p>
      ) : isResubmitMode &&
        (!resubmitQuery.data ||
          (resubmitQuery.data.status !== "returned" && resubmitQuery.data.status !== "rejected")) ? null : (
        <>
          {usesCashAdvancePicker ? (
            <EApprovalCashAdvancePicker
              formId={formId}
              value={parentSubmissionId}
              onChange={handleCashAdvanceSelect}
              error={fieldErrors.parent_submission_id}
              enabled={enabled && !!formId}
            />
          ) : null}
          {usesPurchaseRequisitionPicker ? (
            <EApprovalPurchaseRequisitionPicker
              formId={formId}
              value={parentSubmissionId}
              onChange={handlePurchaseRequisitionSelect}
              error={fieldErrors.parent_submission_id}
              enabled={enabled && !!formId}
            />
          ) : null}
          {showControlledDocumentRegistryUi ? (
            <ControlledDocumentRequestModePicker
              mode={controlledDocumentRequestMode}
              onChange={handleControlledDocumentRequestModeChange}
              disabled={isBusy}
            />
          ) : null}
          {showControlledDocumentRegistryUi && controlledDocumentRequestMode === "revision" ? (
            <ControlledDocumentPicker
              documentCode={controlledDocumentCode}
              onDocumentCodeChange={handleControlledDocumentCodeChange}
              onLookupResolved={handleControlledDocumentLookupResolved}
              disabled={isBusy}
            />
          ) : null}
          {controlledDocumentSync &&
          controlledDocumentRequestMode === "revision" &&
          controlledDocumentCode ? (
            <div className="rounded-xl border border-border bg-muted/30 px-4 py-3.5 text-sm">
              <div className="flex items-start gap-2.5">
                <GitBranch className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <div className="min-w-0 flex-1">
                  <p className="font-medium text-foreground">
                    Revision request —{" "}
                    <code className="rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[11px] text-primary">
                      {controlledDocumentCode}
                    </code>
                  </p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Title, document type, department, and revision number are pre-filled from the registry.
                  </p>
                  <div className="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    <span className="flex items-center gap-1 text-muted-foreground">
                      <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                      Pre-filled from registry
                    </span>
                    <span className="flex items-center gap-1 font-medium text-foreground">
                      <AlertCircle className="h-3 w-3 text-amber-500" />
                      Please fill: Effective date · Details of change · File attachment
                    </span>
                  </div>
                </div>
              </div>
            </div>
          ) : null}
          {needsApproverOptions && usersQuery.isError ? (
            <OperationalAlert
              level="error"
              title="Approver list unavailable"
              description="Could not load the user list for approver fields."
            />
          ) : null}
          {duplicateApproverWarning ? (
            <OperationalAlert level="warning" title="Duplicate approvers" description={duplicateApproverWarning} />
          ) : null}
          {hasBalanceBlocker ? (
            <OperationalAlert
              level="warning"
              title="Amount exceeds open balance"
              description={
                balanceError ??
                (usesPurchaseRequisitionPicker
                  ? "Reduce the PO total before saving or submitting."
                  : "Reduce the liquidation amount before saving or submitting.")
              }
            />
          ) : null}
          {!hasBalanceBlocker && overspendWarning ? (
            <OperationalAlert level="warning" title="Overspend policy warning" description={overspendWarning} />
          ) : null}
          {fieldErrors._form ? (
            <OperationalAlert level="warning" title="Cannot submit yet" description={fieldErrors._form} />
          ) : null}
          {showSectionProgress ? (
            <EApprovalFormSectionProgressNav sections={sectionProgress} className="sticky top-0 z-10" />
          ) : null}
          <div className={cn("rounded-xl border border-border bg-card shadow-sm", focused ? "p-5 sm:p-6" : "p-4")}>
            <EApprovalComposeFormFields
              fields={composeFields}
              values={computedValues}
              fieldErrors={displayedFieldErrors}
              fieldHelpOverrides={fieldHelpOverrides}
              formMetadata={formMetadata}
              composeConfig={composeConfig}
              validationOptions={{
                approverOptions,
                requiredApproverFieldNames,
                existingAttachmentCountsByField: draftExistingAttachmentCounts,
                cameraMetadataByField,
              }}
              currentStep={composeCurrentStep}
              onCurrentStepChange={setComposeCurrentStep}
              onStepMetaChange={setComposeStepMeta}
              onStepValidationIssues={(issues) => {
                const map: Record<string, string> = {};
                for (const issue of issues) {
                  map[issue.fieldName] = issue.message;
                }
                setFieldErrors(map);
                if (issues[0]) {
                  scrollToFirstFieldError(issues[0].fieldName);
                }
              }}
              onChange={(name, value) => {
                userEditedRef.current = true;
                if (controlledDocumentSync?.revisionFieldName === name) {
                  revisionManualRef.current = true;
                }
                if (controlledDocumentSync?.documentCodeField === name) {
                  revisionManualRef.current = false;
                }
                setValues((prev) => {
                  const patch = procurementLinkCascadePatch(name, value);
                  return applyComputedFieldValues(fields, { ...prev, ...patch });
                });
                setFieldErrors((prev) => {
                  const next = { ...prev };
                  delete next[name];
                  delete next._form;
                  for (const key of Object.keys(procurementLinkCascadePatch(name, value))) {
                    delete next[key];
                  }
                  return next;
                });
              }}
              fileSelections={attachmentFiles}
              cameraMetadataByField={cameraMetadataByField}
              existingAttachmentsByField={draftExistingAttachmentsByField}
              onRemoveSavedAttachment={(attachmentId) => removeSavedAttachmentMutation.mutateAsync(attachmentId)}
              removingSavedAttachmentId={removeSavedAttachmentMutation.isPending ? removeSavedAttachmentMutation.variables ?? null : null}
              onFileChange={(name, files) =>
                setAttachmentFiles((prev) => {
                  const next = { ...prev };
                  if (files.length === 0) {
                    delete next[name];
                  } else {
                    next[name] = files;
                  }
                  return next;
                })
              }
              onCameraChange={(name, files, metadataByName) => {
                setAttachmentFiles((prev) => {
                  const next = { ...prev };
                  if (files.length === 0) {
                    delete next[name];
                  } else {
                    next[name] = files;
                  }
                  return next;
                });
                setCameraMetadataByField((prev) => {
                  const next = { ...prev };
                  if (files.length === 0) {
                    delete next[name];
                  } else {
                    next[name] = metadataByName;
                  }
                  return next;
                });
              }}
              approverOptions={approverOptions}
              approverOptionsLoading={needsApproverOptions && usersQuery.isLoading}
              density="comfortable"
              planFeaturesOverride={metadataQuery.data?.plan_features}
              prefillReadOnlyFields={
                controlledDocumentRequestMode === "revision" && prefillReadOnlyFields.size > 0
                  ? prefillReadOnlyFields
                  : undefined
              }
              hidePrefillFieldBadges={showRevisionSummaryBanner}
            />
          </div>
        </>
      )}
    </>
  );

  const actions = (
    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <p className="text-xs text-muted-foreground">
        {composeStepMeta.stepped && !composeStepMeta.isLastStep
          ? `Complete step ${composeStepMeta.currentStep + 1} of ${composeStepMeta.totalSteps} to submit.`
          : isResubmitMode
            ? "Update the fields as needed, then resubmit into the approval workflow."
            : saveDraftMutation.isPending
              ? "Saving draft…"
              : savedLabel
                ? `Draft saved at ${savedLabel}`
                : "Progress autosaves locally and to the server."}
      </p>
      <div className="flex flex-wrap justify-end gap-2">
        {onCancel ? (
          <Button type="button" variant="outline" onClick={onCancel} disabled={isBusy}>
            Cancel
          </Button>
        ) : null}
        {!isResubmitMode ? (
          <Button
            type="button"
            variant="outline"
            onClick={handleSaveDraft}
            disabled={isBusy || formQuery.isLoading || hasBalanceBlocker}
          >
            Save draft
          </Button>
        ) : null}
        <Button
          type="button"
          onClick={handleSubmit}
          disabled={
            formQuery.isLoading ||
            formQuery.isError ||
            formQuery.data?.status === "draft" ||
            isBusy ||
            Boolean(duplicateApproverWarning) ||
            hasBalanceBlocker ||
            (composeStepMeta.stepped && !composeStepMeta.isLastStep) ||
            (isResubmitMode &&
              (!resubmitQuery.data ||
                (resubmitQuery.data.status !== "returned" && resubmitQuery.data.status !== "rejected")))
          }
        >
          {submitMutation.isPending
            ? isResubmitMode
              ? "Resubmitting…"
              : "Submitting…"
            : isResubmitMode
              ? "Resubmit request"
              : "Submit request"}
        </Button>
      </div>
    </div>
  );

  if (fullPage) {
    return (
      <div
        className={cn(
          "w-full space-y-4",
          focused ? shellClassName ?? "w-full" : shellClassName ?? E_APPROVAL_WIDE_FORM_MAX_WIDTH_CLASS,
          !focused && !shellClassName && "mx-auto",
        )}
      >
        {formBody}
        <div className="sticky bottom-0 border-t border-border bg-background/95 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/80">
          {actions}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {formBody}
      {actions}
    </div>
  );
}
