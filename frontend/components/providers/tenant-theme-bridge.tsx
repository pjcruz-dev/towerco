"use client";

import { useTheme } from "next-themes";
import { useEffect, useState } from "react";

import { fetchTenantBranding, resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { applyTenantThemePalette, clearTenantThemeCssVariables } from "@/lib/theme/apply-tenant-theme";
import { applyPreviewPrimary, applyTenantChromeSurface } from "@/lib/theme/tenant-chrome";
import { useResolvedTenantChrome, useTenantAppearanceStore } from "@/stores/tenant-appearance-store";
import { isCentralHostname } from "@/lib/tenant/resolve-tenant-domain";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

/**
 * Loads public tenant branding on tenant hosts and applies CSS variables for the active color mode.
 */
export function TenantThemeBridge() {
  const { resolvedTheme } = useTheme();
  const branding = useTenantBrandingStore((s) => s.branding);
  const setBranding = useTenantBrandingStore((s) => s.setBranding);
  const preview = useTenantAppearanceStore((state) => state.preview);
  const chrome = useResolvedTenantChrome();

  const [hostname, setHostname] = useState<string | null>(null);

  useEffect(() => {
    setHostname(window.location.hostname.toLowerCase());
  }, []);

  useEffect(() => {
    if (!hostname || isCentralHostname(hostname)) {
      clearTenantThemeCssVariables();
      applyTenantChromeSurface(null);
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
          applyTenantChromeSurface(null);
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
      applyTenantChromeSurface(null);

      return;
    }

    if (!branding && !preview && !chrome) {
      clearTenantThemeCssVariables();
      applyTenantChromeSurface(null);

      return;
    }

    clearTenantThemeCssVariables();
    const mode = resolvedTheme === "dark" ? "dark" : "light";
    const palette = mode === "dark" ? branding?.dark : branding?.light;
    applyTenantThemePalette(palette);
    if (preview) {
      applyPreviewPrimary(preview);
    } else if (chrome?.primary_preset) {
      applyPreviewPrimary(chrome);
    }
    applyTenantChromeSurface(chrome);

    if (typeof document !== "undefined" && branding?.favicon_url) {
      const existing = document.querySelector<HTMLLinkElement>("link[rel='icon']");
      const link = existing ?? document.createElement("link");
      if (!existing) {
        link.rel = "icon";
        document.head.appendChild(link);
      }
      link.href = resolveBrandingAssetUrl(branding.favicon_url) ?? branding.favicon_url;
    }
  }, [branding, chrome, hostname, preview, resolvedTheme]);

  return null;
}
