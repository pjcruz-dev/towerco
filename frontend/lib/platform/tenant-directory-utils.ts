import type { PlatformTenantRow } from "@/lib/api/modules/platform-api";
import {
  PLATFORM_TENANT_MODULE_BADGE_ORDER,
  TENANT_MODULE_LABELS,
} from "@/lib/tenant/enabled-modules";

export function tenantHasDynamicEntities(row: PlatformTenantRow): boolean {
  return (row.effective_enabled_modules ?? []).includes("dynamic_entities");
}

export function tenantIsEApprovalOnly(row: PlatformTenantRow): boolean {
  const modules = row.effective_enabled_modules ?? [];
  return modules.includes("e_approval") && !modules.includes("dynamic_entities");
}

export function formatTenantModuleBadges(row: PlatformTenantRow): string[] {
  const modules = row.effective_enabled_modules ?? [];
  const badges = PLATFORM_TENANT_MODULE_BADGE_ORDER.filter((key) => modules.includes(key)).map(
    (key) => TENANT_MODULE_LABELS[key] ?? key.replace(/_/g, " "),
  );

  if (badges.length === 0) {
    return ["Core"];
  }

  return badges;
}

export function tenantUsesPlatformModuleDefault(row: PlatformTenantRow): boolean {
  return row.enabled_modules == null;
}

export function formatModulesLabel(row: PlatformTenantRow): string {
  if (tenantUsesPlatformModuleDefault(row)) {
    return `Default · ${formatTenantModuleBadges(row).join(", ")}`;
  }

  return formatTenantModuleBadges(row).join(" · ");
}

const MODULE_BADGE_CLASSES: Record<string, string> = {
  "E-Approval": "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
  Ticketing: "bg-violet-500/10 text-violet-700 dark:text-violet-300",
  "Dynamic Entities": "bg-sky-500/10 text-sky-700 dark:text-sky-300",
  "AI Assistant": "bg-fuchsia-500/10 text-fuchsia-800 dark:text-fuchsia-300",
  Core: "bg-muted text-muted-foreground",
};

export function moduleBadgeClass(module: string): string {
  return MODULE_BADGE_CLASSES[module] ?? "bg-muted text-muted-foreground";
}

export function escapeCsvField(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replaceAll('"', '""')}"`;
  }
  return value;
}

export function exportTenantsCsv(rows: PlatformTenantRow[]): void {
  const header = [
    "tenant_id",
    "slug",
    "primary_domain",
    "domains",
    "environment",
    "plan_tier",
    "subscription_status",
    "seat_limit",
    "mfa_required",
    "modules",
    "uses_platform_default",
    "created_at",
  ].join(",");

  const lines = rows.map((row) =>
    [
      escapeCsvField(row.id),
      escapeCsvField(row.slug ?? ""),
      escapeCsvField(row.domains[0] ?? ""),
      escapeCsvField(row.domains.join("; ")),
      escapeCsvField(row.environment ?? "production"),
      escapeCsvField(row.plan_tier ?? "starter"),
      escapeCsvField(row.subscription_status ?? "active"),
      escapeCsvField(String(row.seat_limit ?? "")),
      escapeCsvField(row.mfa_required ? "true" : "false"),
      escapeCsvField(formatModulesLabel(row)),
      escapeCsvField(tenantUsesPlatformModuleDefault(row) ? "true" : "false"),
      escapeCsvField(row.created_at ?? ""),
    ].join(","),
  );

  const blob = new Blob([[header, ...lines].join("\n")], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = `toweros-tenants-${new Date().toISOString().slice(0, 10)}.csv`;
  anchor.click();
  URL.revokeObjectURL(url);
}

export function moduleFilterLabel(value: string): string {
  switch (value) {
    case "e_approval_only":
      return "E-Approval only";
    case "dynamic_entities":
      return "Includes Dynamic Entities";
    case "ticketing":
      return "Includes Ticketing";
    default:
      return "All modules";
  }
}
