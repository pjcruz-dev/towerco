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
import { permissions } from "@/lib/rbac/permissions";

type Props = { formId: string };

export function EApprovalRequestFormPageClient({ formId }: Props) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const resubmitSubmissionId = searchParams.get("resubmit")?.trim() || undefined;

  const isControlledDocumentRequest =
    !resubmitSubmissionId &&
    (searchParams.get("controlled_mode") === "revision" ||
      searchParams.get("controlled_mode") === "new");

  const formQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "submit-title"],
    queryFn: () => fetchEApprovalForm(formId),
  });

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
