import { EApprovalSubmissionPrintPageClient } from "./e-approval-submission-print-page-client";

type Props = { params: Promise<{ id: string }> };

export default async function EApprovalSubmissionPrintPage({ params }: Props) {
  const { id } = await params;

  return <EApprovalSubmissionPrintPageClient submissionId={id} />;
}
