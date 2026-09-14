import { describe, expect, it } from "vitest";

import {
  DOC_EXTRACT_BATCHES_PAGE_CHROME,
  TICKETING_DASHBOARD_PAGE_CHROME,
  isActionVisible,
  resolvePageChrome,
} from "@/lib/ui/page-chrome-config";

describe("page-chrome-config", () => {
  it("uses defaults when prefs empty", () => {
    const chrome = resolvePageChrome(DOC_EXTRACT_BATCHES_PAGE_CHROME, {});
    expect(chrome.title).toBe("DocExtract");
    expect(chrome.visibleActionIds).toContain("customize");
    expect(chrome.visibleActionIds).toContain("new");
    expect(isActionVisible(chrome, "tour")).toBe(false);
  });

  it("hides tour by default on ticketing until prefs override", () => {
    const chrome = resolvePageChrome(TICKETING_DASHBOARD_PAGE_CHROME, null);
    expect(isActionVisible(chrome, "tour")).toBe(false);
    expect(isActionVisible(chrome, "new")).toBe(true);
    expect(isActionVisible(chrome, "customize")).toBe(true);
  });

  it("respects explicit empty hiddenActionIds (show all hideable)", () => {
    const chrome = resolvePageChrome(TICKETING_DASHBOARD_PAGE_CHROME, {
      hiddenActionIds: [],
    });
    expect(isActionVisible(chrome, "tour")).toBe(true);
  });

  it("hides hideable actions and keeps required customize", () => {
    const chrome = resolvePageChrome(DOC_EXTRACT_BATCHES_PAGE_CHROME, {
      title: "Extractions docs",
      hiddenActionIds: ["help", "tour", "templates", "new", "customize"],
    });
    expect(chrome.title).toBe("Extractions docs");
    expect(isActionVisible(chrome, "customize")).toBe(true);
    expect(isActionVisible(chrome, "help")).toBe(false);
    expect(isActionVisible(chrome, "new")).toBe(false);
  });

  it("applies action order and identity placement", () => {
    const chrome = resolvePageChrome(TICKETING_DASHBOARD_PAGE_CHROME, {
      actionOrder: ["new", "customize", "refresh", "help", "tour"],
      identityPlacement: "below",
      hiddenActionIds: ["tour"],
    });
    expect(chrome.identityPlacement).toBe("below");
    expect(chrome.visibleActionIds[0]).toBe("new");
    expect(chrome.visibleActionIds).toContain("customize");
    expect(chrome.visibleActionIds).not.toContain("tour");
  });
});
