"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useRef, useState } from "react";

import { EApprovalComposeFormFields, type ComposeFormStepMeta } from "@/components/e-approval/e-approval-compose-form-fields";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchEApprovalPublicRevision,
  resubmitEApprovalPublicRevision,
  uploadEApprovalPublicRevisionAttachment,
} from "@/lib/api/modules/e-approval-public-api";
import { validateSubmissionValues } from "@/modules/e-approval/field-validation";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import { parseFormComposeConfig } from "@/modules/e-approval/form-compose-config";
import { E_APPROVAL_FORM_SHELL_CLASS } from "@/modules/e-approval/form-layout";

type Props = {
  submissionId: string;
  resubmitToken: string;
};

function clearFieldError(
  prev: Record<string, string>,
  fieldName: string,
): Record<string, string> {
  if (!prev[fieldName] && !prev._form) {
    return prev;
  }
  const next = { ...prev };
  delete next[fieldName];
  delete next._form;
  return next;
}

export function EApprovalPublicRevisePageClient({ submissionId, resubmitToken }: Props) {
  const [values, setValues] = useState<Record<string, string>>({});
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [attachmentFiles, setAttachmentFiles] = useState<Record<string, File[]>>({});
  const attachmentFilesRef = useRef<Record<string, File[]>>({});
  const valuesRef = useRef<Record<string, string>>({});
  const [completedDocNo, setCompletedDocNo] = useState<string | null>(null);
  const [composeStepMeta, setComposeStepMeta] = useState<ComposeFormStepMeta>({
    stepped: false,
    currentStep: 0,
    totalSteps: 1,
    isLastStep: true,
  });

  const reviseQuery = useQuery({
    queryKey: ["e-approval", "public-revise", submissionId, resubmitToken],
    queryFn: () => fetchEApprovalPublicRevision(submissionId, resubmitToken),
    retry: false,
    enabled: Boolean(submissionId && resubmitToken),
  });

  const formFields = reviseQuery.data?.form.fields ?? [];
  const fillableFields = useMemo(
    () => formFields.filter((f) => isComposeFillableFieldType(f.type)),
    [formFields],
  );

  useEffect(() => {
    if (!reviseQuery.data) {
      return;
    }
    const nextValues = reviseQuery.data.values ?? {};
    valuesRef.current = nextValues;
    setValues(nextValues);
  }, [reviseQuery.data]);

  const syncAttachmentFiles = (name: string, files: File[]) => {
    const next = { ...attachmentFilesRef.current };
    if (files.length === 0) {
      delete next[name];
    } else {
      next[name] = files;
    }
    attachmentFilesRef.current = next;
    setAttachmentFiles(next);
  };

  const syncValues = (name: string, value: string) => {
    const next = { ...valuesRef.current, [name]: value };
    valuesRef.current = next;
    setValues(next);
  };

  const submitMutation = useMutation({
    mutationFn: async () => {
      if (!reviseQuery.data) {
        throw new Error("Revision session is not loaded.");
      }

      const latestValues = valuesRef.current;
      const latestAttachments = attachmentFilesRef.current;

      const result = await resubmitEApprovalPublicRevision(submissionId, {
        resubmit_token: resubmitToken,
        values: latestValues,
      });

      const failedUploads: string[] = [];
      const uploadTasks: Array<Promise<void>> = [];
      const uploadToken = result.upload_token || reviseQuery.data.upload_token;

      for (const [fieldName, files] of Object.entries(latestAttachments)) {
        for (const file of files) {
          uploadTasks.push(
            uploadEApprovalPublicRevisionAttachment(result.submission_id, uploadToken, file, fieldName).then(
              () => undefined,
              (uploadError) => {
                failedUploads.push(`${fieldName}: ${getErrorMessage(uploadError)}`);
              },
            ),
          );
        }
      }
      await Promise.all(uploadTasks);

      if (failedUploads.length > 0) {
        throw new Error(
          failedUploads.length === 1
            ? `Revision saved, but a file failed to upload: ${failedUploads[0]}`
            : `Revision saved, but some files failed to upload: ${failedUploads.join("; ")}`,
        );
      }

      return result;
    },
    onSuccess: (result) => {
      setCompletedDocNo(result.document_no);
    },
  });

  if (!resubmitToken) {
    return (
      <OperationalAlert
        level="error"
        title="Invalid revise link"
        description="This revision link is missing a security token. Open the link from your email again."
      />
    );
  }

  if (completedDocNo) {
    return (
      <div className="mx-auto max-w-lg space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm">
        <h1 className="text-xl font-semibold text-foreground">Revision submitted</h1>
        <p className="text-sm text-muted-foreground">
          Your revised request <span className="font-medium text-foreground">{completedDocNo}</span> was
          resubmitted. The organization will continue review from the appropriate approval step.
        </p>
      </div>
    );
  }

  if (reviseQuery.isLoading) {
    return <p className="text-sm text-muted-foreground">Loading revision…</p>;
  }

  if (reviseQuery.isError) {
    return (
      <OperationalAlert
        level="error"
        title="Revision unavailable"
        description={getErrorMessage(reviseQuery.error)}
      />
    );
  }

  if (!reviseQuery.data) {
    return null;
  }

  const data = reviseQuery.data;
  const planFeatures = data.plan_features ?? {
    plan_tier: "starter",
    file_uploads: false,
    max_file_fields: 0,
  };
  const composeConfig = parseFormComposeConfig(null);

  const handleSubmit = () => {
    const issues = validateSubmissionValues(
      fillableFields,
      valuesRef.current,
      attachmentFilesRef.current,
    );
    if (issues.length > 0) {
      const map: Record<string, string> = {};
      for (const issue of issues) {
        map[issue.fieldName] = issue.message;
      }
      setFieldErrors(map);
      return;
    }
    setFieldErrors({});
    submitMutation.mutate();
  };

  return (
    <div className={E_APPROVAL_FORM_SHELL_CLASS}>
      <header className="space-y-2 border-b border-border pb-4">
        <p className="text-xs font-medium text-amber-700 dark:text-amber-400">Needs revision</p>
        <h1 className="text-2xl font-semibold text-foreground">{data.form.name}</h1>
        <p className="text-sm text-muted-foreground">
          Document <span className="font-medium text-foreground">{data.document_no}</span>
          {data.submitter_email ? ` · ${data.submitter_email}` : null}
        </p>
        {data.revision_notes ? (
          <OperationalAlert
            level="warning"
            title="Revision notes"
            description={data.revision_notes}
          />
        ) : (
          <p className="text-sm text-muted-foreground">
            Update the form as requested, then resubmit. Your request returns to the current approver when the form
            is configured for resume routing.
          </p>
        )}
      </header>

      <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <h2 className="text-base font-medium text-foreground">Form</h2>
        <EApprovalComposeFormFields
          fields={data.form.fields}
          values={values}
          fieldErrors={fieldErrors}
          formMetadata={null}
          composeConfig={composeConfig}
          onStepMetaChange={setComposeStepMeta}
          onStepValidationIssues={(issues) => {
            const map: Record<string, string> = {};
            for (const issue of issues) {
              map[issue.fieldName] = issue.message;
            }
            setFieldErrors(map);
          }}
          onChange={(name, value) => {
            syncValues(name, value);
            setFieldErrors((prev) => clearFieldError(prev, name));
          }}
          fileSelections={attachmentFiles}
          onFileChange={(name, files) => {
            syncAttachmentFiles(name, files);
            syncValues(
              name,
              files.length > 0 ? files.map((file) => file.name).join(", ") : "",
            );
            setFieldErrors((prev) => clearFieldError(prev, name));
          }}
          approverOptions={data.approver_options ?? []}
          planFeaturesOverride={planFeatures}
          allowRemoteLookups={false}
        />
      </section>

      <div className="flex justify-end border-t border-border pt-4">
        <Button
          type="button"
          size="lg"
          disabled={submitMutation.isPending || (composeStepMeta.stepped && !composeStepMeta.isLastStep)}
          onClick={handleSubmit}
        >
          {submitMutation.isPending ? "Resubmitting…" : "Resubmit"}
        </Button>
      </div>

      {submitMutation.isError ? (
        <p className="text-sm text-destructive">{getErrorMessage(submitMutation.error)}</p>
      ) : null}
    </div>
  );
}
