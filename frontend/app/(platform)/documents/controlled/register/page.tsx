import { Suspense } from "react";

import { ControlledDocumentsPageClient } from "./controlled-documents-page-client";

export default function ControlledDocumentsRegisterPage() {
  return (
    <Suspense fallback={null}>
      <ControlledDocumentsPageClient />
    </Suspense>
  );
}
