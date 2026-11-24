import { Suspense } from "react";

import { ControlledDocumentNewPageClient } from "./controlled-document-new-page-client";

export default function ControlledDocumentNewPage() {
  return (
    <Suspense>
      <ControlledDocumentNewPageClient />
    </Suspense>
  );
}
