"use client";

import dynamic from "next/dynamic";

export type { OperationalMapPin } from "@/components/maps/operational-map";

function MapSkeleton({ heightClassName }: { heightClassName?: string }) {
  return (
    <div
      className={`w-full animate-pulse rounded-lg border border-border bg-muted/40 ${
        heightClassName ?? "min-h-[360px]"
      }`}
      aria-hidden
    />
  );
}

/**
 * Lazily loads the MapLibre-backed OperationalMap (client-only). Keeps the ~1MB maplibre-gl
 * bundle out of the initial page chunk until the map is actually rendered.
 */
export const OperationalMap = dynamic(
  () => import("@/components/maps/operational-map").then((m) => m.OperationalMap),
  {
    ssr: false,
    loading: () => <MapSkeleton />,
  },
);
