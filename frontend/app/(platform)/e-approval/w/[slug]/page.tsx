import { EApprovalFormWorkspacePageClient } from "./e-approval-form-workspace-page-client";

type Props = { params: Promise<{ slug: string }> };

export default async function EApprovalFormWorkspacePage({ params }: Props) {
  const { slug } = await params;
  return <EApprovalFormWorkspacePageClient slug={slug} />;
}
