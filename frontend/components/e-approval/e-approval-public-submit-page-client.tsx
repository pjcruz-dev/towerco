"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useRef, useState } from "react";

import { EApprovalComposeFormFields, type ComposeFormStepMeta } from "@/components/e-approval/e-approval-compose-form-fields";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchEApprovalPublicForm,
  submitEApprovalPublicForm,
  uploadEApprovalPublicAttachment,
} from "@/lib/api/modules/e-approval-public-api";
import { fieldDefaultValue, validateSubmissionValues } from "@/modules/e-approval/field-validation";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import { parseFormComposeConfig } from "@/modules/e-approval/form-compose-config";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { E_APPROVAL_FORM_SHELL_CLASS } from "@/modules/e-approval/form-layout";

type Props = {
  accessToken: string;
};

function buildInitialValues(fields: EApprovalFormFieldInput[]): Record<string, string> {
  const values: Record<string, string> = {};
  for (const field of fields) {
    if (field.type === "grid") {
      values[field.name] = '{"rows":[{"0":""}]}';
    } else if (isComposeFillableFieldType(field.type)) {
      values[field.name] = fieldDefaultValue(field);
    }
  }
  return values;
}

function resolveAssetUrl(path: string | null): string | null {
  if (!path) return null;
  if (path.startsWith("http://") || path.startsWith("https://")) return path;
  const api = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";
  const origin = api.replace(/\/api\/v1\/?$/, "");
  return `${origin}${path.startsWith("/") ? path : `/${path}`}`;
}

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

