import type { RolloutAssignableUser } from "@/lib/api/modules/rollout-api";

/** Tenant roles likely to act as delegate for a gate approval role scope. */
const DELEGATE_ROLES_BY_SCOPE: Record<string, string[]> = {
  saq: ["saq_approver", "manager", "tenant_admin"],
  saq_engineering: ["saq_approver", "manager", "tenant_admin"],
  pmo: ["pmo_approver", "manager", "tenant_admin"],
  cme: ["cme_approver", "manager", "tenant_admin"],
  cme_power: ["cme_approver", "manager", "tenant_admin"],
  engineering: ["pmo_approver", "manager", "tenant_admin"],
  mno: ["manager", "tenant_admin"],
  tenant_admin: ["tenant_admin", "manager"],
};

export function filterAssignableUsersForDelegationRole(
  users: RolloutAssignableUser[],
  roleKey: string,
  excludeUserId?: string | null,
): RolloutAssignableUser[] {
  const scope = roleKey.trim().toLowerCase();
  const allowedRoles = scope ? DELEGATE_ROLES_BY_SCOPE[scope] : null;

  return users.filter((user) => {
    if (excludeUserId && user.id === excludeUserId) {
      return false;
    }

    if (!allowedRoles) {
      return true;
    }

    return user.roles.some((role) => allowedRoles.includes(role));
  });
}
