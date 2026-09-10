/**
 * Local + server named table layouts (visibility + order) per list page.
 * Storage key convention: `{columnVisibilityStorageKey}.layouts`
 * Server preference key: `module-list.{storageKey}`
 * Tenant-shared layouts: PUT …/shared
 */

import {
  fetchUserUiPreferenceBundle,
  putSharedUserUiPreference,
  putUserUiPreference,
} from "@/lib/api/modules/user-ui-preferences-api";

export type ModuleListLayout = {
  id: string;
  name: string;
  visibility: Record<string, boolean>;
  order: string[];
  updatedAt: string;
};

export type ModuleListLayoutsStore = {
  layouts: ModuleListLayout[];
};

export function layoutsStorageKey(columnVisibilityStorageKey: string): string {
  return `${columnVisibilityStorageKey}.layouts`;
}

export function layoutsPreferenceKey(storageKey: string): string {
  return `module-list.${storageKey}`;
}

function isLayout(value: unknown): value is ModuleListLayout {
  if (!value || typeof value !== "object") return false;
  const layout = value as ModuleListLayout;
  return (
    typeof layout.id === "string" &&
    typeof layout.name === "string" &&
    layout.visibility !== null &&
    typeof layout.visibility === "object" &&
    Array.isArray(layout.order)
  );
}

export function parseModuleListLayoutsValue(value: unknown): ModuleListLayout[] {
  if (!value || typeof value !== "object") return [];
  const layouts = (value as ModuleListLayoutsStore).layouts;
  if (!Array.isArray(layouts)) return [];
  return layouts.filter(isLayout).slice(0, 12);
}

export function readModuleListLayouts(storageKey: string): ModuleListLayout[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(storageKey);
    if (!raw) return [];
    return parseModuleListLayoutsValue(JSON.parse(raw) as ModuleListLayoutsStore);
  } catch {
    return [];
  }
}

export function writeModuleListLayouts(storageKey: string, layouts: ModuleListLayout[]): void {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(storageKey, JSON.stringify({ layouts } satisfies ModuleListLayoutsStore));
}

export function createModuleListLayoutId(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID();
  }
  return `layout-${Date.now()}`;
}

export type ModuleListLayoutsLoadResult = {
  personal: ModuleListLayout[];
  shared: ModuleListLayout[];
};

/**
 * Load personal layouts (cached locally) plus tenant-shared layouts.
 */
export async function loadModuleListLayoutsBundle(
  storageKey: string,
): Promise<ModuleListLayoutsLoadResult> {
  const local = readModuleListLayouts(storageKey);
  try {
    const bundle = await fetchUserUiPreferenceBundle(layoutsPreferenceKey(storageKey));
    const personal =
      bundle.value != null ? parseModuleListLayoutsValue(bundle.value) : local;
    if (bundle.value != null) {
      writeModuleListLayouts(storageKey, personal);
    }
    const shared = parseModuleListLayoutsValue({
      layouts: Array.isArray(bundle.shared?.layouts) ? bundle.shared.layouts : [],
    });
    return { personal, shared };
  } catch {
    return { personal: local, shared: [] };
  }
}

/** @deprecated Prefer loadModuleListLayoutsBundle */
export async function loadModuleListLayouts(storageKey: string): Promise<ModuleListLayout[]> {
  const bundle = await loadModuleListLayoutsBundle(storageKey);
  return bundle.personal;
}

export async function persistModuleListLayouts(
  storageKey: string,
  layouts: ModuleListLayout[],
): Promise<void> {
  writeModuleListLayouts(storageKey, layouts);
  try {
    await putUserUiPreference(layoutsPreferenceKey(storageKey), { layouts });
  } catch {
    // Offline / unauthorized: localStorage remains the source of truth for this browser.
  }
}

export async function persistSharedModuleListLayouts(
  storageKey: string,
  layouts: ModuleListLayout[],
): Promise<ModuleListLayout[]> {
  try {
    const shared = await putSharedUserUiPreference(layoutsPreferenceKey(storageKey), { layouts });
    return parseModuleListLayoutsValue({
      layouts: Array.isArray(shared?.layouts) ? shared.layouts : layouts,
    });
  } catch {
    return layouts;
  }
}
