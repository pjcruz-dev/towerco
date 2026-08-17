"use client";

import { ThemeToggle } from "@/components/theme/theme-toggle";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

export function TenantAuthChrome({ children }: { children: React.ReactNode }) {
  const rawLogoUrl = useTenantBrandingStore((s) => s.branding?.logo_url);
  const logoUrl = resolveBrandingAssetUrl(rawLogoUrl);
  const organizationLabel = useOrganizationLabel();

  return (
    <div className="flex min-h-screen flex-col bg-background antialiased">
      <header className="shrink-0 border-b border-border bg-card">
        <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4 sm:px-6">
          <div className="flex items-center gap-3">
            {logoUrl ? (
              // eslint-disable-next-line @next/next/no-img-element -- organization logo from platform admin
              <img
                src={logoUrl}
                alt=""
                className="h-8 w-auto max-w-[160px] object-contain"
                referrerPolicy="no-referrer"
              />
            ) : (
              <span className="text-base font-semibold tracking-tight text-foreground">{organizationLabel}</span>
            )}
          </div>
          <ThemeToggle />
        </div>
      </header>
      <main className="flex flex-1 flex-col items-center justify-center p-6">{children}</main>
    </div>
  );
}
