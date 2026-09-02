"use client";

import { ThemeToggle } from "@/components/theme/theme-toggle";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

/**
 * Split-screen auth shell (NextAdmin-style layout, TowerOS operational styling).
 * Form on the left; brand panel on the right (desktop). Not purple marketing chrome.
 */
export function TenantAuthChrome({ children }: { children: React.ReactNode }) {
  const rawLogoUrl = useTenantBrandingStore((s) => s.branding?.logo_url);
  const logoUrl = resolveBrandingAssetUrl(rawLogoUrl);
  const organizationLabel = useOrganizationLabel();

  return (
    <div className="grid min-h-screen bg-background antialiased lg:grid-cols-2">
      <section className="relative flex min-h-screen flex-col">
        <header className="flex h-14 shrink-0 items-center justify-between px-4 sm:px-8">
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
              <span className="text-base font-semibold tracking-tight text-foreground">
                {organizationLabel}
              </span>
            )}
          </div>
          <ThemeToggle />
        </header>

        <main className="flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-8">
          <div className="w-full max-w-[420px]">{children}</div>
        </main>
      </section>

      <aside
        className="relative hidden overflow-hidden bg-slate-900 text-slate-50 lg:flex lg:flex-col lg:justify-center lg:px-12 xl:px-16"
        aria-hidden
      >
        <div
          className="pointer-events-none absolute inset-0 opacity-[0.12]"
          style={{
            backgroundImage:
              "repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(255,255,255,0.35) 10px, rgba(255,255,255,0.35) 11px)",
          }}
        />
        <div className="relative z-10 max-w-md space-y-5">
          <p className="text-sm font-medium text-slate-300">
            Built for operators · Designed for clarity
          </p>
          <h2 className="text-3xl font-semibold leading-tight tracking-tight text-white xl:text-4xl">
            {organizationLabel}
          </h2>
          <p className="text-sm leading-relaxed text-slate-300">
            Review and complete approvals in one secure workspace.
          </p>
        </div>
      </aside>
    </div>
  );
}
