import { Suspense } from "react";

import { ControlledDocumentPublishedFilePageClient } from "./controlled-document-published-file-page-client";

export default function ControlledDocumentPublishedFilePage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading the published file…</p>}>
      <ControlledDocumentPublishedFilePageClient />
    </Suspense>
  );
}
