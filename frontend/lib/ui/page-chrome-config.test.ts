import { describe, expect, it } from "vitest";

import {
  DOC_EXTRACT_BATCHES_PAGE_CHROME,
  isActionVisible,
  resolvePageChrome,
} from "@/lib/ui/page-chrome-config";

describe("page-chrome-config", () => {
  it("uses defaults when prefs empty", () => {
    const chrome = resolvePageChrome(DOC_EXTRACT_BATCHES_PAGE_CHROME, {});
    expect(chrome.title).toBe("DocExtract");
    expect(chrome.visibleActionIds).toContain("customize");
    expect(chrome.visibleActionIds).toContain("new");
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
});
