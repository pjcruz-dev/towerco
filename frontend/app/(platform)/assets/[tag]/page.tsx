import { Suspense } from "react";

import { AssetDetailPageClient } from "./asset-detail-page-client";

export default async function AssetDetailPage({ params }: { params: Promise<{ tag: string }> }) {
  const { tag } = await params;
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading asset…</div>}>
      <AssetDetailPageClient tag={tag} />
    </Suspense>
  );
}
