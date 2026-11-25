import { Suspense } from "react";

import { ControlledDocumentDraftsPageClient } from "./controlled-document-drafts-page-client";

export default function ControlledDocumentDraftsPage() {
  return (
    <Suspense>
      <ControlledDocumentDraftsPageClient />
    </Suspense>
  );
}
