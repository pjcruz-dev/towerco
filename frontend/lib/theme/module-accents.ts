import { MODULE_SHELLS } from "@/lib/navigation/module-shell";
import type { TenantChromePresetId } from "@/lib/theme/tenant-chrome";

/** Accent only. Neutrals and status colors stay the same in every module. */
export const MODULE_ACCENT_DEFAULTS: Record<string, TenantChromePresetId> = {
  e_approval: "violet",
  ticketing: "amber",
  document_register: "teal",
  doc_extract: "blue",
  asset_management: "rose",
  dynamic_entities: "violet",
};

export type ModuleAccent = {
  primary_preset: TenantChromePresetId | "custom";
  primary: string | null;
};

export type ModuleAccentMap = Record<string, ModuleAccent>;

export const MODULE_ACCENT_TARGETS = MODULE_SHELLS.filter((shell) => shell.kind === "module");

export function moduleAccentFor(
  saved: ModuleAccentMap | null | undefined,
  moduleId: string,
): ModuleAccent | null {
  const chosen = saved?.[moduleId];
  if (chosen?.primary_preset) {
    return chosen;
  }
  const fallback = MODULE_ACCENT_DEFAULTS[moduleId];
  if (!fallback) {
    return null;
  }
  return { primary_preset: fallback, primary: null };
}
