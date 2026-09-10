import { describe, expect, it } from "vitest";

import { resolveDashboardSkeletonSlots } from "@/lib/ui/dashboard-board-skeleton";
import { EMPTY_DASHBOARD_LAYOUT_PREFS } from "@/lib/ui/dashboard-widget-registry";

describe("resolveDashboardSkeletonSlots", () => {
  it("uses default enabled ids when layout is empty", () => {
    const slots = resolveDashboardSkeletonSlots({
      layout: EMPTY_DASHBOARD_LAYOUT_PREFS,
      defaultEnabledIds: ["filters", "table", "page_attention"],
    });
    expect(slots.map((slot) => slot.id)).toEqual(["filters", "table", "page_attention"]);
    expect(slots[0]?.kindHint).toBe("filters");
    expect(slots[1]?.kindHint).toBe("table");
    expect(slots[2]?.kindHint).toBe("attention");
  });

  it("respects enabled order, spans, and hidden widgets", () => {
    const slots = resolveDashboardSkeletonSlots({
      layout: {
        ...EMPTY_DASHBOARD_LAYOUT_PREFS,
        enabledWidgetIds: ["table", "kpis", "chart_donut"],
        widgetOrder: ["kpis", "chart_donut", "table"],
        hiddenWidgetIds: ["chart_donut"],
        spans: { kpis: "full", table: "half" },
      },
      defaultEnabledIds: ["filters", "table"],
    });
    expect(slots.map((slot) => slot.id)).toEqual(["kpis", "table"]);
    expect(slots[0]?.span).toBe("full");
    expect(slots[0]?.kindHint).toBe("kpi");
    expect(slots[1]?.span).toBe("half");
    expect(slots[1]?.kindHint).toBe("table");
  });

  it("maps workspace home sections to useful shapes", () => {
    const slots = resolveDashboardSkeletonSlots({
      layout: {
        ...EMPTY_DASHBOARD_LAYOUT_PREFS,
        enabledWidgetIds: ["awaiting_me", "action_charts", "recent_activity"],
        widgetOrder: ["awaiting_me", "action_charts", "recent_activity"],
        spans: { action_charts: "full", recent_activity: "half" },
      },
      defaultEnabledIds: ["kpis", "awaiting_me"],
    });
    expect(slots.map((s) => s.kindHint)).toEqual(["attention", "chart", "list"]);
    expect(slots[2]?.span).toBe("half");
  });
});
