"use client";

import dynamic from "next/dynamic";

function OperationalMapPanelSkeleton() {
  return (
    <div
      className="relative flex min-h-[520px] flex-1 flex-col overflow-hidden rounded-lg border bg-card"
      aria-hidden
    >
      <div className="h-full min-h-[480px] w-full flex-1 animate-pulse bg-muted/40" />
    </div>
  );
}

/**
 * Lazily loads the MapLibre-backed GIS panel (client-only). Keeps the ~1MB maplibre-gl
 * bundle out of the /gis route's initial chunk until the map is actually rendered.
 */
export const OperationalMapPanel = dynamic(
  () => import("@/components/gis/operational-map-panel-impl").then((m) => m.OperationalMapPanelImpl),
  {
    ssr: false,
    loading: () => <OperationalMapPanelSkeleton />,
  },
);
