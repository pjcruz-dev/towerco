export function coerceCoordinate(value: string | number | null | undefined): number | null {
  if (value == null || value === "") {
    return null;
  }

  const numeric = typeof value === "number" ? value : Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

export function formatCoordinate(value: string | number | null | undefined, digits = 6): string {
  const numeric = coerceCoordinate(value);
  return numeric == null ? "" : numeric.toFixed(digits);
}

export function toCoordinatePair(
  lat: string | number | null | undefined,
  lng: string | number | null | undefined,
): { lat: number; lng: number } | null {
  const latitude = coerceCoordinate(lat);
  const longitude = coerceCoordinate(lng);

  if (latitude == null || longitude == null) {
    return null;
  }

  const validated = validateCoordinatePair(lat, lng);

  return validated.ok ? { lat: validated.lat, lng: validated.lng } : null;
}

export type CoordinateCaptureMethod = "gps" | "map_drag" | "manual";

export type CoordinateValidationResult =
  | { ok: true; lat: number; lng: number; swapped: boolean }
  | { ok: false; message: string };

/** Client-side guard before save; server normalizes swapped lat/lng as well. */
export function validateCoordinatePair(
  lat: string | number | null | undefined,
  lng: string | number | null | undefined,
): CoordinateValidationResult {
  const latitude = coerceCoordinate(lat);
  const longitude = coerceCoordinate(lng);

  if (latitude == null && longitude == null) {
    return { ok: false, message: "" };
  }

  if (latitude == null || longitude == null) {
    return { ok: false, message: "Enter both latitude and longitude, or clear both." };
  }

  if (Math.abs(latitude) > 90 && Math.abs(longitude) <= 90) {
    return { ok: true, lat: longitude, lng: latitude, swapped: true };
  }

  if (Math.abs(latitude) > 90) {
    return { ok: false, message: "Latitude must be between -90 and 90." };
  }

  if (Math.abs(longitude) > 180) {
    return { ok: false, message: "Longitude must be between -180 and 180." };
  }

  return { ok: true, lat: latitude, lng: longitude, swapped: false };
}

export function hasCoordinatePair(
  lat: string | number | null | undefined,
  lng: string | number | null | undefined,
): boolean {
  return coerceCoordinate(lat) != null && coerceCoordinate(lng) != null;
}
