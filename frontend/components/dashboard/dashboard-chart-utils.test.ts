import { describe, expect, it } from "vitest";

import { DASHBOARD_CHART, resolveChartFill, statusChartColor } from "@/components/dashboard/dashboard-chart-utils";

describe("status chart colors", () => {
  it("paints rejected red even when a palette fill was stored", () => {
    expect(statusChartColor("rejected")).toBe(DASHBOARD_CHART.danger);
    expect(statusChartColor("Rejected")).toBe(DASHBOARD_CHART.danger);
    expect(
      resolveChartFill({ key: "rejected", label: "Rejected", fill: "#28C76F" }, 3),
    ).toBe(DASHBOARD_CHART.danger);
  });

  it("keeps pending, approved, and draft on their status hues", () => {
    expect(statusChartColor("pending")).toBe(DASHBOARD_CHART.warning);
    expect(statusChartColor("approved")).toBe(DASHBOARD_CHART.success);
    expect(statusChartColor("draft")).toBe(DASHBOARD_CHART.sky);
  });

  it("leaves non-status labels on the row fill", () => {
    expect(statusChartColor("Document Approval")).toBeNull();
    expect(resolveChartFill({ key: "form-1", label: "Document Approval", fill: "#00BAD1" }, 0)).toBe(
      "#00BAD1",
    );
  });
});
