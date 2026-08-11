"use client";

import { useState } from "react";
import { MapPin } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import {
  parseLocationAllowGeolocation,
  parseLocationValue,
  serializeLocationValue,
} from "@/modules/e-approval/field-type-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  disabled?: boolean;
  onChange: (next: string) => void;
};

export function EApprovalLocationField({ field, value, disabled, onChange }: Props) {
  const allowGeo = parseLocationAllowGeolocation(field);
  const parsed = parseLocationValue(value);
  const [locating, setLocating] = useState(false);

  const update = (patch: { lat?: string; lng?: string; label?: string }) => {
    const base = parsed ?? { lat: "", lng: "", label: undefined };
    const next = {
      lat: patch.lat !== undefined ? patch.lat : base.lat,
      lng: patch.lng !== undefined ? patch.lng : base.lng,
      label: patch.label !== undefined ? patch.label : base.label,
    };
    if (!next.lat.trim() || !next.lng.trim()) {
      onChange(
        serializeLocationValue({
          lat: next.lat,
          lng: next.lng,
          ...(next.label?.trim() ? { label: next.label.trim() } : {}),
        }),
      );
      return;
    }
    onChange(
      serializeLocationValue({
        lat: next.lat.trim(),
        lng: next.lng.trim(),
        ...(next.label?.trim() ? { label: next.label.trim() } : {}),
      }),
    );
  };

  const useMyLocation = () => {
    if (!navigator.geolocation) {
      return;
    }
    setLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        update({
          lat: String(pos.coords.latitude),
          lng: String(pos.coords.longitude),
        });
        setLocating(false);
      },
      () => setLocating(false),
      { enableHighAccuracy: true, timeout: 15000 },
    );
  };

  return (
    <div className="space-y-2">
      <div className="grid gap-2 sm:grid-cols-2">
        <Input
          disabled={disabled}
          type="text"
          inputMode="decimal"
          placeholder="Latitude"
          value={parsed?.lat ?? ""}
          onChange={(e) => update({ lat: e.target.value })}
        />
        <Input
          disabled={disabled}
          type="text"
          inputMode="decimal"
          placeholder="Longitude"
          value={parsed?.lng ?? ""}
          onChange={(e) => update({ lng: e.target.value })}
        />
      </div>
      <Input
        disabled={disabled}
        type="text"
        placeholder="Place label (optional)"
        value={parsed?.label ?? ""}
        onChange={(e) => update({ label: e.target.value })}
      />
      {allowGeo && !disabled ? (
        <Button type="button" size="sm" variant="outline" disabled={locating} onClick={useMyLocation}>
          {locating ? <Spinner className="mr-1" /> : <MapPin className="mr-1 h-4 w-4" />}
          Use my location
        </Button>
      ) : null}
    </div>
  );
}
