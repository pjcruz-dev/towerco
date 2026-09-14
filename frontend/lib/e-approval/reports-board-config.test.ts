import { describe, expect, it } from "vitest";

import {
  mergeReportsEnabledIdsForTab,
  reportsAddableCatalogEntries,
  reportsBoardDefaultEnabledIds,
  resolveReportsEnabledWidgetIds,
  resolveReportsWidgetChrome,
} from "@/lib/e-approval/reports-board-config";

describe("reports-board-config", () => {
  it("defaults include export widgets and core analytics for auditors", () => {
    const ids = reportsBoardDefaultEnabledIds(true);
    expect(ids).toContain("reports_analytics_filters");
    expect(ids).toContain("reports_export_builder");
    expect(ids).not.toContain("reports_analytics_bottlenecks");
  });

  it("hides audit-only widgets for non-auditors", () => {
    const ids = reportsBoardDefaultEnabledIds(false);
    expect(ids.every((id) => !id.startsWith("reports_analytics_"))).toBe(true);
    expect(ids).toContain("reports_export_builder");
  });

  it("expands legacy mono analytics widget", () => {
    const ids = resolveReportsEnabledWidgetIds(
      ["reports_analytics", "reports_export_builder"],
      true,
    );
    expect(ids).toContain("reports_analytics_kpis");
    expect(ids).not.toContain("reports_analytics");
    expect(ids).toContain("reports_export_builder");
  });

  it("lists optional analytics in addable catalog", () => {
    const enabled = reportsBoardDefaultEnabledIds(true);
    const addable = reportsAddableCatalogEntries(enabled, true);
    expect(addable.some((entry) => entry.id === "reports_analytics_bottlenecks")).toBe(true);
    expect(addable.some((entry) => entry.id === "reports_export_builder")).toBe(false);
  });

  it("filters addable catalog by hub tab group", () => {
    const enabled = reportsBoardDefaultEnabledIds(true);
    const analyticsAddable = reportsAddableCatalogEntries(enabled, true, "analytics");
    expect(analyticsAddable.every((entry) => entry.id.startsWith("reports_analytics_"))).toBe(true);
    const exportsAddable = reportsAddableCatalogEntries(enabled, true, "exports");
    expect(exportsAddable.every((entry) => !entry.id.startsWith("reports_analytics_"))).toBe(true);
  });

  it("merges tab enabled ids without dropping the other hub group", () => {
    const previous = [
      "reports_analytics_kpis",
      "reports_export_builder",
      "reports_saved",
    ];
    const nextAnalytics = ["reports_analytics_trend", "reports_analytics_kpis"];
    expect(mergeReportsEnabledIdsForTab(previous, nextAnalytics, "analytics")).toEqual([
      "reports_analytics_trend",
      "reports_analytics_kpis",
      "reports_export_builder",
      "reports_saved",
    ]);
  });

  it("resolves Layout & options chrome", () => {
    const chrome = resolveReportsWidgetChrome("reports_analytics_kpis", {
      title: " My KPIs ",
      settings: { showDescription: true, compact: true },
    });
    expect(chrome.title).toBe("My KPIs");
    expect(chrome.description).toContain("KPI");
    expect(chrome.compact).toBe(true);

    const hidden = resolveReportsWidgetChrome("reports_analytics_kpis", {
      settings: { showDescription: false },
    });
    expect(hidden.description).toBeNull();
  });
});
