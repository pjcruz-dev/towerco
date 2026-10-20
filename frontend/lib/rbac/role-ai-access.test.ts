import { describe, expect, it } from "vitest";

import { roleAllowsPermissionEdit, toggleAiCapability } from "@/lib/rbac/role-ai-access";

describe("role AI access", () => {
  it("keeps the workspace owner locked and leaves every other role editable", () => {
    expect(roleAllowsPermissionEdit("tenant_admin")).toBe(false);
    expect(roleAllowsPermissionEdit("administrator")).toBe(false);
    expect(roleAllowsPermissionEdit("ticketing_contributor")).toBe(true);
    expect(roleAllowsPermissionEdit("viewer")).toBe(true);
  });

  it("turns one capability off without clearing the rest of an open preset", () => {
    const next = toggleAiCapability({ preset: "unrestricted" }, "delete_records", false);

    expect(next.delete_records).toBe(false);
    expect(next.ask_lookup).toBe(true);
    expect(next.create_update).toBe(true);
    expect(next.read_code).toBe(true);
  });

  it("turns one capability on from a closed preset", () => {
    const next = toggleAiCapability({ preset: "none" }, "ask_lookup", true);

    expect(next.ask_lookup).toBe(true);
    expect(next.create_update).toBe(false);
    expect(next.delete_records).toBe(false);
  });
});
