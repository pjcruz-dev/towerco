import { EApprovalSubmissionDetailPageClient } from "./e-approval-submission-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function EApprovalSubmissionDetailPage({ params }: PageProps) {
  const { id } = await params;

  return <EApprovalSubmissionDetailPageClient submissionId={id} />;
}
