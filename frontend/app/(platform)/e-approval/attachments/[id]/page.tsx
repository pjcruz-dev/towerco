import { EApprovalAttachmentDownloadPageClient } from "./e-approval-attachment-download-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function EApprovalAttachmentDownloadPage({ params }: PageProps) {
  await params;

  return <EApprovalAttachmentDownloadPageClient />;
}
