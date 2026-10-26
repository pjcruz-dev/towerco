import { Suspense } from "react";

import { ControlledDocumentObsoletePageClient } from "./controlled-document-obsolete-page-client";

export default function ControlledDocumentObsoletePage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading files…</p>}>
      <ControlledDocumentObsoletePageClient />
    </Suspense>
  );
}
