import { describe, expect, it } from "vitest";

import { resolveApiBaseUrl } from "@/lib/api/client";

describe("resolveApiBaseUrl", () => {
  it("leaves production same-host HTTPS unchanged", () => {
    expect(
      resolveApiBaseUrl("https://app.alliancetowers.com/api/v1", {
        href: "https://app.alliancetowers.com/dashboard",
      }),
    ).toBe("https://app.alliancetowers.com/api/v1");
  });

  it("leaves staging HTTPS unchanged", () => {
    expect(
      resolveApiBaseUrl("https://staging.alliancetowers.com/api/v1", {
        href: "https://staging.alliancetowers.com/e-approval",
      }),
    ).toBe("https://staging.alliancetowers.com/api/v1");
  });

  it("upgrades non-local HTTP API to HTTPS (passkey redirect safety)", () => {
    expect(
      resolveApiBaseUrl("http://staging.alliancetowers.com/api/v1", {
        href: "https://staging.alliancetowers.com/login",
      }),
    ).toBe("https://staging.alliancetowers.com/api/v1");
  });

  it("keeps localhost HTTP for local tenancy", () => {
    expect(
      resolveApiBaseUrl("http://localhost:8000/api/v1", {
        href: "http://atc.localhost:3000/dashboard",
      }),
    ).toBe("http://localhost:8000/api/v1");
  });

  it("keeps *.localhost HTTP", () => {
    expect(
      resolveApiBaseUrl("http://atc.localhost:8000/api/v1", {
        href: "http://atc.localhost:3000/dashboard",
      }),
    ).toBe("http://atc.localhost:8000/api/v1");
  });

  it("resolves relative /api/v1 against the page origin (same-host tenancy)", () => {
    expect(
      resolveApiBaseUrl("/api/v1", {
        href: "https://app.alliancetowers.com/dashboard",
      }),
    ).toBe("https://app.alliancetowers.com/api/v1");
  });

  it("strips trailing slash for axios baseURL", () => {
    expect(
      resolveApiBaseUrl("https://app.alliancetowers.com/api/v1/", {
        href: "https://app.alliancetowers.com/dashboard",
      }),
    ).toBe("https://app.alliancetowers.com/api/v1");
  });

  it("returns configured as-is during SSR without page context", () => {
    expect(resolveApiBaseUrl("https://app.alliancetowers.com/api/v1")).toBe(
      "https://app.alliancetowers.com/api/v1",
    );
  });
});
