import { LayoutDashboard, LifeBuoy } from "lucide-react";
import { describe, expect, it } from "vitest";

import {
  applyModuleShellToGroups,
  commandItemMatchesShell,
  compressShellFooter,
  moduleShellLinksFromGroups,
  resolveModuleShell,
  shellFooterItems,
} from "@/lib/navigation/module-shell";
import { workspaceNavGroups, type WorkspaceTopNavItem } from "@/lib/navigation/workspace-nav-config";

describe("resolveModuleShell", () => {
  it("maps each product path to one shell", () => {
    expect(resolveModuleShell("/e-approval/approvals").id).toBe("e_approval");
    expect(resolveModuleShell("/ticketing/tickets/new").id).toBe("ticketing");
    expect(resolveModuleShell("/documents/controlled").id).toBe("document_register");
    expect(resolveModuleShell("/doc-extract/batches").id).toBe("doc_extract");
    expect(resolveModuleShell("/help/e-approval/finance").id).toBe("e_approval");
    expect(resolveModuleShell("/users/roles").id).toBe("administration");
    expect(resolveModuleShell("/admin/system").id).toBe("administration");
    expect(resolveModuleShell("/dashboard").kind).toBe("hub");
    expect(resolveModuleShell("/account/profile").kind).toBe("hub");
  });
});

