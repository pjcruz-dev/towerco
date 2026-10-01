import { Suspense } from "react";

import { DynamicRecordDetailPageClient } from "./dynamic-record-detail-page-client";

export default async function DynamicRecordDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return (
    <Suspense fallback={<p className="text-sm text-muted-foreground">Loading…</p>}>
      <DynamicRecordDetailPageClient recordId={id} />
    </Suspense>
  );
}
