import { EApprovalRequestFocusPageClient } from "./e-approval-request-focus-page-client";

type Props = { params: Promise<{ formId: string }> };

export default async function EApprovalRequestFocusPage({ params }: Props) {
  const { formId } = await params;
  return <EApprovalRequestFocusPageClient formId={formId} />;
}
