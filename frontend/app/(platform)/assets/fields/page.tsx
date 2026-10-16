"use client";

import { Suspense } from "react";

import { AssetFieldsPageClient } from "./asset-fields-page-client";

export default function AssetFieldsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading fields…</div>}>
      <AssetFieldsPageClient />
    </Suspense>
  );
}