export function EApprovalPublicSubmitPageClient({ accessToken }: Props) {
  const [accessPassword, setAccessPassword] = useState("");
  const [passwordSubmitted, setPasswordSubmitted] = useState(false);
  const [submitterName, setSubmitterName] = useState("");
  const [submitterEmail, setSubmitterEmail] = useState("");
  const [values, setValues] = useState<Record<string, string>>({});
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [attachmentFiles, setAttachmentFiles] = useState<Record<string, File[]>>({});
  const attachmentFilesRef = useRef<Record<string, File[]>>({});
  const valuesRef = useRef<Record<string, string>>({});
  const [completedDocNo, setCompletedDocNo] = useState<string | null>(null);
  const [uploadWarning, setUploadWarning] = useState<string | null>(null);
  const [composeStepMeta, setComposeStepMeta] = useState<ComposeFormStepMeta>({
    stepped: false,
    currentStep: 0,
    totalSteps: 1,
    isLastStep: true,
  });

  const formQuery = useQuery({
    queryKey: ["e-approval", "public", accessToken, passwordSubmitted ? accessPassword : ""],
    queryFn: () => fetchEApprovalPublicForm(accessToken, passwordSubmitted ? accessPassword : undefined),
    retry: false,
  });

  const passwordRequired = formQuery.data?.requires_password === true && !passwordSubmitted;

  const formFields = formQuery.data?.form.fields ?? [];

  const fillableFields = useMemo(
    () => formFields.filter((f) => isComposeFillableFieldType(f.type)),
    [formFields],
  );

  useEffect(() => {
    if (!formQuery.data?.form.fields.length) {
      return;
    }
    setValues((prev) => {
      if (Object.keys(prev).length > 0) {
        return prev;
      }
      const initial = buildInitialValues(formQuery.data.form.fields);
      valuesRef.current = initial;
      return initial;
    });
  }, [formQuery.data?.form.fields]);

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
      const latestValues = valuesRef.current;
      const latestAttachments = attachmentFilesRef.current;
      const pendingAttachmentCounts: Record<string, number> = {};
      for (const [fieldName, files] of Object.entries(latestAttachments)) {
        if (files.length > 0) {
          pendingAttachmentCounts[fieldName] = files.length;
        }
      }

      const result = await submitEApprovalPublicForm(accessToken, {
        submitter_name: submitterName.trim(),
        submitter_email: submitterEmail.trim(),
        values: latestValues,
        access_password: accessPassword || undefined,
        pending_attachment_counts: pendingAttachmentCounts,
      });

      const failedUploads: string[] = [];
      const uploadTasks: Array<Promise<void>> = [];
      for (const [fieldName, files] of Object.entries(latestAttachments)) {
        for (const file of files) {
          uploadTasks.push(
            uploadEApprovalPublicAttachment(
              accessToken,
              result.submission_id,
              result.upload_token,
              file,
              fieldName,
              accessPassword || undefined,
            ).then(
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
            ? `Submission saved, but a file failed to upload: ${failedUploads[0]}`
            : `Submission saved, but some files failed to upload: ${failedUploads.join("; ")}`,
        );
      }

      return result;
    },
    onSuccess: (result) => {
      setUploadWarning(null);
      setCompletedDocNo(result.document_no);
    },
  });

  const handleUnlock = () => {
    setPasswordSubmitted(true);
    void formQuery.refetch();
  };

  if (completedDocNo) {
    return (
      <div className="mx-auto max-w-lg space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm">
        <h1 className="text-xl font-semibold text-foreground">Submission received</h1>
        <p className="text-sm text-muted-foreground">
          Your request <span className="font-medium text-foreground">{completedDocNo}</span> was submitted successfully.
          The organization will review it through their internal approval process. Status updates may be sent to the
          email you provided when the organization has enabled external notifications.
        </p>
      </div>
    );
  }

  if (formQuery.isLoading && !passwordRequired) {
    return <p className="text-sm text-muted-foreground">Loading form…</p>;
  }

  if (passwordRequired) {
    const formName = formQuery.data?.form.name;
    return (
      <div className="mx-auto max-w-md space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm">
        <h1 className="text-xl font-semibold text-foreground">{formName || "Secure form access"}</h1>
        <p className="text-sm text-muted-foreground">Enter the password provided by the organization to open this form.</p>
        <div className="space-y-2">
          <Label htmlFor="ea-public-password">Access password</Label>
          <Input
            id="ea-public-password"
            type="password"
            value={accessPassword}
            onChange={(e) => setAccessPassword(e.target.value)}
          />
        </div>
        {formQuery.isError ? (
          <p className="text-sm text-destructive">{getErrorMessage(formQuery.error)}</p>
        ) : null}
        <Button type="button" onClick={handleUnlock} disabled={!accessPassword.trim()}>
          Continue
        </Button>
      </div>
    );
  }

  if (formQuery.isError) {
    return (
      <OperationalAlert
        level="error"
        title="Form unavailable"
        description={getErrorMessage(formQuery.error)}
      />
    );
  }

  if (!formQuery.data) {
    return null;
  }

  const planFeatures = formQuery.data.plan_features ?? {
    plan_tier: "starter",
    file_uploads: false,
    max_file_fields: 0,
  };
  const approverOptions = formQuery.data.approver_options ?? [];
  const { form } = formQuery.data;
  const logoUrl = resolveAssetUrl(form.brand_logo_url);
  const composeConfig = parseFormComposeConfig(form.metadata_json ?? null);

  const handleSubmit = () => {
    const latestValues = valuesRef.current;
    const latestAttachments = attachmentFilesRef.current;
    const issues = validateSubmissionValues(fillableFields, latestValues, latestAttachments);
    if (!submitterName.trim() || !submitterEmail.trim()) {
      setFieldErrors({ _form: "Enter your name and email before submitting." });
      return;
    }
    if (issues.length > 0) {
      const map: Record<string, string> = {};
      for (const issue of issues) {
        map[issue.fieldName] = issue.message;
      }
      setFieldErrors(map);
      return;
    }
    setFieldErrors({});
    setUploadWarning(null);
    submitMutation.mutate();
  };

  return (
    <div className={E_APPROVAL_FORM_SHELL_CLASS}>
      <header className="space-y-2 border-b border-border pb-4">
        {logoUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={logoUrl} alt="" className="mb-2 h-10 max-w-[180px] object-contain" />
        ) : null}
        <h1 className="text-2xl font-semibold text-foreground">{form.name}</h1>
        {form.description ? <p className="text-sm text-muted-foreground">{form.description}</p> : null}
        {formQuery.data.sponsor_label ? (
          <p className="text-xs text-muted-foreground">Submissions are routed to {formQuery.data.sponsor_label} for review.</p>
        ) : null}
      </header>

      <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <h2 className="text-base font-medium text-foreground">Your contact details</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="ea-public-name">Full name</Label>
            <Input id="ea-public-name" value={submitterName} onChange={(e) => setSubmitterName(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ea-public-email">Email</Label>
            <Input
              id="ea-public-email"
              type="email"
              value={submitterEmail}
              onChange={(e) => setSubmitterEmail(e.target.value)}
            />
          </div>
        </div>
      </section>

      <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <h2 className="text-base font-medium text-foreground">Form</h2>
        {fieldErrors._form ? (
          <OperationalAlert level="warning" title="Cannot submit" description={fieldErrors._form} />
        ) : null}
        <EApprovalComposeFormFields
          fields={form.fields}
          values={values}
          fieldErrors={fieldErrors}
          formMetadata={form.metadata_json ?? null}
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
            // Keep the string value in sync for any non-file consumers / visibility.
            syncValues(
              name,
              files.length > 0 ? files.map((file) => file.name).join(", ") : "",
            );
            setFieldErrors((prev) => clearFieldError(prev, name));
          }}
          approverOptions={approverOptions}
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
          {submitMutation.isPending ? "Submitting…" : "Submit"}
        </Button>
      </div>

      {submitMutation.isError ? (
        <p className="text-sm text-destructive">{getErrorMessage(submitMutation.error)}</p>
      ) : null}
      {uploadWarning ? <p className="text-sm text-destructive">{uploadWarning}</p> : null}
    </div>
  );
}
