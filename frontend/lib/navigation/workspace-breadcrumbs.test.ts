import { describe, expect, it } from "vitest";

import { resolveWorkspaceBreadcrumbs } from "./workspace-breadcrumbs";

describe("resolveWorkspaceBreadcrumbs", () => {
  it("hides breadcrumbs on module dashboards", () => {
    expect(resolveWorkspaceBreadcrumbs("/e-approval")).toEqual([]);
    expect(resolveWorkspaceBreadcrumbs("/dashboard")).toEqual([]);
  });

  it("maps dynamic-entities routes to module-aligned crumbs", () => {
    expect(resolveWorkspaceBreadcrumbs("/dynamic-entities/fields")).toEqual([
      { label: "Dynamic Entities", href: "/dynamic-entities" },
      { label: "Manage Fields" },
    ]);
  });

  it("maps nested e-approval routes", () => {
    expect(resolveWorkspaceBreadcrumbs("/e-approval/submissions/new")).toEqual([
      { label: "E-Approval", href: "/e-approval" },
      { label: "Submissions", href: "/e-approval/submissions" },
      { label: "New request" },
    ]);
  });

  it("maps e-approval routes", () => {
    expect(resolveWorkspaceBreadcrumbs("/e-approval/approvals")).toEqual([
      { label: "E-Approval", href: "/e-approval" },
      { label: "Approvals" },
    ]);
  });

  it("maps team and access routes", () => {
    expect(resolveWorkspaceBreadcrumbs("/users")).toEqual([
      { label: "Team & Access", href: "/users" },
      { label: "Users" },
    ]);
    expect(resolveWorkspaceBreadcrumbs("/users/roles")).toEqual([
      { label: "Team & Access", href: "/users" },
      { label: "Roles & permissions" },
    ]);
    expect(resolveWorkspaceBreadcrumbs("/users/org")).toEqual([
      { label: "Team & Access", href: "/users" },
      { label: "Organization" },
    ]);
  });
});
