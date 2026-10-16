import { Suspense } from "react";

import { AssetsPageClient } from "./assets-page-client";

export default function AssetsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading assets…</div>}>
      <AssetsPageClient />
    </Suspense>
  );
}
