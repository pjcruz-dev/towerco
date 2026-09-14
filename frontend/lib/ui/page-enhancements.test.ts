import { describe, expect, it } from "vitest";

import {
  resolvePickerGroup,
  type DashboardCatalogEntry,
} from "@/lib/ui/dashboard-widget-catalog";
import { getCatalogEntry } from "@/lib/ui/dashboard-widget-catalog";
import { isCatalogEntryDataBound } from "@/lib/ui/build-dynamic-board-widgets";
import { emptyNormalizedData, withPageEnhancements } from "@/lib/ui/dashboard-widget-data";

function stubEntry(partial: Partial<DashboardCatalogEntry> & Pick<DashboardCatalogEntry, "id" | "kind">): DashboardCatalogEntry {
  return {
    label: partial.label ?? partial.id,
    description: partial.description ?? "",
    category: partial.category ?? "enhancements",
    hazeSource: "shared",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    modules: ["ticketing"],
    ...partial,
  };
}

describe("page enhancements phase 1", () => {
  it("exposes enhancement catalog ids", () => {
    for (const id of [
      "page_shortcuts",
      "page_kpi_strip",
      "page_attention",
      "page_activity",
      "page_tip",
      "page_exports",
    ]) {
      expect(getCatalogEntry(id)?.bindMode).toBe("always");
      expect(resolvePickerGroup(getCatalogEntry(id)!)).toBe("enhancements");
    }
  });

  it("marks enhancements bindable without live metrics", () => {
    const entry = getCatalogEntry("page_tip")!;
    expect(isCatalogEntryDataBound("ticketing", entry, [], emptyNormalizedData())).toBe(true);
    expect(isCatalogEntryDataBound("doc-extract", getCatalogEntry("page_attention")!, [], emptyNormalizedData())).toBe(
      true,
    );
  });

  it("groups table as page section and charts as charts", () => {
    expect(resolvePickerGroup(stubEntry({ id: "table", kind: "table", category: "operations" }))).toBe(
      "sections",
    );
    expect(resolvePickerGroup(stubEntry({ id: "chart_donut", kind: "chart_donut", category: "charts" }))).toBe(
      "charts",
    );
  });

  it("merges enhancement bags onto normalized data", () => {
    const next = withPageEnhancements(emptyNormalizedData(), {
      shortcuts: [{ href: "/ticketing/tickets/new", label: "New ticket" }],
      attention: [{ id: "1", title: "2 at risk", tone: "warning" }],
      exportsTeaser: { href: "/e-approval/reports", label: "My exports", count: 3 },
    });
    expect(next.shortcuts).toHaveLength(1);
    expect(next.attention?.[0]?.title).toBe("2 at risk");
    expect(next.exportsTeaser?.count).toBe(3);
  });
});