describe("applyModuleShellToGroups", () => {
  it("keeps only the module that owns the path", () => {
    const scoped = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/e-approval"));
    const titles = scoped.flatMap((group) => group.items.map((item) => item.title));
    expect(scoped).toHaveLength(1);
    expect(scoped[0]?.group).toBe("E-Forms");
    expect(titles).toContain("E-Forms");
    expect(titles).not.toContain("Ticketing");
    expect(titles).not.toContain("DocExtract");
    expect(titles).not.toContain("Settings");
  });

  it("keeps the full document name as the sidebar section", () => {
    const scoped = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/documents/controlled"));
    expect(scoped[0]?.group).toBe("Electronic Document Control System");
    expect(scoped[0]?.items.map((item) => item.title)).toContain("EDCS");
  });

  it("keeps administration pages together", () => {
    const scoped = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/users"));
    const titles = scoped.flatMap((group) => group.items.map((item) => item.title));
    expect(titles).toContain("Team & Access");
    expect(titles).toContain("Settings");
    expect(titles).not.toContain("E-Forms");
  });

  it("turns the hub into one app per module", () => {
    const scoped = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/dashboard"));
    const titles = scoped.flatMap((group) => group.items.map((item) => item.title));
    expect(scoped[0]?.group).toBe("Apps");
    expect(titles).toEqual(
      expect.arrayContaining(["Dashboard", "E-Forms", "Ticketing", "EDCS", "DocExtract"]),
    );
    expect(titles).not.toContain("Administration");
    expect(titles).not.toContain("Help");
    expect(titles).not.toContain("Finance");
    expect(titles).not.toContain("Sites");
  });

  it("does not turn personal exports into the Administration app", () => {
    const icon = LayoutDashboard;
    const settings: WorkspaceTopNavItem = {
      title: "Settings",
      icon,
      permissions: [],
      module: "core",
      items: [
        { title: "My E-Forms profile", href: "/e-approval/profile", permissions: [], module: "e_approval" },
        { title: "My exports", href: "/exports", permissions: [] },
      ],
    };
    const links = moduleShellLinksFromGroups([{ group: "Administration", items: [settings] }]);
    expect(links.map((link) => link.id)).not.toContain("administration");
    expect(resolveModuleShell("/exports").kind).toBe("hub");
  });

  it("pins non-module items that are not already on screen", () => {
    const hub = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/dashboard"));
    const footer = shellFooterItems(workspaceNavGroups, hub);
    const titles = footer.map((item) => item.title);
    expect(titles).toEqual(expect.arrayContaining(["Settings", "Help", "Team & Access"]));
    expect(titles).not.toContain("E-Forms");
    expect(titles).not.toContain("Dashboard");
  });

  it("drops footer entries already shown in the current shell", () => {
    const admin = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/users"));
    const footer = shellFooterItems(workspaceNavGroups, admin);
    const titles = footer.map((item) => item.title);
    expect(titles).not.toContain("Settings");
    expect(titles).not.toContain("Team & Access");
    expect(titles).not.toContain("Dashboard");
    expect(titles).toContain("Help");
  });

  it("compresses the footer into the group that contributed the most items", () => {
    const hub = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/dashboard"));
    const compressed = compressShellFooter(shellFooterItems(workspaceNavGroups, hub));
    expect(compressed).toHaveLength(1);
    expect(compressed[0]?.title).toBe("Settings");
    const childTitles = compressed[0]?.items?.map((item) => item.title) ?? [];
    expect(childTitles).toEqual(expect.arrayContaining(["Help", "Users", "Manage System"]));
  });

  it("leaves a single footer item uncompressed", () => {
    const admin = applyModuleShellToGroups(workspaceNavGroups, resolveModuleShell("/users"));
    const compressed = compressShellFooter(shellFooterItems(workspaceNavGroups, admin));
    expect(compressed.map((item) => item.title)).toEqual(["Help"]);
  });

  it("names a normal user's footer after the item they can open", () => {
    const icon = LayoutDashboard;
    const settings: WorkspaceTopNavItem = {
      title: "Settings",
      icon,
      permissions: [],
      items: [
        { title: "My E-Forms profile", href: "/e-approval/profile", permissions: [] },
        { title: "My exports", href: "/exports", permissions: [] },
      ],
    };
    const help: WorkspaceTopNavItem = {
      title: "Help",
      icon,
      href: "/help",
      permissions: [],
    };
    const compressed = compressShellFooter([settings, help]);
    expect(compressed[0]?.title).toBe("Settings");
    expect(compressed[0]?.items?.map((item) => item.title)).toEqual([
      "My E-Forms profile",
      "My exports",
      "Help",
    ]);
  });

  it("links each shell to its dashboard", () => {
    const links = moduleShellLinksFromGroups(workspaceNavGroups);
    expect(links.find((link) => link.id === "doc_extract")?.homeHref).toBe("/doc-extract");
    expect(links.find((link) => link.id === "e_approval")?.homeHref).toBe("/e-approval");
    expect(links.find((link) => link.id === "ticketing")?.homeHref).toBe("/ticketing");
    expect(links.find((link) => link.id === "document_register")?.homeHref).toBe("/documents/controlled");
    expect(links.map((link) => link.id)).not.toContain("administration");
  });

  it("matches command items by module or path", () => {
    const shell = resolveModuleShell("/ticketing");
    expect(commandItemMatchesShell({ href: "/ticketing/reports", module: "ticketing" }, shell)).toBe(true);
    expect(commandItemMatchesShell({ href: "/e-approval", module: "e_approval" }, shell)).toBe(false);
    expect(commandItemMatchesShell({ href: "/e-approval" }, resolveModuleShell("/dashboard"))).toBe(true);
  });

  it("includes a path-only child when the parent has no module", () => {
    const icon = LayoutDashboard;
    const foreign: WorkspaceTopNavItem = {
      title: "Loose",
      icon,
      permissions: [],
      items: [
        { title: "Inside", href: "/ticketing/reports", permissions: [] },
        { title: "Outside", href: "/e-approval", permissions: [] },
      ],
    };
    const scoped = applyModuleShellToGroups(
      [{ group: "Operations", items: [foreign, { title: "Other", href: "/e-approval", icon: LifeBuoy, permissions: [], module: "e_approval" }] }],
      resolveModuleShell("/ticketing"),
    );
    expect(scoped[0]?.items.map((item) => item.title)).toEqual(["Loose"]);
    expect(scoped[0]?.items[0]?.items?.map((item) => item.title)).toEqual(["Inside"]);
  });
});
