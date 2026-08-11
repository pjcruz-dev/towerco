"use client";

import { useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";

import { EApprovalSubmissionComposePanel } from "@/components/e-approval/e-approval-submission-compose-panel";
import { RequestFocusShell } from "@/components/layout/request-focus-shell";
import { PermissionGate } from "@/components/layout/permission-gate";
import { eApprovalRequestUrl } from "@/modules/documents/controlled-document-submission-url";
import type { ControlledDocumentRequestMode } from "@/modules/e-approval/controlled-document-compose";
import { permissions } from "@/lib/rbac/permissions";

type Props = { formId: string };

export function EApprovalRequestFocusPageClient({ formId }: Props) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [formTitle, setFormTitle] = useState("New request");
  const resubmitSubmissionId = searchParams.get("resubmit")?.trim() || undefined;

  const controlledModeParam = searchParams.get("controlled_mode");
  const initialControlledMode = useMemo((): ControlledDocumentRequestMode | undefined => {
    if (resubmitSubmissionId) {
      return undefined;
    }
    if (controlledModeParam === "new" || controlledModeParam === "revision") {
      return controlledModeParam;
    }
    return undefined;
  }, [controlledModeParam, resubmitSubmissionId]);

  const initialDocumentCode = resubmitSubmissionId
    ? undefined
    : searchParams.get("document_code")?.trim() || undefined;

  const isControlledDocumentRequest =
    !resubmitSubmissionId &&
    (initialControlledMode === "revision" || initialControlledMode === "new");

  const backHref = resubmitSubmissionId
    ? `/e-approval/submissions/${resubmitSubmissionId}`
    : isControlledDocumentRequest
      ? eApprovalRequestUrl(formId, searchParams)
      : "/e-approval/submissions/new";

  const backLabel = resubmitSubmissionId
    ? "Submission"
    : isControlledDocumentRequest
      ? "Standard view"
      : "All forms";

  const handleCancel = () => {
    router.push(backHref);
  };

  const handleSubmitted = ({ submission }: { submission: { id: string } }) => {
    if (isControlledDocumentRequest) {
      router.push("/documents/controlled");
      return;
    }
    router.push(`/e-approval/submissions/${submission.id}`);
  };

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsCreate]}>
      <RequestFocusShell
        title={resubmitSubmissionId ? `Revise — ${formTitle}` : formTitle}
        backHref={backHref}
        backLabel={backLabel}
      >
        <EApprovalSubmissionComposePanel
          formId={formId}
          fullPage
          focused
          shellClassName="w-full"
          resubmitSubmissionId={resubmitSubmissionId}
          initialControlledMode={initialControlledMode}
          initialDocumentCode={initialDocumentCode}
          onFormLoaded={(form) => setFormTitle(form.name)}
          onCancel={handleCancel}
          onSubmitted={handleSubmitted}
        />
      </RequestFocusShell>
    </PermissionGate>
  );
}
