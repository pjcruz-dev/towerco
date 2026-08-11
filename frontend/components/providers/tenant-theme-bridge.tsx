"use client";

import { useTheme } from "next-themes";
import { useEffect, useState } from "react";

import { fetchTenantBranding } from "@/lib/api/modules/branding-api";
import { applyTenantThemePalette, clearTenantThemeCssVariables } from "@/lib/theme/apply-tenant-theme";
import { isCentralHostname } from "@/lib/tenant/resolve-tenant-domain";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

/**
 * Loads public tenant branding on tenant hosts and applies CSS variables for the active color mode.
 */
export function TenantThemeBridge() {
  const { resolvedTheme } = useTheme();
  const branding = useTenantBrandingStore((s) => s.branding);
  const setBranding = useTenantBrandingStore((s) => s.setBranding);

  const [hostname, setHostname] = useState<string | null>(null);

  useEffect(() => {
    setHostname(window.location.hostname.toLowerCase());
  }, []);

  useEffect(() => {
    if (!hostname || isCentralHostname(hostname)) {
      clearTenantThemeCssVariables();
      setBranding(null);

      return undefined;
    }

    let cancelled = false;

    (async () => {
      try {
        const data = await fetchTenantBranding(hostname);
        if (!cancelled) {
          setBranding(data);
        }
      } catch {
        if (!cancelled) {
          setBranding(null);
          clearTenantThemeCssVariables();
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [hostname, setBranding]);

  useEffect(() => {
    if (!hostname || isCentralHostname(hostname)) {
      clearTenantThemeCssVariables();

      return;
    }

    if (!branding) {
      clearTenantThemeCssVariables();

      return;
    }

    clearTenantThemeCssVariables();
    const mode = resolvedTheme === "dark" ? "dark" : "light";
    const palette = mode === "dark" ? branding.dark : branding.light;
    applyTenantThemePalette(palette);

    if (typeof document !== "undefined" && branding.favicon_url) {
      const existing = document.querySelector<HTMLLinkElement>("link[rel='icon']");
      const link = existing ?? document.createElement("link");
      if (!existing) {
        link.rel = "icon";
        document.head.appendChild(link);
      }
      link.href = branding.favicon_url;
    }
  }, [branding, hostname, resolvedTheme]);

  return null;
}
