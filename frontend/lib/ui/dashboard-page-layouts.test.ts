import { describe, expect, it } from "vitest";

import { resolveEffectiveDashboardLayout } from "@/lib/ui/dashboard-page-layouts";
import { EMPTY_DASHBOARD_LAYOUT_PREFS } from "@/lib/ui/dashboard-widget-registry";

describe("dashboard-page-layouts", () => {
  it("prefers personal override over tenant default", () => {
    const personal = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["table", "page_exports"],
    };
    const tenantDefault = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["filters", "table", "page_attention"],
    };
    const resolved = resolveEffectiveDashboardLayout({
      personal,
      tenantDefault,
      local: EMPTY_DASHBOARD_LAYOUT_PREFS,
    });
    expect(resolved.source).toBe("personal");
    expect(resolved.layout.enabledWidgetIds).toEqual(["table", "page_exports"]);
  });

  it("uses tenant default when personal is absent", () => {
    const tenantDefault = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["filters", "table"],
      widgetOrder: ["filters", "table"],
    };
    const resolved = resolveEffectiveDashboardLayout({
      personal: null,
      tenantDefault,
      local: EMPTY_DASHBOARD_LAYOUT_PREFS,
    });
    expect(resolved.source).toBe("tenant");
    expect(resolved.layout.enabledWidgetIds).toEqual(["filters", "table"]);
  });

  it("falls back to local cache when neither personal nor tenant exist", () => {
    const local = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["table"],
      widgetOrder: ["table"],
    };
    const resolved = resolveEffectiveDashboardLayout({
      personal: null,
      tenantDefault: null,
      local,
    });
    expect(resolved.source).toBe("local");
    expect(resolved.layout.enabledWidgetIds).toEqual(["table"]);
  });

  it("merges fresher local widget options onto personal when Card appearance differs", () => {
    const personal = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["kpis"],
      widgetOptions: {
        kpis: { title: "Status KPIs" },
      },
    };
    const local = {
      ...EMPTY_DASHBOARD_LAYOUT_PREFS,
      enabledWidgetIds: ["kpis"],
      widgetOptions: {
        kpis: {
          title: "Status KPIs",
          settings: { kpiCards: '{"awaiting":{"accent":"emerald"}}' },
        },
      },
    };
    const resolved = resolveEffectiveDashboardLayout({
      personal,
      tenantDefault: null,
      local,
    });
    expect(resolved.source).toBe("personal");
    expect(resolved.layout.widgetOptions.kpis?.settings?.kpiCards).toContain("emerald");
  });
});
