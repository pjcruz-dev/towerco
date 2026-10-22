import { describe, expect, it } from "vitest";

import {
  authPanelUsesBrandMark,
  contrastForeground,
  cssColorToHex,
  resolveTenantChrome,
} from "@/lib/theme/tenant-chrome";

describe("tenant chrome", () => {
  it("turns computed rgb into hex", () => {
    expect(cssColorToHex("rgb(13, 148, 136)")).toBe("#0d9488");
  });

  it("keeps saturated brand marks on the dark auth panel and drops near-black", () => {
    const panel = "#0f172a";
    expect(authPanelUsesBrandMark("#171717", panel)).toBe(false);
    expect(authPanelUsesBrandMark("#16a34a", panel)).toBe(true);
    expect(authPanelUsesBrandMark("#0d9488", panel)).toBe(true);
    expect(authPanelUsesBrandMark("#7367f0", panel)).toBe(true);
    expect(authPanelUsesBrandMark("#e11d48", panel)).toBe(true);
    expect(authPanelUsesBrandMark("#f59e0b", panel)).toBe(true);
    expect(authPanelUsesBrandMark("#2563eb", panel)).toBe(true);
    expect(authPanelUsesBrandMark("#052e16", panel)).toBe(false);
    expect(authPanelUsesBrandMark("#f9fafb", "#111827")).toBe(true);
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
