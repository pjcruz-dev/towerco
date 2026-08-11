import { ControlledDocumentsPageClient } from "./controlled-documents-page-client";
import { Suspense } from "react";

export default function ControlledDocumentsPage() {
  return (
    <Suspense fallback={null}>
      <ControlledDocumentsPageClient />
    </Suspense>
  );
}
