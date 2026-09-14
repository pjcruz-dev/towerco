import { describe, expect, it } from "vitest";

import {
  normalizeDashboardLayoutPrefs,
  resolveVisibleOrderedWidgets,
  type DashboardWidgetDef,
} from "@/lib/ui/dashboard-widget-registry";

describe("dashboard-widget-registry", () => {
  const widgets: DashboardWidgetDef[] = [
    { id: "a", label: "A", render: () => null },
    { id: "b", label: "B", hideable: false, render: () => null },
    { id: "c", label: "C", render: () => null },
  ];

  it("orders and hides soft-hideable widgets only", () => {
    const visible = resolveVisibleOrderedWidgets(widgets, ["c", "a", "b"], ["a"]);
    expect(visible.map((w) => w.id)).toEqual(["c", "b"]);
  });

  it("keeps non-hideable widgets even when listed as hidden", () => {
    const visible = resolveVisibleOrderedWidgets(widgets, [], ["b", "c"]);
    expect(visible.map((w) => w.id)).toEqual(["a", "b"]);
  });

  it("normalizes partial layout prefs", () => {
    expect(normalizeDashboardLayoutPrefs({ widgetOrder: ["x"] })).toEqual({
      widgetOrder: ["x"],
      hiddenWidgetIds: [],
      enabledWidgetIds: [],
      spans: {},
      widgetOptions: {},
      pageChrome: {},
    });
    expect(normalizeDashboardLayoutPrefs(null)).toEqual({
      widgetOrder: [],
      hiddenWidgetIds: [],
      enabledWidgetIds: [],
      spans: {},
      widgetOptions: {},
      pageChrome: {},
    });
  });
});
