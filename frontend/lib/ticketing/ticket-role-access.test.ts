import { describe, expect, it } from "vitest";

import {
  filterNavGroupsForTicketingPreview,
  ticketingEntityAllows,
  ticketingNavTargetForPath,
  ticketingNavTargetHidden,
  ticketingPreviewAllowsPath,
} from "@/lib/ticketing/ticket-role-access";

describe("ticketing role access", () => {
  it("leaves a role open until Ticketing access is set", () => {
    expect(ticketingEntityAllows(undefined, "view")).toBe(true);
    expect(
      ticketingEntityAllows(
        { entities: { e_approval_submission: { view: false, create: false, edit: false, delete: false, export: false } } },
        "view",
      ),
    ).toBe(true);
  });

  it("hides Reports without hiding the rest of Ticketing", () => {
    const granted = ["ticketing:view"];
    const matrix = {
      fields: {
        ticketing_ticket: { "nav:/ticketing/reports": "hide" as const },
      },
    };
    expect(ticketingNavTargetHidden(matrix, "/ticketing/reports")).toBe(true);
    expect(ticketingPreviewAllowsPath(granted, matrix, "/ticketing/reports")).toBe(false);
    expect(ticketingPreviewAllowsPath(granted, matrix, "/ticketing")).toBe(true);
    expect(ticketingPreviewAllowsPath(granted, matrix, "/ticketing/tickets")).toBe(true);
    expect(ticketingPreviewAllowsPath(granted, matrix, "/e-approval/reports")).toBe(true);
  });

  it("treats a ticket record as Tickets and a new ticket as its own page", () => {
    expect(ticketingNavTargetForPath("/ticketing/tickets/abc")?.label).toBe("Tickets");
    expect(ticketingNavTargetForPath("/ticketing/tickets/new")?.label).toBe("New ticket");
    expect(ticketingNavTargetForPath("/ticketing")?.label).toBe("Overview");
    expect(ticketingNavTargetForPath("/ticketing/reports")?.label).toBe("Reports");
  });

  it("keeps the Ticketing group when Reports is hidden", () => {
    const groups = filterNavGroupsForTicketingPreview(
      [
        {
          group: "Operations",
          items: [
            {
              title: "Ticketing",
              href: "/ticketing",
              items: [
                { title: "Overview", href: "/ticketing" },
                { title: "Reports", href: "/ticketing/reports" },
              ],
            },
          ],
        },
      ],
      (href) => href !== "/ticketing/reports",
    );
    expect(groups[0]?.items[0]?.items?.map((item) => item.href)).toEqual(["/ticketing"]);
  });

  it("leaves an already empty E-Forms group in place", () => {
    const groups = filterNavGroupsForTicketingPreview(
      [
        {
          group: "Operations",
          items: [
            { title: "E-Forms", items: [] },
            {
              title: "Ticketing",
              items: [{ title: "Overview", href: "/ticketing" }],
            },
          ],
        },
      ],
      () => true,
    );
    expect(groups[0]?.items.map((item) => item.title)).toEqual(["E-Forms", "Ticketing"]);
  });
});
