export const TENANT_CHROME_PRESETS = [
  { id: "violet", label: "Violet" },
  { id: "teal", label: "Teal" },
  { id: "rose", label: "Rose" },
  { id: "amber", label: "Amber" },
  { id: "blue", label: "Blue" },
] as const;

export type TenantChromePresetId = (typeof TENANT_CHROME_PRESETS)[number]["id"];

export type TenantChrome = {
  saved: boolean;
  primary_preset: TenantChromePresetId | "custom" | null;
  primary: string | null;
  skin: "default" | "bordered";
  layout: "vertical" | "collapsed" | "horizontal";
  content: "compact" | "wide";
};

export const DEFAULT_TENANT_CHROME: TenantChrome = {
  saved: false,
  primary_preset: null,
  primary: null,
  skin: "default",
  layout: "vertical",
  content: "compact",
};

const MANAGED_COLOR_KEYS = [
  "primary",
  "primary-foreground",
  "ring",
  "sidebar-primary",
  "sidebar-primary-foreground",
  "chart-1",
] as const;

/** Surfaces a module accent may tint. Status colors are not in this list. */
export const MODULE_SURFACE_KEYS = [
  "background",
  "card",
  "popover",
  "sidebar",
  "sidebar-accent",
  "sidebar-border",
  "border",
  "input",
  "muted",
  "secondary",
  "accent",
  "chart-1",
  "chart-2",
  "chart-3",
  "chart-4",
  "chart-5",
] as const;

const LIGHT_SURFACES = {
  background: "#f8fafc",
  card: "#ffffff",
  popover: "#ffffff",
  sidebar: "#ffffff",
  "sidebar-accent": "#f1f5f9",
  "sidebar-border": "#e2e8f0",
  border: "#e2e8f0",
  input: "#e2e8f0",
  muted: "#f1f5f9",
  secondary: "#f1f5f9",
  accent: "#f1f5f9",
} as const;

const DARK_SURFACES = {
  background: "#0f172a",
  card: "#111827",
  popover: "#111827",
  sidebar: "#0f172a",
  "sidebar-accent": "#1e293b",
  "sidebar-border": "#1e293b",
  border: "#1e293b",
  input: "#1e293b",
  muted: "#1f2937",
  secondary: "#1f2937",
  accent: "#1f2937",
} as const;

const LIGHT_TINT = {
  background: 0.08,
  card: 0.035,
  popover: 0.035,
  sidebar: 0.16,
  "sidebar-accent": 0.24,
  "sidebar-border": 0.2,
  border: 0.16,
  input: 0.12,
  muted: 0.12,
  secondary: 0.12,
  accent: 0.12,
} as const;

const DARK_TINT = {
  background: 0.14,
  card: 0.16,
  popover: 0.16,
  sidebar: 0.22,
  "sidebar-accent": 0.3,
  "sidebar-border": 0.28,
  border: 0.24,
  input: 0.2,
  muted: 0.18,
  secondary: 0.18,
  accent: 0.18,
} as const;

/** Same cutoff as TenantChromePalette: amber stays dark text, saturated presets stay light text. */
const LIGHT_TEXT_MAX_LUMINANCE = 0.4;

export function resolveTenantChrome(
  saved: TenantChrome | null | undefined,
  preview: TenantChrome | null,
): TenantChrome | null {
  if (preview) {
    return preview;
  }
  if (saved?.saved) {
    return saved;
  }
  return null;
}

export function isHex6(value: string | null | undefined): value is string {
  return typeof value === "string" && /^#[0-9a-fA-F]{6}$/.test(value);
}

export function cssColorToHex(value: string): string | null {
  const match = value.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
  if (!match) {
    return null;
  }
  return rgbToHex(Number(match[1]), Number(match[2]), Number(match[3]));
}

export function contrastForeground(hex: string): string {
  const rgb = hexToRgb(hex);
  if (!rgb) {
    return rgbToHex(250, 250, 250);
  }
  return relativeLuminance(rgb[0], rgb[1], rgb[2]) > LIGHT_TEXT_MAX_LUMINANCE
    ? rgbToHex(17, 24, 39)
    : rgbToHex(250, 250, 250);
}

/** UI-component contrast. Below this, a brand mark disappears on the dark auth panel. */
const AUTH_PANEL_MARK_MIN_CONTRAST = 3;

/**
 * The auth panel is a dark field. Saturated workspace colors (green, teal, violet,
 * rose, blue, amber, and a light custom color) stay on the rings and the label.
 * The default near-black primary, and any custom color too close to that field, does not.
 */
export function authPanelUsesBrandMark(primaryHex: string, panelHex: string): boolean {
  const primary = hexToRgb(primaryHex);
  const panel = hexToRgb(panelHex);
  if (!primary || !panel) {
    return false;
  }
  const primaryLuminance = relativeLuminance(primary[0], primary[1], primary[2]);
  const panelLuminance = relativeLuminance(panel[0], panel[1], panel[2]);
  const lighter = Math.max(primaryLuminance, panelLuminance);
  const darker = Math.min(primaryLuminance, panelLuminance);
  return (lighter + 0.05) / (darker + 0.05) >= AUTH_PANEL_MARK_MIN_CONTRAST;
}

export function productPrimaryHex(): string {
  return rgbToHex(23, 23, 23);
}

export function readSwatchHex(presetId: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }
  const probe = document.createElement("span");
  probe.style.color = `var(--chrome-swatch-${presetId})`;
  document.body.appendChild(probe);
  const computed = getComputedStyle(probe).color;
  probe.remove();
  return cssColorToHex(computed);
}

export function applyTenantChromeSurface(chrome: TenantChrome | null): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  if (!chrome) {
    delete root.dataset.skin;
    delete root.dataset.content;
    return;
  }
  root.dataset.skin = chrome.skin;
  root.dataset.content = chrome.content;
}

