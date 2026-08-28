/**
 * Audit IP helpers — approximate location from public IP (not GPS).
 */

const PRIVATE_IP =
  /^(?:127\.|10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[0-1])\.|0\.|::1|fc|fd|fe80)/i;

export function isPublicClientIp(ip: string | null | undefined): boolean {
  const value = (ip ?? "").trim();
  if (!value) return false;
  return !PRIVATE_IP.test(value);
}

/** External lookup page (map + ISP) — no API key. */
export function ipInfoUrl(ip: string): string {
  return `https://ipinfo.io/${encodeURIComponent(ip)}`;
}

export function googleMapsUrl(lat: number, lon: number): string {
  return `https://www.google.com/maps?q=${lat},${lon}`;
}

export type IpGeoLookup = {
  city: string | null;
  region: string | null;
  country: string | null;
  latitude: number | null;
  longitude: number | null;
  label: string;
};

type IpApiCoResponse = {
  error?: boolean;
  reason?: string;
  city?: string | null;
  region?: string | null;
  country_name?: string | null;
  latitude?: number | null;
  longitude?: number | null;
};

export async function lookupIpGeo(ip: string): Promise<IpGeoLookup | null> {
  if (!isPublicClientIp(ip)) {
    return null;
  }

  const response = await fetch(`https://ipapi.co/${encodeURIComponent(ip)}/json/`, {
    headers: { Accept: "application/json" },
  });
  if (!response.ok) {
    return null;
  }

  const data = (await response.json()) as IpApiCoResponse;
  if (data.error) {
    return null;
  }

  const city = data.city?.trim() || null;
  const region = data.region?.trim() || null;
  const country = data.country_name?.trim() || null;
  const latitude = typeof data.latitude === "number" ? data.latitude : null;
  const longitude = typeof data.longitude === "number" ? data.longitude : null;

  const label = [city, region, country].filter(Boolean).join(", ") || "Approximate location";

  return { city, region, country, latitude, longitude, label };
}
