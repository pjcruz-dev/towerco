import { Suspense } from "react";

import { DynamicFieldsAdminPageClient } from "./dynamic-fields-admin-page-client";

export default function DynamicFieldsAdminPage() {
  return (
    <Suspense fallback={<p className="text-sm text-muted-foreground">Loading…</p>}>
      <DynamicFieldsAdminPageClient />
    </Suspense>
  );
}
