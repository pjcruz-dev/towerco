import { MODULE_SHELLS } from "@/lib/navigation/module-shell";
import type { TenantChromePresetId } from "@/lib/theme/tenant-chrome";

export type ModuleAccent = {
  primary_preset: TenantChromePresetId | "custom" | "plain" | null;
  primary: string | null;
  sidebar?: string | null;
  background?: string | null;
  card?: string | null;
};

export type ModuleAccentMap = Record<string, ModuleAccent>;

export const MODULE_ACCENT_TARGETS = MODULE_SHELLS.filter((shell) => shell.kind === "module");

function hexOrNull(value: string | null | undefined): string | null {
  return typeof value === "string" && /^#[0-9a-fA-F]{6}$/.test(value) ? value : null;
}

/** Saved module colors only. An unset module stays the plain shared theme. */
export function moduleStyleFor(
  saved: ModuleAccentMap | null | undefined,
  moduleId: string,
): ModuleAccent | null {
  const chosen = saved?.[moduleId];
  if (!chosen) {
    return null;
  }
  const preset =
    chosen.primary_preset && chosen.primary_preset !== "plain" ? chosen.primary_preset : null;
  const style: ModuleAccent = {
    primary_preset: preset,
    primary: preset === "custom" ? hexOrNull(chosen.primary) : null,
    sidebar: hexOrNull(chosen.sidebar),
    background: hexOrNull(chosen.background),
    card: hexOrNull(chosen.card),
  };
  if (!style.primary_preset && !style.sidebar && !style.background && !style.card) {
    return null;
  }
  return style;
}
