import { Suspense } from "react";

import { RolloutDetailPageClient } from "./rollout-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function Page({ params }: PageProps) {
  const { id } = await params;
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading rollout…</p>}>
      <RolloutDetailPageClient rolloutId={id} />
    </Suspense>
  );
}