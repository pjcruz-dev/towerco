"use client";

import { useTheme } from "next-themes";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";

import { fetchTenantBranding, resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { resolveModuleShell } from "@/lib/navigation/module-shell";
import { applyTenantThemePalette, clearTenantThemeCssVariables } from "@/lib/theme/apply-tenant-theme";
import { moduleStyleFor } from "@/lib/theme/module-accents";
import {
  applyChosenModuleSurfaces,
  applyModuleCharts,
  applyPreviewPrimary,
  applyTenantChromeSurface,
  clearModuleSurfaces,
  isHex6,
  readSwatchHex,
} from "@/lib/theme/tenant-chrome";
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
  const modulePreview = useTenantAppearanceStore((state) => state.modulePreview);
  const chrome = useResolvedTenantChrome();
  const pathname = usePathname();
  const shell = resolveModuleShell(pathname ?? "/");
  const moduleStyle =
    shell.kind === "module"
      ? moduleStyleFor(modulePreview ?? branding?.module_accents, shell.id)
      : null;
  const moduleAccent =
    moduleStyle?.primary_preset && moduleStyle.primary_preset !== "plain"
      ? { primary_preset: moduleStyle.primary_preset, primary: moduleStyle.primary }
      : null;

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

    if (!branding && !preview && !chrome && !moduleStyle) {
      clearTenantThemeCssVariables();
      clearModuleSurfaces();
      applyTenantChromeSurface(null);

      return;
    }

    clearTenantThemeCssVariables();
    clearModuleSurfaces();
    const mode = resolvedTheme === "dark" ? "dark" : "light";
    const palette = mode === "dark" ? branding?.dark : branding?.light;
    applyTenantThemePalette(palette);
    const personal = preview ?? chrome;
    if (moduleAccent) {
      applyPreviewPrimary({
        saved: true,
        primary_preset: moduleAccent.primary_preset,
        primary: moduleAccent.primary,
        skin: personal?.skin ?? "default",
        layout: personal?.layout ?? "vertical",
        content: personal?.content ?? "compact",
      });
    } else if (personal?.primary_preset) {
      applyPreviewPrimary(personal);
    }
    const accentHex =
      moduleAccent == null
        ? null
        : moduleAccent.primary_preset === "custom" && isHex6(moduleAccent.primary)
          ? moduleAccent.primary
          : readSwatchHex(moduleAccent.primary_preset);
    if (accentHex) {
      applyModuleCharts(accentHex, mode);
    }
    if (moduleStyle) {
      applyChosenModuleSurfaces(moduleStyle, mode);
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
  }, [branding, chrome, hostname, moduleAccent, modulePreview, moduleStyle, preview, resolvedTheme]);

  return null;
}
