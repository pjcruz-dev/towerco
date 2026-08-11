"use client";

import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useRef } from "react";

const DEFAULT_STYLE =
  process.env.NEXT_PUBLIC_MAPLIBRE_STYLE_URL ?? "https://demotiles.maplibre.org/style.json";

const DEFAULT_CENTER: [number, number] = [121.0437, 14.676];

export type OperationalMapPin = {
  id: string;
  lat: number;
  lng: number;
  label: string;
  type: "site" | "rollout_site" | "candidate";
  status?: string;
  rollout_id?: string | null;
  rollout_ref?: string | null;
};

type DraggablePin = {
  lat: number;
  lng: number;
};

type Props = {
  pins?: OperationalMapPin[];
  heightClassName?: string;
  onPinClick?: (pin: OperationalMapPin) => void;
  draggablePin?: DraggablePin | null;
  onDraggablePinMove?: (coords: DraggablePin) => void;
  /** When set with a draggable pin handler, map clicks place/move the pin. */
  onMapClick?: (coords: DraggablePin) => void;
  linkRolloutPins?: boolean;
};

function pinColor(type: OperationalMapPin["type"], status?: string): string {
  if (type === "candidate") {
    if (status === "selected") return "#16A34A";
    if (status === "rejected") return "#DC2626";
    return "#2563EB";
  }
  if (type === "rollout_site") return "#D97706";
  if (status === "warning") return "#D97706";
  if (status === "critical") return "#DC2626";
  return "#22C55E";
}

function boundsFromPins(pins: OperationalMapPin[], draggable?: DraggablePin | null): maplibregl.LngLatBounds | null {
  const coords: [number, number][] = pins.map((pin) => [pin.lng, pin.lat]);
  if (draggable) {
    coords.push([draggable.lng, draggable.lat]);
  }
  if (coords.length === 0) {
    return null;
  }

  const bounds = new maplibregl.LngLatBounds(coords[0], coords[0]);
  coords.slice(1).forEach((coord) => bounds.extend(coord));
  return bounds;
}

export function OperationalMap({
  pins = [],
  heightClassName = "min-h-[360px]",
  onPinClick,
  draggablePin,
  onDraggablePinMove,
  onMapClick,
  linkRolloutPins = false,
}: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const mapRef = useRef<maplibregl.Map | null>(null);
  const markersRef = useRef<maplibregl.Marker[]>([]);
  const dragMarkerRef = useRef<maplibregl.Marker | null>(null);
  const onMapClickRef = useRef(onMapClick);
  const router = useRouter();

  useEffect(() => {
    onMapClickRef.current = onMapClick;
  }, [onMapClick]);

  const center = useMemo(() => {
    if (pins.length > 0) {
      return [pins[0].lng, pins[0].lat] as [number, number];
    }
    if (draggablePin) {
      return [draggablePin.lng, draggablePin.lat] as [number, number];
    }
    return DEFAULT_CENTER;
  }, [pins, draggablePin]);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) {
      return undefined;
    }

    const map = new maplibregl.Map({
      container: el,
      style: DEFAULT_STYLE,
      center,
      zoom: pins.length > 0 || draggablePin ? 12 : 5,
      attributionControl: {},
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: true }), "top-right");
    map.on("click", (event) => {
      onMapClickRef.current?.({ lat: event.lngLat.lat, lng: event.lngLat.lng });
    });
    mapRef.current = map;

    return () => {
      markersRef.current.forEach((marker) => marker.remove());
      markersRef.current = [];
      dragMarkerRef.current?.remove();
      dragMarkerRef.current = null;
      map.remove();
      mapRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- map instance is created once per mount
  }, []);

  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;

    markersRef.current.forEach((marker) => marker.remove());
    markersRef.current = [];

    pins.forEach((pin) => {
      const element = document.createElement("button");
      element.type = "button";
      element.title = pin.label;
      element.className = "size-3.5 rounded-full border-2 border-white shadow-md";
      element.style.backgroundColor = pinColor(pin.type, pin.status);
      element.addEventListener("click", (event) => {
        event.stopPropagation();
        if (onPinClick) {
          onPinClick(pin);
          return;
        }
        if (linkRolloutPins && pin.rollout_id) {
          router.push(`/project-one/rollouts/${pin.rollout_id}`);
        }
      });

      const marker = new maplibregl.Marker({ element }).setLngLat([pin.lng, pin.lat]).addTo(map);
      markersRef.current.push(marker);
    });

    const bounds = boundsFromPins(pins, draggablePin);
    if (bounds) {
      map.fitBounds(bounds, { padding: 48, maxZoom: 14, duration: 0 });
    }
  }, [pins, draggablePin, onPinClick, linkRolloutPins, router]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;

    dragMarkerRef.current?.remove();
    dragMarkerRef.current = null;

    if (!draggablePin) {
      return;
    }

    const element = document.createElement("div");
    element.className = "size-4 rounded-full border-2 border-white bg-primary shadow-lg";

    const marker = new maplibregl.Marker({ element, draggable: true })
      .setLngLat([draggablePin.lng, draggablePin.lat])
      .addTo(map);

    marker.on("dragend", () => {
      const lngLat = marker.getLngLat();
      onDraggablePinMove?.({ lat: lngLat.lat, lng: lngLat.lng });
    });

    dragMarkerRef.current = marker;
  }, [draggablePin, onDraggablePinMove]);

  return (
    <div className={`relative overflow-hidden rounded-lg border border-slate-800 bg-slate-950 ${heightClassName}`}>
      <div ref={containerRef} className="h-full w-full min-h-[280px]" />
      {pins.length === 0 && !draggablePin ? (
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-slate-400">
          {onMapClick ? "Click the map to drop a pin." : "No map coordinates yet."}
        </div>
      ) : null}
    </div>
  );
}
