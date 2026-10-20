import {
  DEFAULT_TENANT_CHROME,
  TENANT_CHROME_PRESETS,
  type TenantChrome,
  type TenantChromePresetId,
} from "@/lib/theme/tenant-chrome";

export const WORKSPACE_APPEARANCE_KEY = "workspace-appearance.self";

export type WorkspaceColorMode = "light" | "dark" | "system";

export type WorkspaceAppearance = TenantChrome & {
  colorMode: WorkspaceColorMode;
};

const PRESET_IDS = new Set<string>(TENANT_CHROME_PRESETS.map((preset) => preset.id));

export function parseWorkspaceAppearance(
  value: Record<string, unknown> | null | undefined,
): WorkspaceAppearance | null {
  if (!value) {
    return null;
  }

  const preset = value.primary_preset;
  const primaryPreset =
    preset === null || preset === undefined
      ? null
      : typeof preset === "string" && (preset === "custom" || PRESET_IDS.has(preset))
        ? (preset as TenantChromePresetId | "custom")
        : null;

  const skin = value.skin === "bordered" ? "bordered" : "default";
  const layout =
    value.layout === "collapsed" || value.layout === "horizontal" ? value.layout : "vertical";
  const content = value.content === "wide" ? "wide" : "compact";
  const colorMode = isColorMode(value.color_mode) ? value.color_mode : "system";
  const primary = typeof value.primary === "string" ? value.primary : null;

  return {
    ...DEFAULT_TENANT_CHROME,
    saved: true,
    primary_preset: primaryPreset,
    primary: primaryPreset === "custom" ? primary : null,
    skin,
    layout,
    content,
    colorMode,
  };
}

export function serializeWorkspaceAppearance(
  appearance: WorkspaceAppearance,
): Record<string, unknown> {
  return {
    primary_preset: appearance.primary_preset,
    primary: appearance.primary_preset === "custom" ? appearance.primary : null,
    skin: appearance.skin,
    layout: appearance.layout,
    content: appearance.content,
    color_mode: appearance.colorMode,
  };
}

function isColorMode(value: unknown): value is WorkspaceColorMode {
  return value === "light" || value === "dark" || value === "system";
}
