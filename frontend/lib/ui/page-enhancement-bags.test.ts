import { describe, expect, it } from "vitest";

import {
  applyPageEnhancements,
  dedupeShortcutsByHref,
  docExtractBatchesEnhancements,
  eApprovalSubmissionsEnhancements,
  ticketingListEnhancements,
  workspaceDashboardEnhancements,
} from "@/lib/ui/page-enhancement-bags";
import { emptyNormalizedData } from "@/lib/ui/dashboard-widget-data";

describe("page-enhancement-bags", () => {
  it("builds doc-extract attention for failed/processing", () => {
    const bag = docExtractBatchesEnhancements({ processing: 2, failed: 1 });
    expect(bag.attention).toHaveLength(2);
    expect(bag.exportsTeaser?.href).toBe("/exports");
    expect(bag.shortcuts?.some((item) => item.href === "/doc-extract/new")).toBe(true);
  });

  it("builds ticketing list enhancements with open count", () => {
    const bag = ticketingListEnhancements({ openCount: 12, slaAtRisk: 3 });
    expect(bag.attention?.some((item) => item.id === "tk-sla")).toBe(true);
    expect(applyPageEnhancements(emptyNormalizedData(), bag).shortcuts?.length).toBeGreaterThan(0);
  });

  it("builds submissions returned attention", () => {
    const bag = eApprovalSubmissionsEnhancements({ returnedCount: 4 });
    expect(bag.attention?.[0]?.title).toContain("4");
  });

  it("dedupes workspace shortcuts when quick links overlap defaults", () => {
    const bag = workspaceDashboardEnhancements({
      quickLinks: [
        { href: "/e-approval", label: "Approvals" },
        { href: "/e-approval", label: "Duplicate" },
      ],
    });
    const hrefs = (bag.shortcuts ?? []).map((item) => item.href);
    expect(hrefs.filter((href) => href === "/e-approval")).toHaveLength(1);
    expect(hrefs).toContain("/ticketing/tickets");
    expect(dedupeShortcutsByHref([{ href: "/a", label: "A" }, { href: "/a", label: "B" }])).toEqual([
      { href: "/a", label: "A" },
    ]);
  });
});
