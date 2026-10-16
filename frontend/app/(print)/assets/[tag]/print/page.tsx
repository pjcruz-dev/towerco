import { Suspense } from "react";

import { AssetPrintClient } from "./asset-print-client";

export default async function AssetPrintPage({ params }: { params: Promise<{ tag: string }> }) {
  const { tag } = await params;
  return (
    <Suspense fallback={<p className="p-8 text-sm">Loading asset…</p>}>
      <AssetPrintClient tag={tag} />
    </Suspense>
  );
}
