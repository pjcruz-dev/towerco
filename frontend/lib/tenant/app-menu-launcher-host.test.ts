import { describe, expect, it } from "vitest";

import {
  isAppMenuLauncherHostname,
  isCentralHostname,
  tenantDomainFromBrowserHostname,
} from "@/lib/tenant/resolve-tenant-domain";

describe("isAppMenuLauncherHostname", () => {
  it("matches appmenu subdomain hosts", () => {
    expect(isAppMenuLauncherHostname("appmenu.alliancetowers.com")).toBe(true);
    expect(isAppMenuLauncherHostname("appmenu.localhost")).toBe(true);
    expect(isAppMenuLauncherHostname("APPMENU.Example.COM")).toBe(true);
  });

  it("does not match workspace hosts", () => {
    expect(isAppMenuLauncherHostname("app.alliancetowers.com")).toBe(false);
    expect(isAppMenuLauncherHostname("staging.alliancetowers.com")).toBe(false);
    expect(isAppMenuLauncherHostname("alliancetowers.com")).toBe(false);
    expect(isAppMenuLauncherHostname("localhost")).toBe(false);
  });
});

describe("tenantDomainFromBrowserHostname with launcher", () => {
  it("does not treat appmenu hosts as tenant domains", () => {
    expect(tenantDomainFromBrowserHostname("appmenu.alliancetowers.com")).toBeNull();
    expect(tenantDomainFromBrowserHostname("appmenu.localhost")).toBeNull();
  });

  it("still treats workspace hosts as tenants", () => {
    expect(tenantDomainFromBrowserHostname("app.alliancetowers.com")).toBe("app.alliancetowers.com");
  });
});

describe("isCentralHostname vs launcher", () => {
  it("does not classify appmenu hosts as platform central by default", () => {
    // Launcher must not redirect /login → /platform/login
    expect(isCentralHostname("appmenu.alliancetowers.com")).toBe(false);
  });
});
