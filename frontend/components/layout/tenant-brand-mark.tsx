"use client";

import { Radio } from "lucide-react";
import { useEffect, useState } from "react";

import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";
import { cn } from "@/lib/utils";

type Props = {
  className?: string;
  iconClassName?: string;
  /** sm = 28px, md = 32px (sidebar), lg = 40px (login) */
  size?: "sm" | "md" | "lg";
  /** Override store logo — used for live preview in platform branding. */
  src?: string | null;
};

const boxClass: Record<NonNullable<Props["size"]>, string> = {
  sm: "h-7 w-7",
  md: "h-8 w-8",
  lg: "h-10 w-10",
};

function FallbackMark({
  className,
  iconClassName,
  size,
}: {
  className?: string;
  iconClassName?: string;
  size: NonNullable<Props["size"]>;
}) {
  const box = boxClass[size];

  return (
    <div
      className={cn(
        "flex shrink-0 items-center justify-center rounded bg-blue-600 text-white shadow-lg shadow-blue-600/20",
        box,
        className,
      )}
    >
      <Radio className={cn(size === "lg" ? "h-6 w-6" : "h-5 w-5", iconClassName)} />
    </div>
  );
}

/**
 * Tenant logo from superadmin branding (theme_tokens.logo_url) or TowerOS fallback mark.
 */
export function TenantBrandMark({ className, iconClassName, size = "md", src }: Props) {
  const storedUrl = useTenantBrandingStore((s) => s.branding?.logo_url);
  const rawUrl = src !== undefined ? src : storedUrl;
  const logoUrl = resolveBrandingAssetUrl(rawUrl);
  const [failed, setFailed] = useState(false);
  const box = boxClass[size];

  useEffect(() => {
    setFailed(false);
  }, [logoUrl]);

  if (!logoUrl || failed) {
    return <FallbackMark className={className} iconClassName={iconClassName} size={size} />;
  }

  return (
    <div
      className={cn(
        "flex shrink-0 items-center justify-center overflow-hidden rounded bg-white/10",
        box,
        className,
      )}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- tenant logo URL from platform admin */}
      <img
        key={logoUrl}
        src={logoUrl}
        alt=""
        className={cn("max-h-full max-w-full object-contain", iconClassName)}
        referrerPolicy="no-referrer"
        onError={() => setFailed(true)}
      />
    </div>
  );
}
