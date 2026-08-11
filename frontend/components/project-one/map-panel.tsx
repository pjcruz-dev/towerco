"use client";

import type { ProjectOneMapPin } from "@/modules/project-one/types";

import { OperationalMap } from "@/components/maps/operational-map-lazy";
import { RefreshingHint } from "@/components/ui/refreshing-hint";

type Props = {
  pins?: ProjectOneMapPin[];
  sites?: Array<{ id: string; name: string; lat: number; lng: number; status: string }>;
  isLoading?: boolean;
};

export function MapPanel({ pins, sites = [], isLoading = false }: Props) {
  const mapPins: ProjectOneMapPin[] =
    pins ??
    sites.map((site) => ({
      id: site.id,
      lat: site.lat,
      lng: site.lng,
      label: site.name,
      type: "site" as const,
      status: site.status,
      rollout_id: null,
      rollout_ref: null,
    }));

  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm">
      <div className="mb-3 flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold">Network Map</h2>
          <p className="text-xs text-muted-foreground">
            Rollout candidates and linked sites. Click a rollout pin to open program detail.
          </p>
        </div>
        <div className="flex flex-wrap gap-2 text-[11px] text-muted-foreground">
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2 rounded-full bg-green-500" /> Site
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2 rounded-full bg-amber-500" /> Selected site
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2 rounded-full bg-blue-600" /> Candidate
          </span>
        </div>
      </div>

      {isLoading && pins === undefined ? (
        <div className="flex min-h-[360px] items-center justify-center rounded-lg border border-dashed border-border bg-muted/20">
          <RefreshingHint label="Loading map pins" />
        </div>
      ) : (
        <OperationalMap pins={mapPins} linkRolloutPins heightClassName="min-h-[360px]" />
      )}
    </section>
  );
}
