import type { AuthUser } from "@/types/auth";

export const TENANT_MODULE_LABELS: Record<string, string> = {
  core: "Dashboard",
  team_access: "Team & Access",
  e_approval: "E-Forms",
  ticketing: "Ticketing",
  document_register: "Document register",
  doc_extract: "DocExtract",
  dynamic_entities: "Dynamic Entities",
  ai_assistant: "AI Assistant",
};

export const TENANT_MODULE_DESCRIPTIONS: Record<string, string> = {
  e_approval: "Forms, submissions, and approval workflows.",
  ticketing: "Tickets, assignments, and SLA follow-up.",
  document_register:
    "ISO master list of approved documents; start requests and revisions via E-Forms.",
  doc_extract: "Upload finance PDFs, OCR scan, map fields, review, and export CSV/XLSX.",
  dynamic_entities: "Dynamic entity packs, records, fields, reports, and workflows.",
  ai_assistant: "In-app assistant for workflows, permissions, and how-to guidance.",
};

/** Optional modules superadmins can enable per tenant (must stay aligned with backend TOGGLEABLE_MODULES). */
export const TOGGLEABLE_WORKSPACE_MODULES = [
  "e_approval",
  "ticketing",
  "document_register",
  "doc_extract",
  "dynamic_entities",
  "ai_assistant",
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
  "ticketing",
  "document_register",
  "doc_extract",
  "dynamic_entities",
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
  return enabledModules.includes("e_approval") || enabledModules.includes("ticketing");
}
