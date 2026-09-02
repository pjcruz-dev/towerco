import { Suspense } from "react";

import { DynReportBuilderPageClient } from "./dyn-report-builder-page-client";

export default function DynReportBuilderPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading…</p>}>
      <DynReportBuilderPageClient />
    </Suspense>
  );
}
