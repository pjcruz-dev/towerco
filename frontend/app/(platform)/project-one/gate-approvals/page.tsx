import { Suspense } from "react";

import { RolloutGateApprovalsPageClient } from "./rollout-gate-approvals-page-client";

export default function RolloutGateApprovalsPage() {
  return (
    <Suspense fallback={<div className="py-10 text-sm text-muted-foreground">Loading gate approvals…</div>}>
      <RolloutGateApprovalsPageClient />
    </Suspense>
  );
}
