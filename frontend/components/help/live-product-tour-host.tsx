"use client";

import { Suspense } from "react";

import { LiveProductTour } from "@/components/help/live-product-tour";

/** Suspense boundary for `useSearchParams` inside the live tour. */
export function LiveProductTourHost() {
  return (
    <Suspense fallback={null}>
      <LiveProductTour />
    </Suspense>
  );
}
