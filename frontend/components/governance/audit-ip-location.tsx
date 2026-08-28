"use client";

import { useEffect, useState } from "react";
import { ExternalLink, MapPin } from "lucide-react";

import { buttonVariants } from "@/components/ui/button";
import {
  googleMapsUrl,
  ipInfoUrl,
  isPublicClientIp,
  lookupIpGeo,
  type IpGeoLookup,
} from "@/lib/workspace/audit-ip-geo";
import { cn } from "@/lib/utils";

type CompactProps = {
  ip: string | null | undefined;
  className?: string;
};

/** Table cell: IP + link to approximate ISP location (not GPS). */
export function AuditIpCompact({ ip, className }: CompactProps) {
  const value = ip?.trim() || "";
  if (!value) {
    return <span className={className}>—</span>;
  }

  if (!isPublicClientIp(value)) {
    return <span className={cn("font-mono text-xs text-muted-foreground", className)}>{value}</span>;
  }

  return (
    <a
      href={ipInfoUrl(value)}
      target="_blank"
      rel="noopener noreferrer"
      className={cn(
        "inline-flex max-w-[11rem] items-center gap-1 font-mono text-xs text-primary hover:underline",
        className,
      )}
      title="Approx. ISP location (not GPS)"
      onClick={(event) => event.stopPropagation()}
    >
      <span className="truncate">{value}</span>
      <ExternalLink className="h-3 w-3 shrink-0 opacity-70" aria-hidden />
    </a>
  );
}

type DetailProps = {
  ip: string | null | undefined;
};

/** Detail drawer: city/region from IP + map link. No precise coordinates shown. */
export function AuditIpDetail({ ip }: DetailProps) {
  const value = ip?.trim() || "";
  const [geo, setGeo] = useState<IpGeoLookup | null>(null);
  const [status, setStatus] = useState<"idle" | "loading" | "ready" | "unavailable">("idle");

  useEffect(() => {
    if (!isPublicClientIp(value)) {
      setGeo(null);
      setStatus("idle");
      return;
    }

    let cancelled = false;
    setStatus("loading");
    lookupIpGeo(value)
      .then((result) => {
        if (cancelled) return;
        setGeo(result);
        setStatus(result ? "ready" : "unavailable");
      })
      .catch(() => {
        if (cancelled) return;
        setGeo(null);
        setStatus("unavailable");
      });

    return () => {
      cancelled = true;
    };
  }, [value]);

  if (!value) {
    return <span>—</span>;
  }

  if (!isPublicClientIp(value)) {
    return (
      <div className="space-y-1">
        <p className="font-mono text-sm">{value}</p>
        <p className="text-xs text-muted-foreground">Private / internal address — no ISP map.</p>
      </div>
    );
  }

  const mapsHref =
    geo?.latitude != null && geo?.longitude != null
      ? googleMapsUrl(geo.latitude, geo.longitude)
      : ipInfoUrl(value);

  return (
    <div className="space-y-2">
      <p className="font-mono text-sm">{value}</p>
      {status === "loading" ? (
        <p className="text-xs text-muted-foreground">Looking up approx. ISP location…</p>
      ) : null}
      {status === "ready" && geo ? (
        <div className="space-y-1.5">
          <p className="text-xs font-medium text-muted-foreground">Approx. ISP location</p>
          <p className="flex items-start gap-1.5 text-sm text-foreground">
            <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden />
            <span>{geo.label}</span>
          </p>
          <p className="text-[11px] leading-relaxed text-muted-foreground">
            Based on the public IP / ISP region — not the user’s GPS or exact address. Useful for
            spotting unusual networks, not proving desk location.
          </p>
        </div>
      ) : null}
      {status === "unavailable" ? (
        <p className="text-xs text-muted-foreground">
          Approx. ISP lookup unavailable — you can still open the region map link.
        </p>
      ) : null}
      <a
        href={mapsHref}
        target="_blank"
        rel="noopener noreferrer"
        className={cn(buttonVariants({ variant: "outline", size: "sm" }), "inline-flex gap-1.5")}
      >
        View region on map
        <ExternalLink className="h-3.5 w-3.5" aria-hidden />
      </a>
    </div>
  );
}
