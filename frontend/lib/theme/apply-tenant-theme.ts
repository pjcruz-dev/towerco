import { TENANT_THEME_VARIABLE_KEYS } from "@/lib/theme/tenant-theme-keys";

export type TenantThemeModePalette = Partial<Record<(typeof TENANT_THEME_VARIABLE_KEYS)[number], string>>;

export type ThemeColorMode = "light" | "dark";

/**
 * Branding may tint charts/background, but shell chrome stays TowerOS design:
 * - aside tracks header surface (var(--card)) — same color as AppHeader
 * - primary CTA Geist near-black / near-white (not tenant accent blue)
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

/** Forced shell tokens — aside matches header (`bg-card`). */
const SHELL_CHROME: Record<ThemeColorMode, Record<string, string>> = {
  light: {
    primary: "#171717",
    "primary-foreground": "#fafafa",
    sidebar: "var(--card)",
    "sidebar-foreground": "var(--card-foreground)",
    "sidebar-primary": "var(--primary)",
    "sidebar-primary-foreground": "var(--primary-foreground)",
    "sidebar-accent": "var(--muted)",
    "sidebar-accent-foreground": "var(--foreground)",
    "sidebar-border": "var(--border)",
    "sidebar-ring": "var(--ring)",
  },
  dark: {
    primary: "oklch(0.985 0 0)",
    "primary-foreground": "oklch(0.205 0 0)",
    sidebar: "var(--card)",
    "sidebar-foreground": "var(--card-foreground)",
    "sidebar-primary": "var(--primary)",
    "sidebar-primary-foreground": "var(--primary-foreground)",
    "sidebar-accent": "var(--muted)",
    "sidebar-accent-foreground": "var(--foreground)",
    "sidebar-border": "var(--border)",
    "sidebar-ring": "var(--ring)",
  },
};

export function clearTenantThemeCssVariables(): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  for (const key of TENANT_THEME_VARIABLE_KEYS) {
    root.style.removeProperty(`--${key}`);
  }
}

/** Always re-apply shell chrome after clear/branding so aside follows light/dark. */
export function applyShellChromeTokens(mode: ThemeColorMode): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  const shell = SHELL_CHROME[mode];
  for (const [key, value] of Object.entries(shell)) {
    root.style.setProperty(`--${key}`, value);
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
