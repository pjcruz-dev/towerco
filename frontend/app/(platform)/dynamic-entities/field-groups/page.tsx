import { Suspense } from "react";

import { DynamicFieldGroupsPageClient } from "./dynamic-field-groups-page-client";

export default function DynamicFieldGroupsPage() {
  return (
    <Suspense fallback={<p className="text-sm text-muted-foreground">Loading…</p>}>
      <DynamicFieldGroupsPageClient />
    </Suspense>
  );
}
