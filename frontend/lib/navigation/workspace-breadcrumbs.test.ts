import { describe, expect, it } from "vitest";

import { resolveWorkspaceBreadcrumbs } from "./workspace-breadcrumbs";

describe("resolveWorkspaceBreadcrumbs", () => {
  it("hides breadcrumbs on module dashboards", () => {
    expect(resolveWorkspaceBreadcrumbs("/project-one")).toEqual([]);
    expect(resolveWorkspaceBreadcrumbs("/dashboard")).toEqual([]);
  });

  it("maps project-one approvals to module-aligned crumbs", () => {
    expect(resolveWorkspaceBreadcrumbs("/project-one/approvals")).toEqual([
      { label: "Project-One", href: "/project-one" },
      { label: "Approvals" },
    ]);
  });

  it("maps nested project-one routes", () => {
    expect(resolveWorkspaceBreadcrumbs("/project-one/approvals/new")).toEqual([
      { label: "Project-One", href: "/project-one" },
      { label: "Approvals", href: "/project-one/approvals" },
      { label: "New approval" },
    ]);
  });

  it("maps e-approval routes", () => {
    expect(resolveWorkspaceBreadcrumbs("/e-approval/approvals")).toEqual([
      { label: "E-Forms", href: "/e-approval" },
      { label: "Approvals" },
    ]);
  });

  it("maps doc-extract batch detail without linking to /doc-extract/batches", () => {
    expect(
      resolveWorkspaceBreadcrumbs("/doc-extract/batches/01a084e1-1d12-72a1-b080-f7b4849fed82"),
    ).toEqual([
      { label: "DocExtract", href: "/doc-extract" },
      { label: "Batches", href: "/doc-extract" },
      { label: "Detail" },
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
