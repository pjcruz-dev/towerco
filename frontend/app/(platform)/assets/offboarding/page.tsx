"use client";

import { Suspense } from "react";

import { OffboardingPageClient } from "./offboarding-page-client";

export default function OffboardingPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading offboarding…</div>}>
      <OffboardingPageClient />
    </Suspense>
  );
}