export function mixHex(base: string, accent: string, accentWeight: number): string {
  const from = hexToRgb(base);
  const toward = hexToRgb(accent);
  if (!from || !toward) {
    return base;
  }
  const weight = Math.max(0, Math.min(1, accentWeight));
  return rgbToHex(
    from[0] * (1 - weight) + toward[0] * weight,
    from[1] * (1 - weight) + toward[1] * weight,
    from[2] * (1 - weight) + toward[2] * weight,
  );
}

/** Tinted shell for one module. Success, warning, and error are left untouched. */
export function moduleSurfaceVariables(accentHex: string, mode: "light" | "dark"): Record<string, string> {
  const bases = mode === "dark" ? DARK_SURFACES : LIGHT_SURFACES;
  const weights = mode === "dark" ? DARK_TINT : LIGHT_TINT;
  const variables: Record<string, string> = {};
  for (const key of Object.keys(bases) as Array<keyof typeof bases>) {
    variables[key] = mixHex(bases[key], accentHex, weights[key]);
  }
  const lift = mode === "dark" ? "#0f172a" : "#ffffff";
  variables["chart-1"] = accentHex;
  variables["chart-2"] = mixHex(accentHex, lift, mode === "dark" ? 0.45 : 0.28);
  variables["chart-3"] = mixHex(accentHex, lift, mode === "dark" ? 0.25 : 0.5);
  variables["chart-4"] = mixHex(accentHex, lift, mode === "dark" ? 0.62 : 0.68);
  variables["chart-5"] = mixHex(accentHex, lift, mode === "dark" ? 0.78 : 0.84);
  return variables;
}

/** Admin-chosen surfaces. Empty values keep the shared plain theme. Dark mode keeps the shared dark shell. */
export function chosenModuleSurfaceVariables(
  surfaces: { sidebar?: string | null; background?: string | null; card?: string | null },
  mode: "light" | "dark",
): Record<string, string> {
  if (mode === "dark") {
    return {};
  }
  const variables: Record<string, string> = {};
  if (isHex6(surfaces.background)) {
    variables.background = surfaces.background;
  }
  if (isHex6(surfaces.card)) {
    variables.card = surfaces.card;
    variables.popover = surfaces.card;
  }
  if (isHex6(surfaces.sidebar)) {
    variables.sidebar = surfaces.sidebar;
    variables["sidebar-accent"] = mixHex(surfaces.sidebar, "#0f172a", 0.06);
    variables["sidebar-border"] = mixHex(surfaces.sidebar, "#94a3b8", 0.28);
  }
  return variables;
}

export function applyChosenModuleSurfaces(
  surfaces: { sidebar?: string | null; background?: string | null; card?: string | null },
  mode: "light" | "dark",
): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  for (const [key, value] of Object.entries(chosenModuleSurfaceVariables(surfaces, mode))) {
    root.style.setProperty(`--${key}`, value);
  }
}

export function applyModuleCharts(accentHex: string, mode: "light" | "dark"): void {
  if (typeof document === "undefined" || !isHex6(accentHex)) {
    return;
  }
  const root = document.documentElement;
  const variables = moduleSurfaceVariables(accentHex, mode);
  for (const key of ["chart-1", "chart-2", "chart-3", "chart-4", "chart-5"]) {
    const value = variables[key];
    if (value) {
      root.style.setProperty(`--${key}`, value);
    }
  }
}

export function clearModuleSurfaces(): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  for (const key of MODULE_SURFACE_KEYS) {
    root.style.removeProperty(`--${key}`);
  }
}

export function applyModuleSurfaces(accentHex: string, mode: "light" | "dark"): void {
  if (typeof document === "undefined" || !isHex6(accentHex)) {
    return;
  }
  const root = document.documentElement;
  const variables = moduleSurfaceVariables(accentHex, mode);
  for (const [key, value] of Object.entries(variables)) {
    root.style.setProperty(`--${key}`, value);
  }
}

export function applyPreviewPrimary(chrome: TenantChrome | null): void {
  if (typeof document === "undefined") {
    return;
  }
  const root = document.documentElement;
  if (!chrome?.primary_preset) {
    for (const key of MANAGED_COLOR_KEYS) {
      root.style.removeProperty(`--${key}`);
    }
    return;
  }

  const preset = chrome.primary_preset;
  const color = preset === "custom" ? chrome.primary : `var(--chrome-swatch-${preset})`;
  const foreground =
    preset === "custom" && isHex6(chrome.primary)
      ? contrastForeground(chrome.primary)
      : `var(--chrome-swatch-${preset}-fg)`;
  if (!color) {
    return;
  }

  for (const key of MANAGED_COLOR_KEYS) {
    const value = key.endsWith("foreground") ? foreground : color;
    if (value) {
      root.style.setProperty(`--${key}`, value);
    }
  }
}

function rgbToHex(red: number, green: number, blue: number): string {
  const channel = (value: number) =>
    Math.max(0, Math.min(255, Math.round(value)))
      .toString(16)
      .padStart(2, "0");
  return `#${channel(red)}${channel(green)}${channel(blue)}`;
}

function hexToRgb(hex: string): [number, number, number] | null {
  const match = hex.trim().match(/^#([0-9a-fA-F]{6})$/);
  if (!match) {
    return null;
  }
  const raw = match[1];
  return [parseInt(raw.slice(0, 2), 16), parseInt(raw.slice(2, 4), 16), parseInt(raw.slice(4, 6), 16)];
}

function relativeLuminance(red: number, green: number, blue: number): number {
  const linear = [red, green, blue].map((channel) => {
    const value = channel / 255;
    if (value <= 0.04045) {
      return value / 12.92;
    }
    return ((value + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
}
