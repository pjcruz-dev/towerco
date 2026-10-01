import { describe, expect, it } from "vitest";

import type { AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import { roleDisplayLabel } from "@/lib/rbac/role-display-labels";
import { filterRolesForEnabledModules, HIDDEN_TENANT_ROLE_ALIASES } from "@/lib/rbac/role-groups";

function role(name: string): AdminRoleRow {
  return {
    id: 1,
    name,
    is_baseline: name === "tenant_admin",
    permissions: [],
    user_count: 0,
  };
}

describe("filterRolesForEnabledModules", () => {
  it("hides the administrator owner alias", () => {
    const visible = filterRolesForEnabledModules([
      role("tenant_admin"),
      role("administrator"),
      role("admin"),
    ]);

    expect(visible.map((row) => row.name)).toEqual(["tenant_admin", "admin"]);
    expect(HIDDEN_TENANT_ROLE_ALIASES.has("administrator")).toBe(true);
  });

  it("keeps the alias only when already assigned", () => {
    const visible = filterRolesForEnabledModules([role("administrator")], {
      alwaysIncludeRoleNames: ["administrator"],
    });

    expect(visible.map((row) => row.name)).toEqual(["administrator"]);
  });
});

describe("roleDisplayLabel", () => {
  it("labels ATC admin as Operations admin", () => {
    expect(roleDisplayLabel("admin")).toBe("Operations admin");
    expect(roleDisplayLabel("tenant_admin")).toBe("Tenant admin");
    expect(roleDisplayLabel("dynamic_entities_admin")).toBe("Dynamic Entities admin");
  });
});
