import { describe, expect, it } from "vitest";

import {
  toEApprovalApiAssetPath,
} from "@/lib/e-approval/fetch-authenticated-asset";

describe("toEApprovalApiAssetPath", () => {
  it("normalizes absolute and relative API logo paths", () => {
    expect(toEApprovalApiAssetPath("/api/v1/e-approval/forms/x/subsidiary-logos/ATC")).toBe(
      "/e-approval/forms/x/subsidiary-logos/ATC",
    );
    expect(toEApprovalApiAssetPath("/e-approval/forms/x/subsidiary-logos/ADIC")).toBe(
      "/e-approval/forms/x/subsidiary-logos/ADIC",
    );
    expect(
      toEApprovalApiAssetPath("http://localhost:8000/api/v1/e-approval/forms/x/logo"),
    ).toBe("/e-approval/forms/x/logo");
  });

  it("skips data/blob URLs", () => {
    expect(toEApprovalApiAssetPath("data:image/png;base64,abc")).toBeNull();
    expect(toEApprovalApiAssetPath("blob:http://localhost/1")).toBeNull();
  });
});
