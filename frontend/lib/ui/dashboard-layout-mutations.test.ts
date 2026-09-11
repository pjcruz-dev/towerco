import { describe, expect, it } from "vitest";

import {
  applyLayoutPreset,
  applyWidgetMinHeight,
  applyWidgetSettings,
  applyWidgetSpan,
  duplicateWidgetInLayout,
  insertWidgetIntoLayout,
  nearestAllowedSpan,
  removeWidgetFromLayout,
} from "@/lib/ui/dashboard-layout-mutations";
import { EMPTY_DASHBOARD_LAYOUT_PREFS } from "@/lib/ui/dashboard-widget-registry";

describe("dashboard-layout-mutations", () => {
  it("snaps resize units to nearest allowed span", () => {
    expect(nearestAllowedSpan(5, ["full", "half", "third", "quarter"])).toBe("third");
    expect(nearestAllowedSpan(7, ["full", "half", "third", "quarter"])).toBe("half");
    expect(nearestAllowedSpan(3, ["full", "half"])).toBe("half");
    expect(nearestAllowedSpan(11, ["full", "half", "third"])).toBe("full");
  });

  it("applies span and height into layout prefs", () => {
    const withSpan = applyWidgetSpan(EMPTY_DASHBOARD_LAYOUT_PREFS, "kpis", "half");
    expect(withSpan.spans.kpis).toBe("half");
    expect(withSpan.widgetOptions.kpis?.span).toBe("half");

    const withHeight = applyWidgetMinHeight(withSpan, "kpis", 240);
    expect(withHeight.widgetOptions.kpis?.settings?.minHeight).toBe(240);
  });

  it("keeps sequential Card appearance settings when applied from the latest layout", () => {
    const first = applyWidgetSettings(EMPTY_DASHBOARD_LAYOUT_PREFS, "kpis", {
      kpiCards: '{"awaiting":{"accent":"sky"}}',
    });
    const second = applyWidgetSettings(first, "kpis", {
      kpiCards: '{"awaiting":{"accent":"sky","sparkStyle":"gauge"}}',
    });
    expect(second.widgetOptions.kpis?.settings?.kpiCards).toContain("gauge");
    expect(second.widgetOptions.kpis?.settings?.kpiCards).toContain("sky");
  });

  it("removes widget from enabled order and options", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["kpis", "queues", "shortcuts"],
      widgetOrder: ["queues", "kpis", "shortcuts"],
      spans: { kpis: "half" as const },
    };
    const next = removeWidgetFromLayout(layout, "kpis", ["kpis", "queues", "shortcuts"]);
    expect(next.enabledWidgetIds).toEqual(["queues", "shortcuts"]);
    expect(next.widgetOrder).toEqual(["queues", "shortcuts"]);
    expect(next.spans.kpis).toBeUndefined();
  });

  it("does not remove catalog widgets marked non-removable", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["filters", "template_grid"],
      widgetOrder: ["filters", "template_grid"],
    };
    const next = removeWidgetFromLayout(layout, "template_grid", ["filters", "template_grid"]);
    expect(next).toEqual(layout);
    expect(next.enabledWidgetIds).toEqual(["filters", "template_grid"]);
  });

  it("removes the data table widget when requested", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["filters", "table"],
      widgetOrder: ["filters", "table"],
    };
    const next = removeWidgetFromLayout(layout, "table", ["filters", "table"]);
    expect(next.enabledWidgetIds).toEqual(["filters"]);
    expect(next.widgetOrder).toEqual(["filters"]);
  });

  it("inserts widget at a specific index", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["kpis", "queues"],
      widgetOrder: ["kpis", "queues"],
    };
    const next = insertWidgetIntoLayout(layout, "chart_donut", "third", 1, ["kpis", "queues"], [
      "kpis",
      "queues",
    ]);
    expect(next.enabledWidgetIds).toContain("chart_donut");
    expect(next.widgetOrder).toEqual(["kpis", "chart_donut", "queues"]);
    expect(next.spans.chart_donut).toBe("third");
  });

  it("duplicates an existing widget with ~n id", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["kpis", "chart_donut"],
      widgetOrder: ["kpis", "chart_donut"],
      spans: { chart_donut: "half" as const },
      widgetOptions: {
        chart_donut: { title: "Donut", settings: { limit: 5 } },
      },
    };
    const next = duplicateWidgetInLayout(layout, "chart_donut", ["kpis", "chart_donut"], [
      "kpis",
      "chart_donut",
    ]);
    expect(next.widgetOrder).toEqual(["kpis", "chart_donut", "chart_donut~2"]);
    expect(next.enabledWidgetIds).toContain("chart_donut~2");
    expect(next.spans["chart_donut~2"]).toBe("half");
    expect(next.widgetOptions["chart_donut~2"]?.title).toBe("Donut (copy)");
  });

  it("inserts a second instance when catalog id already exists", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["chart_donut"],
      widgetOrder: ["chart_donut"],
    };
    const next = insertWidgetIntoLayout(layout, "chart_donut", "half", 1, ["chart_donut"], [
      "chart_donut",
    ]);
    expect(next.widgetOrder).toEqual(["chart_donut", "chart_donut~2"]);
  });

  it("applies compact preset spans", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["kpis", "chart_donut", "queues"],
      widgetOrder: ["kpis", "chart_donut", "queues"],
    };
    const next = applyLayoutPreset(layout, "compact", ["kpis", "chart_donut", "queues"]);
    expect(next.spans.kpis).toBe("full");
    expect(next.spans.chart_donut).toBe("half");
    expect(next.widgetOptions.chart_donut?.settings?.compact).toBe(true);
  });

  it("applies ops role pack from available page widgets", () => {
    const available = [
      "filters",
      "table",
      "page_attention",
      "page_kpi_strip",
      "page_shortcuts",
      "page_activity",
      "page_exports",
      "chart_donut",
    ];
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: available,
      widgetOrder: available,
      pageChrome: { title: "Custom" },
    };
    const next = applyLayoutPreset(layout, "ops", available, {
      defaultEnabledIds: ["filters", "table"],
      availableIds: available,
    });
    expect(next.enabledWidgetIds).toEqual(["filters", "page_attention", "table"]);
    expect(next.widgetOrder).toEqual(["filters", "page_attention", "table"]);
    expect(next.hiddenWidgetIds).toEqual([]);
  });

  it("applies manager role pack with split density", () => {
    const available = [
      "filters",
      "table",
      "page_kpi_strip",
      "page_shortcuts",
      "page_attention",
      "chart_donut",
    ];
    const next = applyLayoutPreset(EMPTY_DASHBOARD_LAYOUT_PREFS, "manager", available, {
      defaultEnabledIds: ["filters", "table"],
      availableIds: available,
    });
    expect(next.enabledWidgetIds).toEqual([
      "page_kpi_strip",
      "page_shortcuts",
      "page_attention",
      "filters",
      "table",
      "chart_donut",
    ]);
    expect(next.spans.page_kpi_strip).toBe("full");
    expect(next.spans.page_shortcuts).toBe("half");
  });

  it("applies auditor role pack with compact density", () => {
    const available = ["filters", "table", "page_activity", "page_exports", "page_kpi_strip"];
    const next = applyLayoutPreset(EMPTY_DASHBOARD_LAYOUT_PREFS, "auditor", available, {
      defaultEnabledIds: ["filters", "table"],
      availableIds: available,
    });
    expect(next.enabledWidgetIds).toEqual(["filters", "table", "page_activity", "page_exports"]);
    expect(next.spans.filters).toBe("full");
    expect(next.spans.table).toBe("half");
    expect(next.widgetOptions.table?.settings?.compact).toBe(true);
  });

  it("resets page defaults and clears personal overrides", () => {
    const layout = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["table", "page_exports"],
      widgetOrder: ["page_exports", "table"],
      spans: { table: "half" as const },
      widgetOptions: { table: { title: "Queue" } },
      pageChrome: { title: "My title", hiddenActionIds: ["help"] },
      hiddenWidgetIds: ["filters"],
    };
    const next = applyLayoutPreset(layout, "reset", ["table", "page_exports"], {
      defaultEnabledIds: ["filters", "table"],
    });
    expect(next.enabledWidgetIds).toEqual(["filters", "table"]);
    expect(next.widgetOrder).toEqual(["filters", "table"]);
    expect(next.spans).toEqual({});
    expect(next.widgetOptions).toEqual({});
    expect(next.pageChrome).toEqual({});
    expect(next.hiddenWidgetIds).toEqual([]);
  });

  it("keeps required table when ops pack has sparse available ids", () => {
    const next = applyLayoutPreset(EMPTY_DASHBOARD_LAYOUT_PREFS, "ops", ["table"], {
      defaultEnabledIds: ["filters", "table"],
      availableIds: ["table", "page_attention"],
    });
    expect(next.enabledWidgetIds).toContain("table");
    expect(next.enabledWidgetIds).toContain("page_attention");
  });
});
