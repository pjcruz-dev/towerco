import { Suspense } from "react";

import { ControlledDocumentsPageClient } from "./controlled-documents-page-client";

export default function ControlledDocumentsRegisterPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading documents…</p>}>
      <ControlledDocumentsPageClient />
    </Suspense>
  );
}
