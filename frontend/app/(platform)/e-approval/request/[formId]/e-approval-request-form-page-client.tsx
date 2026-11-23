"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";

import { EApprovalBackLink, EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSubmissionComposePanel } from "@/components/e-approval/e-approval-submission-compose-panel";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { ExternalLink } from "lucide-react";
import { eApprovalFocusUrl } from "@/modules/documents/controlled-document-submission-url";
import { fetchEApprovalForm } from "@/lib/api/modules/e-approval-api";
import { isIsoDocumentControlForm } from "@/modules/e-approval/form-workspace";
import { viewerCanStartFormRequest } from "@/modules/e-approval/form-request-access";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

type Props = { formId: string };

function EApprovalRequestFormPageBody({ formId }: Props) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const currentUserId = useAuthStore((state) => state.user?.id ?? null);
  const resubmitSubmissionId = searchParams.get("resubmit")?.trim() || undefined;

  const formQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "submit-title"],
    queryFn: () => fetchEApprovalForm(formId),
  });

  const isControlledDocumentRequest =
    isIsoDocumentControlForm(formQuery.data?.metadata_json) &&
    !resubmitSubmissionId &&
    (searchParams.get("controlled_mode") === "revision" ||
      searchParams.get("controlled_mode") === "new");

  const startBlocked =
    !resubmitSubmissionId &&
    formQuery.isSuccess &&
    !viewerCanStartFormRequest(formQuery.data?.metadata_json, currentUserId);

  const handleSubmitted = ({ submission }: { submission: { id: string } }) => {
    if (resubmitSubmissionId) {
      router.push(`/e-approval/submissions/${submission.id}`);
      return;
    }
    if (isControlledDocumentRequest) {
      router.push("/documents/controlled");
    } else {
      router.push("/e-approval/submissions");
    }
  };

  const handleCancel = () => {
    if (resubmitSubmissionId) {
      router.push(`/e-approval/submissions/${resubmitSubmissionId}`);
      return;
    }
    if (isControlledDocumentRequest) {
      router.push("/documents/controlled");
    } else {
      router.push("/e-approval/submissions/new");
    }
  };

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsCreate]}>
      <div className="space-y-6 pb-8">
        <LiveProductTourHost />
        <EApprovalPageHeader
          title={
            resubmitSubmissionId
              ? `Revise ${formQuery.data?.name ?? "request"}`
              : (formQuery.data?.name ?? "New request")
          }
          description={
            <>
              {resubmitSubmissionId ? (
                <>
                  <EApprovalBackLink href={`/e-approval/submissions/${resubmitSubmissionId}`}>
                    Back to submission
                  </EApprovalBackLink>
                  {" · "}
                  <EApprovalBackLink href="/e-approval/submissions">My submissions</EApprovalBackLink>
                </>
              ) : isControlledDocumentRequest ? (
                <EApprovalBackLink href="/documents/controlled">Document register</EApprovalBackLink>
              ) : (
                <>
                  <EApprovalBackLink href="/e-approval/submissions/new">Choose another form</EApprovalBackLink>
                  {" · "}
                  <EApprovalBackLink href="/e-approval/submissions">My submissions</EApprovalBackLink>
                </>
              )}
            </>
          }
          actions={
            <div className="flex flex-wrap items-center gap-2">
              {startBlocked ? null : (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="gap-1.5"
              onClick={() =>
                window.open(eApprovalFocusUrl(formId, searchParams), "_blank", "noopener,noreferrer")
              }
            >
              Focused view
              <ExternalLink className="h-3.5 w-3.5" />
            </Button>
              )}
            </div>
          }
        />

        <EApprovalSubmissionComposePanel
          formId={formId}
          fullPage
          resubmitSubmissionId={resubmitSubmissionId}
          onCancel={handleCancel}
          onSubmitted={handleSubmitted}
        />
      </div>
    </PermissionGate>
  );
}

export function EApprovalRequestFormPageClient({ formId }: Props) {
  return <EApprovalRequestFormPageBody formId={formId} />;
}
