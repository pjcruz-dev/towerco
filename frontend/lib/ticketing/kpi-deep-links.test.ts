import { describe, expect, it } from "vitest";

import {
  ticketingKpiHref,
  ticketingPrefsFromSearchParams,
  withTicketingKpiHrefs,
} from "@/lib/ticketing/kpi-deep-links";
import { SPAN_CLASS } from "@/lib/ui/dashboard-widget-catalog";

describe("ticketing KPI deep links", () => {
  it("maps KPI keys to filtered ticket URLs", () => {
    expect(ticketingKpiHref("open")).toContain("status=open,in_progress");
    expect(ticketingKpiHref("assigned_me")).toContain("assigned_me=1");
    expect(ticketingKpiHref("urgent")).toContain("priority=urgent");
    expect(ticketingKpiHref("sla_at_risk")).toContain("sla_status=");
  });

  it("fills missing hrefs from keys", () => {
    const rows = withTicketingKpiHrefs([{ key: "open", label: "Open", value: 1 }]);
    expect(rows[0]?.href).toBe("/ticketing/tickets?status=open,in_progress");
  });

  it("hydrates prefs from URL search params", () => {
    const params = new URLSearchParams(
      "status=open,in_progress&priority=urgent&assigned_me=1&sla_status=at_risk,breached",
    );
    expect(ticketingPrefsFromSearchParams(params)).toEqual({
      status: "open,in_progress",
      priority: "urgent",
      assignedMe: true,
      mineOnly: false,
      slaStatus: "at_risk,breached",
    });
  });
});

describe("dashboard span classes", () => {
  it("packs quarter/half/third from the md breakpoint", () => {
    expect(SPAN_CLASS.quarter).toContain("md:col-span-3");
    expect(SPAN_CLASS.half).toContain("md:col-span-6");
    expect(SPAN_CLASS.third).toContain("md:col-span-4");
    expect(SPAN_CLASS.full).toBe("col-span-12");
  });
});
