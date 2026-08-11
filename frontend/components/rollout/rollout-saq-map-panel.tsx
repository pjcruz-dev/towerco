"use client";

import { OperationalMap, type OperationalMapPin } from "@/components/maps/operational-map-lazy";
import { toCoordinatePair } from "@/lib/rollout/coordinates";
import type { RolloutCandidate, RolloutDetail } from "@/modules/rollout/types";

type Props = {
  detail: RolloutDetail | undefined;
  draggableCoords?: { lat: number; lng: number } | null;
  onDraggableCoordsChange?: (coords: { lat: number; lng: number }) => void;
};

function candidatePins(candidates: RolloutCandidate[]): OperationalMapPin[] {
  return candidates.flatMap((candidate) => {
    const coords = toCoordinatePair(candidate.latitude, candidate.longitude);
    if (!coords) {
      return [];
    }

    return [
      {
        id: candidate.id,
        lat: coords.lat,
        lng: coords.lng,
        label: candidate.label ?? `Candidate ${candidate.candidate_number}`,
        type: "candidate" as const,
        status: candidate.status,
      },
    ];
  });
}

export function RolloutSaqMapPanel({ detail, draggableCoords, onDraggableCoordsChange }: Props) {
  const pins: OperationalMapPin[] = [];

  const siteCoords = toCoordinatePair(detail?.site?.latitude, detail?.site?.longitude);
  if (detail?.site && siteCoords) {
    pins.push({
      id: detail.site.id,
      lat: siteCoords.lat,
      lng: siteCoords.lng,
      label: detail.site.name,
      type: "rollout_site",
      status: detail.status,
      rollout_id: detail.id,
      rollout_ref: detail.rollout_ref,
    });
  }

  pins.push(...candidatePins(detail?.candidates ?? []));

  const normalizedDraggable =
    draggableCoords == null ? null : toCoordinatePair(draggableCoords.lat, draggableCoords.lng);

  return (
    <OperationalMap
      pins={pins}
      draggablePin={normalizedDraggable}
      onDraggablePinMove={onDraggableCoordsChange}
      heightClassName="min-h-[320px]"
    />
  );
}
