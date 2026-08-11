import { Suspense } from "react";

import { EApprovalRequestFormPageClient } from "./e-approval-request-form-page-client";

type Props = { params: Promise<{ formId: string }> };

export default async function EApprovalRequestFormPage({ params }: Props) {
  const { formId } = await params;
  return (
    <Suspense>
      <EApprovalRequestFormPageClient formId={formId} />
    </Suspense>
  );
}
