import { describe, expect, it } from "vitest";

import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";

describe("resolveBrandingAssetUrl", () => {
  it("returns https URLs unchanged", () => {
    expect(resolveBrandingAssetUrl("https://cdn.example.com/logo.png")).toBe(
      "https://cdn.example.com/logo.png",
    );
  });

  it("prefixes hosted API paths with the API origin", () => {
    const resolved = resolveBrandingAssetUrl(
      "/api/v1/public/tenant-branding/logo?tenant=278e0e2c-ac8a-4b83-8b0a-fed3071fdc6b",
    );
    expect(resolved).toMatch(/^https?:\/\/.+\/api\/v1\/public\/tenant-branding\/logo\?tenant=/);
  });

  it("returns null for empty values", () => {
    expect(resolveBrandingAssetUrl(null)).toBeNull();
    expect(resolveBrandingAssetUrl("  ")).toBeNull();
  });
});
