import { Suspense } from "react";

import { EApprovalSubmissionNewPageClient } from "./e-approval-submission-new-page-client";

export default function EApprovalSubmissionNewPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
          Loading…
        </div>
      }
    >
      <EApprovalSubmissionNewPageClient />
    </Suspense>
  );
}
