import { EApprovalSubmissionDetailPageClient } from "@/app/(platform)/e-approval/submissions/[id]/e-approval-submission-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ControlledDocumentRequestPage({ params }: PageProps) {
  const { id } = await params;

  return <EApprovalSubmissionDetailPageClient submissionId={id} />;
}
