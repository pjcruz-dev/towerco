import { TENANT_THEME_VARIABLE_KEYS } from "@/lib/theme/tenant-theme-keys";

export type TenantThemeModePalette = Partial<Record<(typeof TENANT_THEME_VARIABLE_KEYS)[number], string>>;

/**
 * Branding may tint charts/background, but shell chrome stays TowerOS design:
 * - side nav white
 * - primary CTA Geist near-black (not tenant accent blue)
 */
const LOCKED_THEME_KEYS = new Set([
  "primary",
  "primary-foreground",
  "sidebar",
  "sidebar-foreground",
  "sidebar-primary",
  "sidebar-primary-foreground",
  "sidebar-accent",
  "sidebar-accent-foreground",
  "sidebar-border",
  "sidebar-ring",
]);

export function clearTenantThemeCssVariables(): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  for (const key of TENANT_THEME_VARIABLE_KEYS) {
    root.style.removeProperty(`--${key}`);
  }
}

export function applyTenantThemePalette(
  palette: TenantThemeModePalette | undefined,
): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  if (!palette) {
    return;
  }
  for (const key of TENANT_THEME_VARIABLE_KEYS) {
    if (LOCKED_THEME_KEYS.has(key)) {
      continue;
    }
    const value = palette[key];
    if (typeof value === "string" && value.trim() !== "") {
      root.style.setProperty(`--${key}`, value.trim());
    }
  }
}
