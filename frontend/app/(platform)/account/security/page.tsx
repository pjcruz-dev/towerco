import { Suspense } from "react";

import { AccountSecurityPageClient } from "./account-security-page-client";

export default function AccountSecurityPage() {
  return (
    <Suspense fallback={<div className="px-4 py-8 text-sm text-muted-foreground">Loading security settings…</div>}>
      <AccountSecurityPageClient />
    </Suspense>
  );
}
