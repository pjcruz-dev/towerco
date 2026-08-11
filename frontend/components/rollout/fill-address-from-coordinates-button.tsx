"use client";

import { useMutation } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { reverseGeocodeCoordinates } from "@/lib/api/modules/rollout-api";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  latitude: string;
  longitude: string;
  currentAddress: string;
  onFilled: (address: string) => void;
  disabled?: boolean;
  className?: string;
};

function parseCoordinatePair(latitude: string, longitude: string): { lat: number; lng: number } | null {
  const latRaw = latitude.trim();
  const lngRaw = longitude.trim();
  if (latRaw === "" || lngRaw === "") {
    return null;
  }
  const lat = Number(latRaw);
  const lng = Number(lngRaw);
  if (!Number.isFinite(lat) || lat < -90 || lat > 90) {
    return null;
  }
  if (!Number.isFinite(lng) || lng < -180 || lng > 180) {
    return null;
  }
  return { lat, lng };
}

export function FillAddressFromCoordinatesButton({
  latitude,
  longitude,
  currentAddress,
  onFilled,
  disabled = false,
  className,
}: Props) {
  const push = useNotificationStore((state) => state.push);
  const coords = parseCoordinatePair(latitude, longitude);

  const mutation = useMutation({
    mutationFn: () => {
      if (!coords) {
        throw new Error("Enter valid latitude and longitude first.");
      }
      return reverseGeocodeCoordinates({ latitude: coords.lat, longitude: coords.lng });
    },
    onSuccess: (result) => {
      const next = result.formatted_address.trim();
      if (!next) {
        push({
          level: "error",
          title: "No address found",
          message: "The geocoder returned an empty result for these coordinates.",
        });
        return;
      }
      if (currentAddress.trim() !== "" && currentAddress.trim() !== next) {
        const confirmed = window.confirm(
          "Full address already has a value. Replace it with the address from these coordinates?",
        );
        if (!confirmed) {
          return;
        }
      }
      onFilled(next);
      push({
        level: "success",
        title: "Address filled",
        message: `Resolved via ${result.provider}. Review and edit if needed.`,
      });
    },
    onError: (error) => {
      push({
        level: "error",
        title: "Could not fill address",
        message: getErrorMessage(error),
      });
    },
  });

  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      className={className}
      disabled={disabled || !coords || mutation.isPending}
      onClick={() => mutation.mutate()}
    >
      {mutation.isPending ? "Resolving…" : "Fill from coordinates"}
    </Button>
  );
}
