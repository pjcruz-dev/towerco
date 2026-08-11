"use client";

import { useMutation } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { forwardGeocodeAddress } from "@/lib/api/modules/rollout-api";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  address: string;
  hasCoordinates: boolean;
  onLocated: (coords: { lat: number; lng: number; formattedAddress?: string }) => void;
  disabled?: boolean;
  className?: string;
};

export function LocateCoordinatesFromAddressButton({
  address,
  hasCoordinates,
  onLocated,
  disabled = false,
  className,
}: Props) {
  const push = useNotificationStore((state) => state.push);
  const query = address.trim();

  const mutation = useMutation({
    mutationFn: () => {
      if (query.length < 3) {
        throw new Error("Enter at least 3 characters in Full address first.");
      }
      return forwardGeocodeAddress({ query });
    },
    onSuccess: (result) => {
      if (hasCoordinates) {
        const confirmed = window.confirm(
          "Latitude/longitude already have values. Replace them with the location for this address?",
        );
        if (!confirmed) {
          return;
        }
      }
      onLocated({
        lat: result.latitude,
        lng: result.longitude,
        formattedAddress: result.formatted_address,
      });
      push({
        level: "success",
        title: "Coordinates located",
        message: `Resolved via ${result.provider}. Review latitude and longitude if needed.`,
      });
    },
    onError: (error) => {
      push({
        level: "error",
        title: "Could not locate address",
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
      disabled={disabled || query.length < 3 || mutation.isPending}
      onClick={() => mutation.mutate()}
    >
      {mutation.isPending ? "Locating…" : "Locate from address"}
    </Button>
  );
}
