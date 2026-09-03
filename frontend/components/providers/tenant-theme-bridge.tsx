"use client";

import { useTheme } from "next-themes";
import { useEffect, useState } from "react";

import { fetchTenantBranding, resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import {
  applyShellChromeTokens,
  applyTenantThemePalette,
  clearTenantThemeCssVariables,
  type ThemeColorMode,
} from "@/lib/theme/apply-tenant-theme";
import { isCentralHostname } from "@/lib/tenant/resolve-tenant-domain";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

function resolveMode(resolvedTheme: string | undefined): ThemeColorMode {
  return resolvedTheme === "dark" ? "dark" : "light";
}

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
      applyShellChromeTokens(resolveMode(resolvedTheme));
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
          applyShellChromeTokens(resolveMode(resolvedTheme));
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [hostname, setBranding, resolvedTheme]);

  useEffect(() => {
    const mode = resolveMode(resolvedTheme);

    if (!hostname || isCentralHostname(hostname)) {
      clearTenantThemeCssVariables();
      applyShellChromeTokens(mode);

      return;
    }

    if (!branding) {
      clearTenantThemeCssVariables();
      applyShellChromeTokens(mode);

      return;
    }

    clearTenantThemeCssVariables();
    const palette = mode === "dark" ? branding.dark : branding.light;
    applyTenantThemePalette(palette);
    applyShellChromeTokens(mode);

    if (typeof document !== "undefined" && branding.favicon_url) {
      const existing = document.querySelector<HTMLLinkElement>("link[rel='icon']");
      const link = existing ?? document.createElement("link");
      if (!existing) {
        link.rel = "icon";
        document.head.appendChild(link);
      }
      link.href = resolveBrandingAssetUrl(branding.favicon_url) ?? branding.favicon_url;
    }
  }, [branding, hostname, resolvedTheme]);

  return null;
}
