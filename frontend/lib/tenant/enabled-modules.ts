import type { AuthUser } from "@/types/auth";

export const TENANT_MODULE_LABELS: Record<string, string> = {
  core: "Dashboard",
  team_access: "Team & Access",
  e_approval: "E-Forms",
  dynamic_entities: "Dynamic Entities",
  ticketing: "Ticketing",
  billings: "Billings",
  ai_assistant: "AI Assistant",
  doc_extract: "DocExtract",
};

export const WORKSPACE_AUDIT_MODULE_FILTERS = [
  { value: "e_approval", label: TENANT_MODULE_LABELS.e_approval },
  { value: "team_access", label: TENANT_MODULE_LABELS.team_access },
  { value: "ticketing", label: TENANT_MODULE_LABELS.ticketing },
  { value: "dynamic_entities", label: TENANT_MODULE_LABELS.dynamic_entities ?? "Dynamic Entities" },
  { value: "doc_extract", label: TENANT_MODULE_LABELS.doc_extract },
  { value: "ai_assistant", label: TENANT_MODULE_LABELS.ai_assistant },
  { value: "core", label: TENANT_MODULE_LABELS.core },
] as const;

export function workspaceAuditModuleLabel(module: string): string {
  return TENANT_MODULE_LABELS[module] ?? module.replace(/_/g, " ");
}

export const TENANT_MODULE_DESCRIPTIONS: Record<string, string> = {
  dynamic_entities:
    "Dynamic entity packs (PM, Procurement, Finance, Ticketing) with Manage Fields.",
  billings: "Tenant subscription, usage, and self-serve plan billing (/billing).",
  ai_assistant:
    "In-app help assistant for workflows, permissions, and how-to guidance.",
  doc_extract: "Upload finance PDFs, OCR scan, map fields, review, and export CSV/XLSX.",
};

/** Optional modules superadmins can enable per tenant (must stay aligned with backend TOGGLEABLE_MODULES). */
export const TOGGLEABLE_WORKSPACE_MODULES = [
  "dynamic_entities",
  "e_approval",
  "ticketing",
  "billings",
  "ai_assistant",
  "doc_extract",
] as const;

type WorkspaceModulesCatalog = {
  toggleable_modules?: string[];
  platform_modules?: string[];
};

/** Merge API catalog with the frontend module list so new modules appear once deployed. */
export function resolveToggleableWorkspaceModules(
  catalog: WorkspaceModulesCatalog | undefined,
): string[] {
  if (!catalog) {
    return [...TOGGLEABLE_WORKSPACE_MODULES];
  }

  const platform = new Set(catalog.platform_modules ?? []);
  const merged = new Set(catalog.toggleable_modules ?? []);

  for (const moduleKey of TOGGLEABLE_WORKSPACE_MODULES) {
    if (platform.has(moduleKey)) {
      merged.add(moduleKey);
    }
  }

  return TOGGLEABLE_WORKSPACE_MODULES.filter((moduleKey) => merged.has(moduleKey));
}

/** Toggleable workspace modules shown as badges on the platform tenant directory. */
export const PLATFORM_TENANT_MODULE_BADGE_ORDER = [
  "e_approval",
  "dynamic_entities",
  "ticketing",
  "billings",
  "doc_extract",
  "ai_assistant",
] as const;

export function resolveEnabledModulesForUser(
  user: AuthUser | null | undefined,
  activeTenantId: string | null,
): string[] {
  if (!user) {
    return [];
  }

  const access = user.tenantAccesses.find((item) => item.tenantId === activeTenantId);
  if (access?.enabledModules && access.enabledModules.length > 0) {
    return access.enabledModules;
  }

  if (user.enabledModules && user.enabledModules.length > 0) {
    return user.enabledModules;
  }

  return [];
}

export function isTenantModuleEnabled(
  enabledModules: string[],
  module: string | undefined,
): boolean {
  if (!module) {
    return true;
  }

  return enabledModules.includes(module);
}

export function notificationsModuleEnabled(enabledModules: string[]): boolean {
  return (
    enabledModules.includes("e_approval")
    || enabledModules.includes("ticketing")
    || enabledModules.includes("dynamic_entities")
  );
}
