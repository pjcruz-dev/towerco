import { Suspense } from "react";

import { DynPrintableEditorPageClient } from "../dyn-printable-editor-page-client";

export default function DynPrintableEditPage() {
  return (
    <Suspense fallback={<p className="text-sm text-muted-foreground">Loading…</p>}>
      <DynPrintableEditorPageClient />
    </Suspense>
  );
}
