/**
 * Server-backed dashboard page board layouts (Customize prefs).
 * Storage key (local): `toweros.*.layout`
 * Preference key: `dashboard-layout.{storageKey}`
 * Personal override wins; shared `layout` is the tenant default.
 */

import {
  deleteUserUiPreference,
  fetchUserUiPreferenceBundle,
  putSharedUserUiPreference,
  putUserUiPreference,
} from "@/lib/api/modules/user-ui-preferences-api";
import {
  EMPTY_DASHBOARD_LAYOUT_PREFS,
  isDashboardLayoutPrefs,
  normalizeDashboardLayoutPrefs,
  type DashboardLayoutPrefs,
} from "@/lib/ui/dashboard-widget-registry";

export function dashboardLayoutPreferenceKey(storageKey: string): string {
  return `dashboard-layout.${storageKey}`;
}

export type DashboardLayoutBundle = {
  personal: DashboardLayoutPrefs | null;
  tenantDefault: DashboardLayoutPrefs | null;
  sharedUpdatedAt: string | null;
};

function asPrefs(value: unknown): DashboardLayoutPrefs | null {
  if (!value || typeof value !== "object") return null;
  if (!isDashboardLayoutPrefs(value)) {
    // Accept loosely shaped server payloads via normalize when core arrays present.
    const row = value as Record<string, unknown>;
    if (
      Array.isArray(row.enabledWidgetIds) ||
      Array.isArray(row.widgetOrder) ||
      Array.isArray(row.hiddenWidgetIds)
    ) {
      return normalizeDashboardLayoutPrefs(row as DashboardLayoutPrefs);
    }
    return null;
  }
  return normalizeDashboardLayoutPrefs(value);
}

export async function loadDashboardLayoutBundle(storageKey: string): Promise<DashboardLayoutBundle> {
  try {
    const bundle = await fetchUserUiPreferenceBundle(dashboardLayoutPreferenceKey(storageKey));
    const personal = asPrefs(bundle.value);
    const tenantDefault = asPrefs(bundle.shared?.layout ?? null);
    return {
      personal,
      tenantDefault,
      sharedUpdatedAt:
        typeof bundle.shared?.updated_at === "string" ? bundle.shared.updated_at : null,
    };
  } catch {
    return { personal: null, tenantDefault: null, sharedUpdatedAt: null };
  }
}

export async function persistPersonalDashboardLayout(
  storageKey: string,
  layout: DashboardLayoutPrefs,
): Promise<void> {
  const normalized = normalizeDashboardLayoutPrefs(layout);
  try {
    await putUserUiPreference(
      dashboardLayoutPreferenceKey(storageKey),
      normalized as unknown as Record<string, unknown>,
    );
  } catch {
    // Offline / unauthorized: localStorage remains the browser cache.
  }
}

export async function clearPersonalDashboardLayout(storageKey: string): Promise<void> {
  try {
    await deleteUserUiPreference(dashboardLayoutPreferenceKey(storageKey));
  } catch {
    // Ignore — local reset still applies.
  }
}

export async function publishTenantDashboardLayout(
  storageKey: string,
  layout: DashboardLayoutPrefs,
): Promise<DashboardLayoutPrefs | null> {
  const normalized = normalizeDashboardLayoutPrefs(layout);
  try {
    const shared = await putSharedUserUiPreference(dashboardLayoutPreferenceKey(storageKey), {
      layout: normalized as unknown as Record<string, unknown>,
    });
    return asPrefs(shared?.layout ?? null);
  } catch {
    return null;
  }
}

export function resolveEffectiveDashboardLayout(input: {
  personal: DashboardLayoutPrefs | null;
  tenantDefault: DashboardLayoutPrefs | null;
  local: DashboardLayoutPrefs;
}): { layout: DashboardLayoutPrefs; source: "personal" | "tenant" | "local" } {
  if (input.personal) {
    return { layout: input.personal, source: "personal" };
  }
  if (input.tenantDefault) {
    return { layout: input.tenantDefault, source: "tenant" };
  }
  const local = normalizeDashboardLayoutPrefs(input.local);
  const empty =
    local.enabledWidgetIds.length === 0 &&
    local.widgetOrder.length === 0 &&
    local.hiddenWidgetIds.length === 0 &&
    Object.keys(local.spans).length === 0 &&
    Object.keys(local.widgetOptions).length === 0 &&
    Object.keys(local.pageChrome ?? {}).length === 0;
  if (empty) {
    return { layout: { ...EMPTY_DASHBOARD_LAYOUT_PREFS }, source: "local" };
  }
  return { layout: local, source: "local" };
}
