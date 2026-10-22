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
