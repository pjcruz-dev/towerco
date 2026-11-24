import { describe, expect, it } from "vitest";

import { loginPathWithNext, safeInternalNext, tenantPostLoginPath } from "@/lib/auth/tenant-post-login-path";

describe("safeInternalNext", () => {
  it("keeps a module dashboard path", () => {
    expect(safeInternalNext("/e-approval")).toBe("/e-approval");
    expect(safeInternalNext("/doc-extract/batches?status=failed")).toBe("/doc-extract/batches?status=failed");
  });

  it("rejects off-site and login loops", () => {
    expect(safeInternalNext("https://evil.example/e-approval")).toBeNull();
    expect(safeInternalNext("//evil.example")).toBeNull();
    expect(safeInternalNext("/login")).toBeNull();
    expect(safeInternalNext("/login?next=/e-approval")).toBeNull();
  });
});

describe("tenantPostLoginPath", () => {
  it("returns the requested module after sign-in", () => {
    expect(tenantPostLoginPath({ passkeyEnrollmentRequired: false }, "/ticketing")).toBe("/ticketing");
    expect(tenantPostLoginPath({ passkeyEnrollmentRequired: false }, null)).toBe("/dashboard");
    expect(tenantPostLoginPath({ passkeyEnrollmentRequired: true }, "/ticketing")).toBe(
      "/account/security?tab=passkeys&required=1",
    );
  });

  it("carries next onto the login and mfa urls", () => {
    expect(loginPathWithNext("/login", "/documents/controlled")).toBe(
      "/login?next=%2Fdocuments%2Fcontrolled",
    );
    expect(loginPathWithNext("/login/mfa", "/e-approval")).toBe("/login/mfa?next=%2Fe-approval");
  });
});