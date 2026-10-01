import { describe, expect, it } from "vitest";

import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";

describe("normalizeAuthSession", () => {
  it("drops entity ACL for tenant_admin so a job-role matrix cannot lock the owner out", () => {
    const session = normalizeAuthSession({
      user: {
        id: "u1",
        name: "Owner",
        email: "owner@example.com",
        roles: ["tenant_admin", "admin"],
        permissions: ["dynamic_entities:view"],
        access_matrix: {
          entities: {
            vendors: { view: true, create: false, edit: false, delete: false, export: false },
          },
        },
      },
    });

    expect(session.user?.roles).toEqual(["tenant_admin", "admin"]);
    expect(session.user?.accessMatrix).toBeUndefined();
  });

  it("keeps entity ACL for non-owner roles", () => {
    const session = normalizeAuthSession({
      user: {
        id: "u2",
        name: "Ops",
        email: "ops@example.com",
        roles: ["admin"],
        permissions: ["dynamic_entities:view"],
        access_matrix: {
          entities: {
            vendors: { view: true, create: false, edit: false, delete: false, export: false },
          },
        },
      },
    });

    expect(session.user?.accessMatrix?.entities?.vendors?.view).toBe(true);
  });
});
