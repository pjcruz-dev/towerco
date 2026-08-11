"use client";

import { FillAddressFromCoordinatesButton } from "@/components/rollout/fill-address-from-coordinates-button";
import { LocateCoordinatesFromAddressButton } from "@/components/rollout/locate-coordinates-from-address-button";
import { FormInput } from "@/components/forms/form-input";
import { formatCoordinate, toCoordinatePair } from "@/lib/rollout/coordinates";

type Props = {
  fullAddress: string;
  latitude: string;
  longitude: string;
  onFullAddressChange: (value: string) => void;
  onLatitudeChange: (value: string) => void;
  onLongitudeChange: (value: string) => void;
  coordinateError?: string | null;
  disabled?: boolean;
  hint?: string;
};

export function SiteProfileLocationFields({
  fullAddress,
  latitude,
  longitude,
  onFullAddressChange,
  onLatitudeChange,
  onLongitudeChange,
  coordinateError = null,
  disabled = false,
  hint,
}: Props) {
  const coords = toCoordinatePair(latitude, longitude);
  const hasCoordinates = coords != null;

  const setCoords = (lat: number, lng: number) => {
    onLatitudeChange(formatCoordinate(lat));
    onLongitudeChange(formatCoordinate(lng));
  };

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-end gap-2">
        <div className="min-w-0 flex-1">
          <FormInput
            label="Full address"
            placeholder="Street, barangay, city, province"
            value={fullAddress}
            onChange={(e) => onFullAddressChange(e.target.value)}
            disabled={disabled}
          />
        </div>
        <LocateCoordinatesFromAddressButton
          address={fullAddress}
          hasCoordinates={hasCoordinates}
          disabled={disabled}
          className="mb-0.5 shrink-0"
          onLocated={({ lat, lng, formattedAddress }) => {
            setCoords(lat, lng);
            if (formattedAddress && formattedAddress.trim() !== "" && formattedAddress.trim() !== fullAddress.trim()) {
              onFullAddressChange(formattedAddress.trim());
            }
          }}
        />
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <FormInput
          label="Latitude"
          placeholder="e.g. 14.5995"
          value={latitude}
          onChange={(e) => onLatitudeChange(e.target.value)}
          disabled={disabled}
        />
        <FormInput
          label="Longitude"
          placeholder="e.g. 120.9842"
          value={longitude}
          onChange={(e) => onLongitudeChange(e.target.value)}
          disabled={disabled}
        />
      </div>
      {coordinateError ? <p className="text-xs text-destructive">{coordinateError}</p> : null}

      <div className="flex flex-wrap gap-2">
        <FillAddressFromCoordinatesButton
          latitude={latitude}
          longitude={longitude}
          currentAddress={fullAddress}
          onFilled={onFullAddressChange}
          disabled={disabled || Boolean(coordinateError)}
        />
      </div>

      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  );
}
