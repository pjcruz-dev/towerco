"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

type GeolocationState = {
  lat: number | null;
  lng: number | null;
  accuracyM: number | null;
  error: string | null;
  isLoading: boolean;
};

const initialState: GeolocationState = {
  lat: null,
  lng: null,
  accuracyM: null,
  error: null,
  isLoading: false,
};

function mapGeolocationError(error: GeolocationPositionError): string {
  if (error.code === error.PERMISSION_DENIED) {
    return "Location permission denied. Enable GPS in browser settings or enter coordinates manually.";
  }
  if (error.code === error.POSITION_UNAVAILABLE) {
    return "Location unavailable. Try again outdoors or enter coordinates manually.";
  }
  if (error.code === error.TIMEOUT) {
    return "Location request timed out. Try again or enter coordinates manually.";
  }

  return error.message || "Could not read GPS location.";
}

export function useGeolocation() {
  const [state, setState] = useState<GeolocationState>(initialState);

  const request = useCallback(() => {
    if (typeof window === "undefined" || !navigator.geolocation) {
      setState({
        lat: null,
        lng: null,
        accuracyM: null,
        error: "Geolocation is not supported on this device.",
        isLoading: false,
      });
      return;
    }

    setState((current) => ({ ...current, isLoading: true, error: null }));

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setState({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          accuracyM:
            typeof position.coords.accuracy === "number" && Number.isFinite(position.coords.accuracy)
              ? position.coords.accuracy
              : null,
          error: null,
          isLoading: false,
        });
      },
      (error) => {
        setState({
          lat: null,
          lng: null,
          accuracyM: null,
          error: mapGeolocationError(error),
          isLoading: false,
        });
      },
      {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
      },
    );
  }, []);

  const clear = useCallback(() => {
    setState(initialState);
  }, []);

  return useMemo(
    () => ({
      lat: state.lat,
      lng: state.lng,
      accuracyM: state.accuracyM,
      error: state.error,
      isLoading: state.isLoading,
      request,
      clear,
    }),
    [state, request, clear],
  );
}
