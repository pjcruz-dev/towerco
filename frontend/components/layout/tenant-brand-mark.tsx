"use client";

import { Radio } from "lucide-react";

import { useTenantBrandingStore } from "@/stores/tenant-branding-store";
import { cn } from "@/lib/utils";

type Props = {
  className?: string;
  iconClassName?: string;
  /** sm = 28px, md = 32px (sidebar), lg = 40px (login) */
  size?: "sm" | "md" | "lg";
};

const boxClass: Record<NonNullable<Props["size"]>, string> = {
  sm: "h-7 w-7",
  md: "h-8 w-8",
  lg: "h-10 w-10",
};

/**
 * Tenant logo from superadmin branding (theme_tokens.logo_url) or TowerOS fallback mark.
 */
export function TenantBrandMark({ className, iconClassName, size = "md" }: Props) {
  const logoUrl = useTenantBrandingStore((s) => s.branding?.logo_url);
  const box = boxClass[size];

  if (logoUrl) {
    return (
      <div
        className={cn(
          "flex shrink-0 items-center justify-center overflow-hidden rounded bg-white/10",
          box,
          className,
        )}
      >
        {/* eslint-disable-next-line @next/next/no-img-element -- tenant HTTPS logo URL from platform admin */}
        <img
          src={logoUrl}
          alt=""
          className={cn("max-h-full max-w-full object-contain", iconClassName)}
          referrerPolicy="no-referrer"
        />
      </div>
    );
  }

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
