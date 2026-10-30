import { EApprovalFormWorkspacePageClient } from "./e-approval-form-workspace-page-client";

type Props = { params: Promise<{ slug: string }>; searchParams: Promise<{ queue?: string | string[] }> };

export default async function EApprovalFormWorkspacePage({ params, searchParams }: Props) {
  const { slug } = await params;
  const query = await searchParams;
  const queue = Array.isArray(query.queue) ? query.queue[0] : query.queue;
  return <EApprovalFormWorkspacePageClient slug={slug} queue={queue ?? null} />;
}
