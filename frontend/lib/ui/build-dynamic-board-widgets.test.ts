import { describe, expect, it } from "vitest";

import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import type { DashboardNormalizedData } from "@/lib/ui/dashboard-widget-data";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";

const data: DashboardNormalizedData = {
  kpis: [{ key: "open", label: "Open", value: "3", tone: "neutral" }],
  series: {
    status: [{ key: "open", label: "Open", value: 3 }],
    priority: [{ key: "high", label: "High", value: 1 }],
  },
  activity: [{ id: "1", title: "Ticket A" }],
  people: [{ id: "u1", name: "Alex", value: 2 }],
  shortcuts: [{ href: "/x", label: "Go" }],
  hero: { title: "Hello", ctaHref: "/x", ctaLabel: "CTA" },
};

const slots: DashboardWidgetDef[] = [
  { id: "kpis", label: "KPIs", render: () => "kpis" },
  { id: "recent_tickets", label: "Tickets", hideable: false, removable: false, render: () => "table" },
];

describe("buildDynamicBoardWidgets", () => {
  it("keeps slots and adds kind widgets from live data", () => {
    const board = buildDynamicBoardWidgets({
      moduleId: "ticketing",
      data,
      slots,
      enabledIds: ["kpis", "chart_donut", "hero_banner", "recent_tickets"],
    });
    expect(board.map((w) => w.id)).toEqual(["kpis", "chart_donut", "hero_banner", "recent_tickets"]);
  });

  it("aliases table to recent_tickets slot content", () => {
    const board = buildDynamicBoardWidgets({
      moduleId: "ticketing",
      data,
      slots,
      enabledIds: ["table"],
    });
    expect(board).toHaveLength(1);
    expect(board[0]?.id).toBe("table");
    expect(board[0]?.render()).toBe("table");
  });

  it("skips widgets that cannot bind to live data", () => {
    const board = buildDynamicBoardWidgets({
      moduleId: "ticketing",
      data: { kpis: [], series: {} },
      slots,
      enabledIds: ["list_leaderboard", "kpis"],
    });
    expect(board.map((w) => w.id)).toEqual(["kpis"]);
  });

  it("exposes only data-bound catalog entries for Add Widget", () => {
    const entries = bindableCatalogEntries("ticketing", slots, data);
    const ids = new Set(entries.map((e) => e.id));
    expect(ids.has("kpis")).toBe(true);
    expect(ids.has("chart_donut")).toBe(true);
    expect(ids.has("hero_banner")).toBe(true);
    expect(ids.has("recent_tickets")).toBe(true);
  });
});
