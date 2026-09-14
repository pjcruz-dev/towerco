import { describe, expect, it } from "vitest";

import { resolveAdminUserDepartmentDisplay, type AdminUserRow } from "@/lib/api/modules/admin-users-api";

function stub(overrides: Partial<AdminUserRow> = {}): AdminUserRow {
  return {
    id: "1",
    name: "Test",
    email: "test@example.com",
    is_active: true,
    deactivated_at: null,
    roles: [],
    permissions: [],
    created_at: null,
    updated_at: null,
    last_active_at: null,
    auth_methods: [],
    mfa_enrolled: false,
    mfa_required: false,
    ...overrides,
  };
}

describe("resolveAdminUserDepartmentDisplay", () => {
  it("uses department_display from API when present", () => {
    expect(
      resolveAdminUserDepartmentDisplay(
        stub({
          department: null,
          department_display: "Engineering & Design",
          department_inherited: true,
        }),
      ),
    ).toEqual({ label: "Engineering & Design", inherited: true });
  });

  it("falls back to manager department for older payloads", () => {
    expect(
      resolveAdminUserDepartmentDisplay(
        stub({
          department: null,
          manager: {
            id: "m1",
            name: "Manager",
            email: "m@example.com",
            department: "Finance and Accounting",
          },
        }),
      ),
    ).toEqual({ label: "Finance and Accounting", inherited: true });
  });

  it("prefers own department over manager", () => {
    expect(
      resolveAdminUserDepartmentDisplay(
        stub({
          department: "Executive Office",
          manager: {
            id: "m1",
            name: "Manager",
            email: "m@example.com",
            department: "Finance and Accounting",
          },
        }),
      ),
    ).toEqual({ label: "Executive Office", inherited: false });
  });
});
