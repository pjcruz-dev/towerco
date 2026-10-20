import { describe, expect, it } from "vitest";

import { contrastForeground, cssColorToHex, resolveTenantChrome } from "@/lib/theme/tenant-chrome";

describe("tenant chrome", () => {
  it("turns computed rgb into hex", () => {
    expect(cssColorToHex("rgb(13, 148, 136)")).toBe("#0d9488");
  });

  it("picks light text for teal and dark text for amber", () => {
    expect(contrastForeground("#0d9488")).toBe("#fafafa");
    expect(contrastForeground("#7367f0")).toBe("#fafafa");
    expect(contrastForeground("#f59e0b")).toBe("#111827");
  });

  it("uses a live preview ahead of the saved workspace chrome", () => {
    const saved = {
      saved: true,
      primary_preset: "teal" as const,
      primary: "#0d9488",
      skin: "default" as const,
      layout: "vertical" as const,
      content: "compact" as const,
    };
    const preview = { ...saved, skin: "bordered" as const, saved: true };
    expect(resolveTenantChrome(saved, preview)?.skin).toBe("bordered");
    expect(resolveTenantChrome(saved, null)?.layout).toBe("vertical");
    expect(resolveTenantChrome({ ...saved, saved: false }, null)).toBeNull();
  });
});
