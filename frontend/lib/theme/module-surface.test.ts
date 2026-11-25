import { describe, expect, it } from "vitest";

import { chosenModuleSurfaceVariables, moduleSurfaceVariables } from "@/lib/theme/tenant-chrome";

describe("module surfaces", () => {
  it("tints the page, sidebar, and cards from the accent", () => {
    const light = moduleSurfaceVariables("#0d9488", "light");
    expect(light.background).not.toBe("#f8fafc");
    expect(light.sidebar).not.toBe(light.background);
    expect(light.card).not.toBe(light.sidebar);
    expect(light["chart-1"]).toBe("#0d9488");
  });

  it("keeps status colors out of the module surface", () => {
    const keys = Object.keys(moduleSurfaceVariables("#7367f0", "dark"));
    expect(keys).not.toContain("destructive");
    expect(keys).not.toContain("success");
    expect(keys).not.toContain("warning");
  });

  it("keeps an empty module on the shared theme and matches text to a chosen color in dark mode", () => {
    expect(chosenModuleSurfaceVariables({}, "light")).toEqual({});
    expect(chosenModuleSurfaceVariables({}, "dark")).toEqual({});
    const dark = chosenModuleSurfaceVariables({ sidebar: "#ffffff", background: "#0d9488" }, "dark");
    expect(dark.sidebar).toBe("#ffffff");
    expect(dark.background).toBe("#0d9488");
    expect(dark["sidebar-foreground"]).toBe("#111827");
    expect(dark.foreground).toBe("#fafafa");
    const light = chosenModuleSurfaceVariables({ sidebar: "#e0f2f1", card: "#ffffff" }, "light");
    expect(light.sidebar).toBe("#e0f2f1");
    expect(light.card).toBe("#ffffff");
    expect(light["card-foreground"]).toBe("#111827");
    expect(light.background).toBeUndefined();
  });

  it("uses a darker page in dark mode than in light mode", () => {
    const light = moduleSurfaceVariables("#2563eb", "light").background;
    const dark = moduleSurfaceVariables("#2563eb", "dark").background;
    expect(light).not.toBe(dark);
  });
});
