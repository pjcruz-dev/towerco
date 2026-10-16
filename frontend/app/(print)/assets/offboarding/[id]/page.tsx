import { Suspense } from "react";

import { OffboardingPrintClient } from "./offboarding-print-client";

export default async function OffboardingPrintPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return (
    <Suspense fallback={<p className="p-8 text-sm">Loading checklist…</p>}>
      <OffboardingPrintClient id={id} />
    </Suspense>
  );
}
