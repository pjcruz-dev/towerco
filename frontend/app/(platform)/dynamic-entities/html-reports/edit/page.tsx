import { Suspense } from "react";

import { DynHtmlReportEditorPageClient } from "./dyn-html-report-editor-page-client";

export default function DynHtmlReportEditorPage() {
  return (
    <Suspense fallback={<p className="text-sm text-muted-foreground">Loading…</p>}>
      <DynHtmlReportEditorPageClient />
    </Suspense>
  );
}
