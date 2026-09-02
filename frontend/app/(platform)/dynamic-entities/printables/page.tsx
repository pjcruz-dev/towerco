import { Suspense } from "react";

import { DynPrintablesAdminPageClient } from "./dyn-printables-admin-page-client";

export default function DynPrintablesAdminPage() {
  return (
    <Suspense fallback={<p className="text-sm text-muted-foreground">Loading…</p>}>
      <DynPrintablesAdminPageClient />
    </Suspense>
  );
}
