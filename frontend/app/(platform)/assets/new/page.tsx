import { Suspense } from "react";

import { AssetRegisterPageClient } from "./asset-register-page-client";

export default function AssetRegisterPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading fields…</div>}>
      <AssetRegisterPageClient />
    </Suspense>
  );
}
