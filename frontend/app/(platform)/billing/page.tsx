import { Suspense } from "react";

import { BillingPageClient } from "./billing-page-client";

export default function BillingPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading billing…</p>}>
      <BillingPageClient />
    </Suspense>
  );
}
