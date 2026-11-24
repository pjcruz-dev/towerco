import { describe, expect, it } from "vitest";

import type { AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import { roleDisplayLabel } from "@/lib/rbac/role-display-labels";
import {
  filterRolesForEnabledModules,
  groupRolesForMenu,
  HIDDEN_TENANT_ROLE_ALIASES,
  roleLabelWithinGroup,
} from "@/lib/rbac/role-groups";

function role(name: string, id = 1): AdminRoleRow {
  return {
    id,
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

describe("groupRolesForMenu", () => {
  it("groups module roles and keeps workspace roles", () => {
    const groups = groupRolesForMenu([
      role("tenant_admin", 1),
      role("admin", 2),
      role("billing", 3),
      role("finance_officer", 4),
      role("assets_viewer", 5),
      role("assets_admin", 6),
      role("dcf_author", 7),
      role("e_approval_requestor", 8),
      role("ticketing_operator", 9),
      role("doc_extract_viewer", 10),
      role("ai_assistant_user", 11),
      role("enviroment_switcher", 12),
    ]);

    expect(groups.map((group) => group.label)).toEqual([
      "Workspace",
      "Ticketing",
      "Assets",
      "Document register",
      "E-Forms",
      "AI Assistant",
      "DocExtract",
      "Custom roles",
    ]);
    expect(groups.find((group) => group.id === "workspace")?.roles.map((row) => row.name)).toEqual([
      "billing",
      "finance_officer",
      "admin",
      "tenant_admin",
    ]);
    expect(groups.find((group) => group.id === "asset_management")?.roles.map((row) => row.name)).toEqual([
      "assets_viewer",
      "assets_admin",
    ]);
    expect(groups.find((group) => group.id === "custom")?.roles.map((row) => row.name)).toEqual([
      "enviroment_switcher",
    ]);
    expect(groups.flatMap((group) => group.roles)).toHaveLength(12);
  });

  it("lists only the module that opened the menu", () => {
    const groups = groupRolesForMenu(
      [
        role("tenant_admin", 1),
        role("billing", 2),
        role("ticketing_viewer", 3),
        role("ticketing_admin", 4),
        role("dcf_approver", 5),
        role("e_approval_requestor", 6),
      ],
      { moduleId: "ticketing" },
    );

    expect(groups.map((group) => group.id)).toEqual(["ticketing"]);
    expect(groups[0]?.roles.map((row) => row.name)).toEqual(["ticketing_viewer", "ticketing_admin"]);
  });

  it("shortens the role name under its module heading", () => {
    expect(roleLabelWithinGroup("e_approval", "e_approval_requestor")).toBe("Requestor");
    expect(roleLabelWithinGroup("document_register", "dcf_controller")).toBe("Controller");
    expect(roleLabelWithinGroup("workspace", "admin")).toBe("Operations admin");
  });
});

describe("roleDisplayLabel", () => {
  it("labels ATC admin as Operations admin", () => {
    expect(roleDisplayLabel("admin")).toBe("Operations admin");
    expect(roleDisplayLabel("tenant_admin")).toBe("Tenant admin");
    expect(roleDisplayLabel("dynamic_entities_admin")).toBe("Dynamic Entities admin");
  });
});
