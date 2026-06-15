"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import {
  EApprovalDocumentLinksPanel,
  pickDocumentLinkState,
} from "@/components/e-approval/e-approval-document-links-panel";
import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { fetchEApprovalSubmission } from "@/lib/api/modules/e-approval-api";
import { permissions } from "@/lib/rbac/permissions";

export function EApprovalSubmissionDetailPageClient({ submissionId }: { submissionId: string }) {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["e-approval", "submission", submissionId],
    queryFn: () => fetchEApprovalSubmission(submissionId),
  });

  const linkState = data ? pickDocumentLinkState(data) : null;

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsView]}>
      <div className="space-y-6">
        <EApprovalPageHeader
          title={data?.document_no ?? "Submission"}
          description={data?.form_name ?? "E-Approval submission detail"}
          actions={
            <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isLoading}>
              Refresh
            </Button>
          }
        />

        {isError ? <p className="text-sm text-destructive">Could not load submission.</p> : null}
        {isLoading && !data ? <p className="text-sm text-muted-foreground">Loading submission…</p> : null}

        {data ? (
          <>
            <EApprovalSectionCard title="Summary" description="Core submission metadata.">
              <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">Status</dt>
                  <dd className="mt-1 font-medium capitalize">{data.status}</dd>
                </div>
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">Requestor</dt>
                  <dd className="mt-1 font-medium">{data.requestor?.name ?? "—"}</dd>
                </div>
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">Form</dt>
                  <dd className="mt-1 font-medium">{data.form_name ?? "—"}</dd>
                </div>
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">Parent submission</dt>
                  <dd className="mt-1 font-medium">
                    {data.parent_submission_id ? (
                      <Link href={`/e-approval/submissions/${data.parent_submission_id}`} className="text-primary hover:underline">
                        View parent
                      </Link>
                    ) : (
                      "—"
                    )}
                  </dd>
                </div>
              </dl>
            </EApprovalSectionCard>

            <EApprovalDocumentLinksPanel
              submissionId={submissionId}
              documentLinks={linkState?.documentLinks}
              incomingDocumentLinks={linkState?.incomingDocumentLinks}
              relatedFormNavigation={linkState?.relatedFormNavigation}
            />
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
