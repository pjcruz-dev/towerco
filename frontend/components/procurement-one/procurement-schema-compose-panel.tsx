"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { EApprovalPurchaseRequisitionPicker } from "@/components/e-approval/e-approval-purchase-requisition-picker";
import { EApprovalFormFieldsLayout } from "@/components/e-approval/e-approval-form-fields-layout";
import { EApprovalFormSectionProgressNav } from "@/components/e-approval/e-approval-form-section-progress";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Button } from "@/components/ui/button";
import { fetchEApprovalMetadata, fetchEApprovalOpenPurchaseRequisitions, fetchEApprovalApprovalPolicy, uploadEApprovalSubmissionAttachment } from "@/lib/api/modules/e-approval-api";
import {
  createProcurementApInvoiceFromPoWithValues,
  createProcurementPoFromPrWithValues,
  createProcurementPoFromValues,
  createProcurementPrFromValues,
  fetchProcurementFormSchema,
  submitProcurementApInvoice,
  submitProcurementPo,
  submitProcurementPr,
  updateProcurementApInvoiceFromValues,
  updateProcurementPoFromValues,
  updateProcurementPrFromValues,
  uploadProcurementPrAttachment,
} from "@/lib/api/modules/procurement-one-api";
import { getApiFieldErrors, getErrorMessage } from "@/lib/api/error";
import {
  mapEApprovalAssignableUsersToOptions,
  useEApprovalAssignableUsers,
} from "@/hooks/use-e-approval-assignable-users";
import { validateDuplicateApproverFields } from "@/modules/e-approval/display";
import {
  applyApproverFieldDefaults,
  normalizeApproverFieldValues,
  requiredApproverFieldNamesForSubmit,
} from "@/modules/e-approval/approver-field-support";
import { applyComputedFieldValues } from "@/modules/e-approval/field-computed";
import { normalizeComposeInitialValues } from "@/modules/e-approval/field-options";
import { fieldDefaultValue, parseFieldValidation, validateSubmissionValues } from "@/modules/e-approval/field-validation";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import {
  buildFormSectionProgress,
  shouldShowFormSectionProgress,
} from "@/modules/e-approval/form-section-progress";
import {
  applyParentPrefillValues,
  evaluatePurchaseOrderAmountWithPolicy,
  formRequiresParentSubmission,
  formUsesPurchaseRequisitionParentPicker,
  formatSubmissionAmount,
  parentSubmissionLinkLabel,
  parentSubmissionLinkTitle,
  parseSubmissionAmount,
} from "@/modules/e-approval/parent-submission-link";
import type { EApprovalFormFieldInput, EApprovalOpenPurchaseRequisition } from "@/modules/e-approval/types";
import { procurementLinkCascadePatch } from "@/modules/e-approval/procurement-link-fields";
import type { ProcurementDocumentKind, ProcurementPrAttachment } from "@/modules/procurement-one/types";
import { attachmentCountsByField, missingRequiredAttachmentFieldLabels } from "@/modules/procurement-one/submit-readiness";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = {
  kind: ProcurementDocumentKind;
  mode: "create" | "edit";
  documentId?: string;
  prId?: string;
  poId?: string;
  initialValues?: Record<string, string>;
  existingAttachments?: ProcurementPrAttachment[];
  lockedParentSubmissionId?: string | null;
  onSaved: (result: { id: string; kind: ProcurementDocumentKind }) => void;
  onCancel?: () => void;
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

export function ProcurementSchemaComposePanel({
  kind,
  mode,
  documentId,
  prId,
  poId,
  initialValues,
  existingAttachments = [],
  lockedParentSubmissionId,
  onSaved,
  onCancel,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const [values, setValues] = useState<Record<string, string>>({});
  const [attachmentFiles, setAttachmentFiles] = useState<Record<string, File[]>>({});
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [parentSubmissionId, setParentSubmissionId] = useState<string | null>(lockedParentSubmissionId ?? null);
  const [savedDocumentId, setSavedDocumentId] = useState<string | undefined>(documentId);
  const hydratedRef = useRef(false);

  const schemaQuery = useQuery({
    queryKey: ["procurement-one", "form-schema", kind],
    queryFn: () => fetchProcurementFormSchema(kind),
  });

  const formMetadata = schemaQuery.data?.form?.metadata ?? null;
  const formId = schemaQuery.data?.form?.id ?? "";
  const usesPurchaseRequisitionPicker =
    formUsesPurchaseRequisitionParentPicker(formMetadata) &&
    lockedParentSubmissionId == null &&
    !(mode === "edit" && kind === "purchase_order" && Boolean(documentId));

  const openPurchaseRequisitionsQuery = useQuery({
    queryKey: ["e-approval", "purchase-requisitions", "open", formId, "procurement"],
    queryFn: () => fetchEApprovalOpenPurchaseRequisitions(formId, { scope: "procurement" }),
    enabled: usesPurchaseRequisitionPicker && !!formId,
    staleTime: 30_000,
  });

  const metadataQuery = useQuery({
    queryKey: ["e-approval", "metadata"],
    queryFn: () => fetchEApprovalMetadata(),
    staleTime: 60_000,
  });

  const approvalPolicyQuery = useQuery({
    queryKey: ["e-approval", "approval-policies"],
    queryFn: () => fetchEApprovalApprovalPolicy(),
    enabled: formMetadata?.use_approval_policy === true,
    staleTime: 60_000,
  });

  const usersQuery = useEApprovalAssignableUsers(true);

  const fields = (schemaQuery.data?.fields ?? []) as EApprovalFormFieldInput[];

  const composeFields = useMemo(() => {
    if (!usesPurchaseRequisitionPicker) {
      return fields;
    }

    return fields.filter((field) => field.name !== "purchase_requisition_document_no");
  }, [fields, usesPurchaseRequisitionPicker]);

  useEffect(() => {
    hydratedRef.current = false;
    setSavedDocumentId(documentId);
    setParentSubmissionId(lockedParentSubmissionId ?? null);
    setFieldErrors({});
  }, [documentId, kind, mode, lockedParentSubmissionId]);

  useEffect(() => {
    if (!schemaQuery.data?.fields || hydratedRef.current) {
      return;
    }

    const base = buildInitialValues(schemaQuery.data.fields as EApprovalFormFieldInput[]);
    const merged = { ...base, ...(initialValues ?? {}) };
    const fieldList = schemaQuery.data.fields as EApprovalFormFieldInput[];
    setValues(normalizeComposeInitialValues(fieldList, merged));
    hydratedRef.current = true;
  }, [initialValues, schemaQuery.data?.fields]);

  const existingAttachmentCountsByField = useMemo(
    () => attachmentCountsByField(existingAttachments),
    [existingAttachments],
  );

  const missingRequiredAttachments = useMemo(
    () => missingRequiredAttachmentFieldLabels(fields, existingAttachments),
    [existingAttachments, fields],
  );

  const approverOptions = useMemo(
    () => mapEApprovalAssignableUsersToOptions(usersQuery.data),
    [usersQuery.data],
  );

  useEffect(() => {
    if (!hydratedRef.current || fields.length === 0 || approverOptions.length === 0) {
      return;
    }

    setValues((prev) => applyApproverFieldDefaults(fields, prev, approverOptions));
  }, [approverOptions, fields]);

  const computedValues = useMemo(() => {
    const withComputed = fields.length > 0 ? applyComputedFieldValues(fields, values) : values;

    return approverOptions.length > 0
      ? normalizeApproverFieldValues(fields, withComputed, approverOptions)
      : withComputed;
  }, [approverOptions, fields, values]);

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

  const requiresParentSubmission = formRequiresParentSubmission(formMetadata);
  const parentLinkLabel = parentSubmissionLinkLabel(formMetadata);
  const parentLinkTitle = parentSubmissionLinkTitle(formMetadata);
  const procurementPolicy = metadataQuery.data?.finance_procurement_policy;
  const planFeatures = metadataQuery.data?.plan_features;

  const selectedPurchaseRequisition = useMemo(
    () => openPurchaseRequisitionsQuery.data?.find((item) => item.id === parentSubmissionId) ?? null,
    [openPurchaseRequisitionsQuery.data, parentSubmissionId],
  );

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
    if (usesPurchaseRequisitionPicker && poAmountEvaluation?.blocked) {
      return "PO total exceeds the tenant overspend policy maximum.";
    }

    return null;
  }, [poAmountEvaluation?.blocked, usesPurchaseRequisitionPicker]);

  const sectionProgress = useMemo(
    () => buildFormSectionProgress(composeFields, computedValues),
    [composeFields, computedValues],
  );
  const showSectionProgress = shouldShowFormSectionProgress(sectionProgress);

  const needsApproverOptions = fields.some((field) => field.type === "approver");

  const fieldHelpOverrides = useMemo(() => {
    const overrides: Record<string, string> = {};

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

    if (selectedPurchaseRequisition?.open_balance != null && usesPurchaseRequisitionPicker) {
      overrides.total_amount =
        poAmountEvaluation?.helpText
        ?? `Maximum PO amount for this purchase requisition: ${formatSubmissionAmount(selectedPurchaseRequisition.open_balance)}.`;
    }

    return Object.keys(overrides).length > 0 ? overrides : undefined;
  }, [
    fields,
    formMetadata?.use_approval_policy,
    kind,
    poAmountEvaluation?.helpText,
    requiredApproverFieldNames,
    selectedPurchaseRequisition?.open_balance,
    usesPurchaseRequisitionPicker,
  ]);

  const duplicateApproverWarning = useMemo(
    () => validateDuplicateApproverFields(fields, computedValues),
    [computedValues, fields],
  );

  const applyParentPrefill = useCallback(
    (prefillValues: Record<string, string | null | undefined> | undefined, documentNo: string) => {
      const prefill = {
        ...(prefillValues ?? {}),
        purchase_requisition_document_no: documentNo,
      };
      setValues((prev) => applyParentPrefillValues(prev, prefill));
    },
    [],
  );

  const handlePurchaseRequisitionSelect = useCallback(
    (item: EApprovalOpenPurchaseRequisition | null) => {
      setParentSubmissionId(item?.id ?? null);
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

      applyParentPrefill(item.prefill_values, item.document_no);
    },
    [applyParentPrefill],
  );

  const persistMutation = useMutation({
    mutationFn: async (opts: { submit: boolean; requireRequired: boolean }) => {
      const issues = opts.requireRequired
        ? validateSubmissionValues(fields, computedValues, attachmentFiles, {
            approverOptions,
            requiredApproverFieldNames: opts.requireRequired ? requiredApproverFieldNames : null,
            existingAttachmentCountsByField,
          })
        : [];
      if (issues.length > 0) {
        const map: Record<string, string> = {};
        for (const issue of issues) {
          map[issue.fieldName] = issue.message;
        }
        setFieldErrors(map);
        throw new Error(issues[0]?.message ?? "Validation failed");
      }

      if (
        requiresParentSubmission &&
        !parentSubmissionId &&
        !lockedParentSubmissionId &&
        kind === "purchase_order" &&
        !prId &&
        mode !== "edit"
      ) {
        throw new Error(`Select an approved ${parentLinkLabel} before continuing.`);
      }

      if (balanceError) {
        throw new Error(balanceError);
      }

      const duplicateApproverError = validateDuplicateApproverFields(fields, computedValues);
      if (duplicateApproverError) {
        throw new Error(duplicateApproverError);
      }

      const targetId = savedDocumentId;
      let id = targetId ?? "";

      const uploadEApprovalAttachments = async (submissionId: string): Promise<void> => {
        if (Object.keys(attachmentFiles).length === 0) {
          return;
        }

        const failedUploads: string[] = [];
        for (const [fieldName, files] of Object.entries(attachmentFiles)) {
          for (const file of files) {
            try {
              await uploadEApprovalSubmissionAttachment(submissionId, file, fieldName);
            } catch (uploadError) {
              failedUploads.push(`${fieldName}: ${getErrorMessage(uploadError)}`);
            }
          }
        }

        if (failedUploads.length > 0) {
          throw new Error(
            opts.submit
              ? `Some files could not be uploaded before submit: ${failedUploads.join("; ")}`
              : `Saved draft, but some files could not be uploaded: ${failedUploads.join("; ")}`,
          );
        }
      };

      const uploadPurchaseRequisitionAttachments = async (prId: string): Promise<void> => {
        if (Object.keys(attachmentFiles).length === 0) {
          return;
        }

        const failedUploads: string[] = [];
        for (const [fieldName, files] of Object.entries(attachmentFiles)) {
          for (const file of files) {
            try {
              await uploadProcurementPrAttachment(prId, file, fieldName);
            } catch (uploadError) {
              failedUploads.push(`${fieldName}: ${getErrorMessage(uploadError)}`);
            }
          }
        }

        if (failedUploads.length > 0) {
          throw new Error(
            opts.submit
              ? `Some files could not be uploaded before submit: ${failedUploads.join("; ")}`
              : `Saved draft, but some files could not be uploaded: ${failedUploads.join("; ")}`,
          );
        }
      };

      if (kind === "purchase_requisition") {
        const detail = targetId
          ? await updateProcurementPrFromValues(targetId, computedValues)
          : await createProcurementPrFromValues(computedValues);
        id = detail.id;
        await uploadPurchaseRequisitionAttachments(id);
        if (opts.submit) {
          await submitProcurementPr(id);
        }
      } else if (kind === "purchase_order") {
        const detail = targetId
          ? await updateProcurementPoFromValues(targetId, computedValues)
          : prId
            ? await createProcurementPoFromPrWithValues(prId, computedValues)
            : await createProcurementPoFromValues(computedValues, {
                parentSubmissionId,
              });
        id = detail.id;
        if (detail.e_approval_submission_id) {
          await uploadEApprovalAttachments(detail.e_approval_submission_id);
        }
        if (opts.submit) {
          await submitProcurementPo(id);
        }
      } else {
        if (!poId && !targetId) {
          throw new Error("Purchase order context is required for AP invoices.");
        }
        const detail = targetId
          ? await updateProcurementApInvoiceFromValues(targetId, computedValues)
          : await createProcurementApInvoiceFromPoWithValues(poId!, computedValues);
        const invoice = "invoice" in detail ? detail.invoice : detail;
        id = invoice.id;
        if (invoice.e_approval_submission_id) {
          await uploadEApprovalAttachments(invoice.e_approval_submission_id);
        }
        if (opts.submit) {
          await submitProcurementApInvoice(id);
        }
      }

      setSavedDocumentId(id);

      return id;
    },
    onSuccess: (id, variables) => {
      setFieldErrors({});
      push({
        level: "success",
        title: variables.submit ? "Submitted for approval" : "Draft saved",
        message: variables.submit
          ? "Approvers have been notified through the E-Approval workflow."
          : "You can return and continue editing this draft.",
      });
      onSaved({ id, kind });
    },
    onError: (error) => {
      const apiFieldErrors = getApiFieldErrors(error, "values.");
      if (Object.keys(apiFieldErrors).length > 0) {
        setFieldErrors(apiFieldErrors);
      }
      push({
        level: "error",
        title: "Could not save",
        message: getErrorMessage(error),
      });
    },
  });

  const isBusy = persistMutation.isPending;

  if (schemaQuery.isLoading) {
    return (
      <div className="space-y-3">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="h-14 animate-pulse rounded-lg bg-muted/50" />
        ))}
      </div>
    );
  }

  if (schemaQuery.isError) {
    return (
      <OperationalAlert
        level="error"
        title="Could not load form schema"
        description={getErrorMessage(schemaQuery.error)}
      />
    );
  }

  if (!schemaQuery.data?.form) {
    return (
      <OperationalAlert
        level="warning"
        title="No published form"
        description="Install and publish the finance & procurement template pack under E-Approval → Forms, then return here."
      />
    );
  }

  if (schemaQuery.data.form.status !== "published") {
    return (
      <OperationalAlert
        level="warning"
        title="Form not published"
        description="Publish the procurement form in E-Approval before creating documents here."
      />
    );
  }

  return (
    <div className="space-y-4">
      {schemaQuery.data.form.description ? (
        <p className="rounded-lg border border-border bg-muted/20 px-3 py-2 text-sm text-muted-foreground">
          {schemaQuery.data.form.description}
        </p>
      ) : null}

      {usesPurchaseRequisitionPicker ? (
        <EApprovalPurchaseRequisitionPicker
          formId={formId}
          value={parentSubmissionId}
          onChange={handlePurchaseRequisitionSelect}
          error={fieldErrors.parent_submission_id}
          enabled={!!formId}
          scope="procurement"
          emptyDescription="Only fully approved PRs with remaining budget are listed. PRs still awaiting approvers (even if you approved your step) are not eligible yet."
        />
      ) : mode === "edit" && kind === "purchase_order" && (lockedParentSubmissionId || initialValues?.purchase_requisition_document_no) ? (
        <OperationalAlert
          level="info"
          title="Linked purchase requisition"
          description={
            initialValues?.purchase_requisition_document_no
              ? `This draft is linked to ${initialValues.purchase_requisition_document_no}. Update delivery details below, then save or submit.`
              : "This draft is already linked to an approved purchase requisition. Update delivery details below, then save or submit."
          }
        />
      ) : null}

      {duplicateApproverWarning ? (
        <OperationalAlert level="warning" title="Duplicate approvers" description={duplicateApproverWarning} />
      ) : null}

      {balanceError ? (
        <OperationalAlert level="warning" title="Amount exceeds policy" description={balanceError} />
      ) : null}

      {poAmountEvaluation?.warning ? (
        <OperationalAlert level="warning" title="Overspend policy warning" description={poAmountEvaluation.warning} />
      ) : null}

      {missingRequiredAttachments.length > 0 ? (
        <OperationalAlert
          level="warning"
          title="Required attachments missing"
          description={`Upload ${missingRequiredAttachments.join(", ")} before submitting for approval.`}
        />
      ) : null}

      {existingAttachments.length > 0 ? (
        <OperationalAlert
          level="info"
          title="Saved attachments"
          description={`${existingAttachments.length} file(s) already on this draft. Upload additional files below if needed.`}
        />
      ) : null}

      {showSectionProgress ? (
        <EApprovalFormSectionProgressNav sections={sectionProgress} className="sticky top-0 z-10" />
      ) : null}

      <div className={cn("rounded-xl border border-border bg-card p-5 shadow-sm")}>
        <EApprovalFormFieldsLayout
          fields={composeFields}
          values={computedValues}
          fieldErrors={fieldErrors}
          fieldHelpOverrides={fieldHelpOverrides}
          formMetadata={formMetadata}
          planFeaturesOverride={
            planFeatures
              ? {
                  plan_tier: planFeatures.plan_tier,
                  file_uploads: planFeatures.file_uploads,
                  max_file_fields: planFeatures.max_file_fields,
                }
              : undefined
          }
          onChange={(name, value) => {
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
          approverOptions={approverOptions}
          approverOptionsLoading={needsApproverOptions && usersQuery.isLoading}
          density="comfortable"
        />
      </div>

      <div className="flex flex-col gap-2 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs text-muted-foreground">
          Fields and approval routing follow your published E-Approval form. Customize them under E-Approval → Forms.
        </p>
        <div className="flex flex-wrap justify-end gap-2">
          {onCancel ? (
            <Button type="button" variant="outline" onClick={onCancel} disabled={isBusy}>
              Cancel
            </Button>
          ) : null}
          <Button
            type="button"
            variant="outline"
            disabled={isBusy}
            onClick={() => persistMutation.mutate({ submit: false, requireRequired: false })}
          >
            Save draft
          </Button>
          <Button
            type="button"
            disabled={isBusy || Boolean(duplicateApproverWarning) || Boolean(balanceError)}
            onClick={() => persistMutation.mutate({ submit: true, requireRequired: true })}
          >
            {persistMutation.isPending ? "Submitting…" : "Submit for approval"}
          </Button>
        </div>
      </div>
    </div>
  );
}
