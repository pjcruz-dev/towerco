import type { PlatformUser } from "@/stores/platform-auth-store";

export const PLATFORM_PERMS = {
  consoleView: "platform.console.view",
  tenantsView: "platform.tenants.view",
  tenantsManage: "platform.tenants.manage",
  tenantsDelete: "platform.tenants.delete",
  tenantsImpersonate: "platform.tenants.impersonate",
  billingView: "platform.billing.view",
  billingManage: "platform.billing.manage",
  playbooksView: "platform.playbooks.view",
  playbooksManage: "platform.playbooks.manage",
  operatorsView: "platform.operators.view",
  operatorsManage: "platform.operators.manage",
  auditView: "platform.audit.view",
} as const;

export function platformHasPermission(
  user: PlatformUser | null | undefined,
  permission: string,
): boolean {
  if (!user?.is_platform_admin) {
    return false;
  }

  if (user.platform_role === "superadmin") {
    return true;
  }

  return (user.platform_permissions ?? []).includes(permission);
}

export function platformRoleLabel(role: string | undefined): string {
  switch (role) {
    case "superadmin":
      return "Superadmin";
    case "billing":
      return "Billing operator";
    case "support":
      return "Support operator";
    case "viewer":
      return "Viewer";
    default:
      return "Operator";
  }
}
