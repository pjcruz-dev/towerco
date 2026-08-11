import { TENANT_THEME_VARIABLE_KEYS } from "@/lib/theme/tenant-theme-keys";

export type TenantThemeModePalette = Partial<Record<(typeof TENANT_THEME_VARIABLE_KEYS)[number], string>>;

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
    const value = palette[key];
    if (typeof value === "string" && value.trim() !== "") {
      root.style.setProperty(`--${key}`, value.trim());
    }
  }
}
