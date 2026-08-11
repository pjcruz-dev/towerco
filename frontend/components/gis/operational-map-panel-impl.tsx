"use client";

import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import { useEffect, useRef } from "react";

const DEFAULT_STYLE =
  process.env.NEXT_PUBLIC_MAPLIBRE_STYLE_URL ?? "https://demotiles.maplibre.org/style.json";

export function OperationalMapPanelImpl() {
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) {
      return undefined;
    }

    const map = new maplibregl.Map({
      container: el,
      style: DEFAULT_STYLE,
      center: [121.0437, 14.676],
      zoom: 5,
      // MapLibre v5 types: `attributionControl` is `false | AttributionControlOptions` only (not `true`).
      attributionControl: {},
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: true }), "top-right");

    return () => {
      map.remove();
    };
  }, []);

  return (
    <div className="relative flex min-h-[520px] flex-1 flex-col overflow-hidden rounded-lg border bg-card">
      <div className="absolute left-3 top-3 z-10 flex items-center gap-2 rounded-lg border border-border bg-card/95 px-3 py-2 text-xs font-medium text-foreground shadow-sm backdrop-blur-sm">
        <span className="text-muted-foreground">Basemap</span>
        <span className="font-mono text-[11px] text-primary">MapLibre</span>
      </div>
      <div ref={containerRef} className="h-full min-h-[480px] w-full flex-1" />
    </div>
  );
}
