import { describe, expect, it } from "vitest";

import { formatOrganizationSlug, organizationSlugFromHostname } from "@/lib/tenant/organization-label";

describe("organizationSlugFromHostname", () => {
  it("parses production-style local hosts", () => {
    expect(organizationSlugFromHostname("app.towerone.localhost")).toBe("towerone");
    expect(organizationSlugFromHostname("staging.atc.localhost")).toBe("atc");
    expect(organizationSlugFromHostname("test.quantum.localhost")).toBe("quantum");
  });

  it("parses slug-only local hosts", () => {
    expect(organizationSlugFromHostname("atc.localhost")).toBe("atc");
  });

  it("returns null for brand hosts without slug", () => {
    expect(organizationSlugFromHostname("app.alliancetowers.com")).toBeNull();
    expect(organizationSlugFromHostname("staging.alliancetowers.com")).toBeNull();
  });

  it("parses legacy brand hosts that still include slug", () => {
    expect(organizationSlugFromHostname("app.atc.alliancetowers.com")).toBe("atc");
    expect(organizationSlugFromHostname("staging.atc.alliancetowers.com")).toBe("atc");
  });

  it("returns null for central dev hosts", () => {
    expect(organizationSlugFromHostname("localhost")).toBeNull();
    expect(organizationSlugFromHostname("127.0.0.1")).toBeNull();
  });
});

describe("formatOrganizationSlug", () => {
  it("normalizes slug casing", () => {
    expect(formatOrganizationSlug("TowerOne")).toBe("towerone");
    expect(formatOrganizationSlug("  ATC  ")).toBe("atc");
  });
});
