"use client";

import { useLayoutEffect, useState } from "react";

import { ThemeToggle } from "@/components/theme/theme-toggle";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { authPanelUsesBrandMark, cssColorToHex } from "@/lib/theme/tenant-chrome";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

function readTokenHex(variable: string): string | null {
  const probe = document.createElement("span");
  probe.style.color = `var(${variable})`;
  document.body.appendChild(probe);
  const hex = cssColorToHex(getComputedStyle(probe).color);
  probe.remove();
  return hex;
}

/** True when the workspace primary stays readable on the dark auth panel. */
function useAuthPanelBrandMark(): boolean {
  const [brand, setBrand] = useState(false);

  useLayoutEffect(() => {
    const read = () => {
      const primary = readTokenHex("--primary");
      const panel = readTokenHex(
        document.documentElement.classList.contains("dark") ? "--card" : "--foreground",
      );
      setBrand(primary !== null && panel !== null && authPanelUsesBrandMark(primary, panel));
    };

    read();
    const observer = new MutationObserver(read);
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class", "style"],
    });
    return () => observer.disconnect();
  }, []);

  return brand;
}

/**
 * Split-screen auth shell. Form on the left; dark brand panel on the right.
 * Rings and the label use the workspace color when it reads on that field
 * (green, teal, violet, rose, blue, amber, or a custom color). The default
 * near-black primary would vanish there, so those marks use the light ink.
 */
export function TenantAuthChrome({ children }: { children: React.ReactNode }) {
  const rawLogoUrl = useTenantBrandingStore((s) => s.branding?.logo_url);
  const logoUrl = resolveBrandingAssetUrl(rawLogoUrl);
  const organizationLabel = useOrganizationLabel();
  const brandMark = useAuthPanelBrandMark();
  const markText = brandMark ? "text-primary" : "text-background dark:text-card-foreground";
  const markFill = brandMark ? "bg-primary" : "bg-background dark:bg-card-foreground";
  const markWash = brandMark ? "bg-primary/20" : "bg-background/15 dark:bg-card-foreground/10";
  const markRing = brandMark ? "border-primary/40" : "border-background/35 dark:border-card-foreground/30";
  const markRingSoft = brandMark ? "border-primary/25" : "border-background/20 dark:border-card-foreground/15";

  return (
    <div className="grid min-h-app-shell bg-background antialiased lg:grid-cols-2">
      <section className="relative flex min-h-app-shell flex-col">
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
        className="relative hidden overflow-hidden bg-foreground text-background lg:flex lg:flex-col lg:justify-center lg:px-12 xl:px-16 dark:bg-card dark:text-card-foreground"
        aria-hidden
      >
        <div className="pointer-events-none absolute inset-0 bg-primary/12" />
        <div className={`pointer-events-none absolute inset-y-0 left-0 w-1 ${markFill}`} />
        <div className={`pointer-events-none absolute -right-28 -top-24 size-[26rem] rounded-full border ${markRingSoft}`} />
        <div className={`pointer-events-none absolute -right-10 -top-6 size-72 rounded-full border ${markRing}`} />
        <div className={`pointer-events-none absolute -bottom-28 -left-16 size-80 rounded-full ${markWash}`} />
        <div className="relative z-10 max-w-lg motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-150">
          <p className={`text-sm font-medium ${markText}`}>Secure workspace</p>
          <h2 className="mt-4 text-5xl font-semibold leading-[1.05] tracking-tight xl:text-6xl">
            {organizationLabel}
          </h2>
          <div className={`mt-8 h-1 w-16 rounded-full ${markFill}`} />
          <div className="mt-10 max-w-md rounded-xl border border-background/15 bg-background/10 px-5 py-5 dark:border-border dark:bg-background/40">
            <span className={`block size-2 rounded-full ${markFill}`} />
            <p className="mt-4 text-lg font-medium">Welcome back</p>
            <p className="mt-2 text-sm leading-relaxed text-background/75 dark:text-muted-foreground">
              Sign in to pick up the work that is waiting. Your session stays on this workspace.
            </p>
          </div>
        </div>
      </aside>
    </div>
  );
}
