import { Suspense } from "react";

import { DocExtractBatchesPageClient } from "../doc-extract-batches-page-client";

export default function DocExtractBatchesIndexPage() {
  return (
    <Suspense fallback={null}>
      <DocExtractBatchesPageClient />
    </Suspense>
  );
}
