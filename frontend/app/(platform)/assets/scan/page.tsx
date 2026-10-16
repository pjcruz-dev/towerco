"use client";

import { Suspense } from "react";

import { AssetScanPageClient } from "./asset-scan-page-client";

export default function AssetScanPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading scanner…</div>}>
      <AssetScanPageClient />
    </Suspense>
  );
}
