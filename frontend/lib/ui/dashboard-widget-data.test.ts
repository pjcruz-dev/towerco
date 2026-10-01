import { describe, expect, it } from "vitest";

import {
  multiSeriesFromData,
  seriesLooksChronological,
  type DashboardNormalizedData,
} from "@/lib/ui/dashboard-widget-data";

function bag(
  series: DashboardNormalizedData["series"],
): DashboardNormalizedData {
  return { kpis: [], series };
}

describe("seriesLooksChronological", () => {
  it("recognizes submission dates", () => {
    expect(
      seriesLooksChronological([
        { key: "2026-09-01", label: "Sep 1" },
        { key: "2026-09-02", label: "Sep 2" },
        { key: "2026-09-03", label: "Sep 3" },
      ]),
    ).toBe(true);
  });

  it("does not treat status labels as a timeline", () => {
    expect(
      seriesLooksChronological([
        { key: "open", label: "Open" },
        { key: "pending", label: "Pending" },
        { key: "closed", label: "Closed" },
      ]),
    ).toBe(false);
  });
});

describe("multiSeriesFromData", () => {
  it("does not overlay unrelated breakdowns by index", () => {
    const result = multiSeriesFromData(
      bag({
        status: [
          { key: "open", label: "Open", value: 4 },
          { key: "closed", label: "Closed", value: 2 },
        ],
        priority: [
          { key: "urgent", label: "Urgent", value: 9 },
          { key: "low", label: "Low", value: 1 },
        ],
      }),
    );

    expect(result.series).toHaveLength(1);
    expect(result.series[0]?.values).toEqual([4, 2]);
    expect(result.categories).toEqual(["Open", "Closed"]);
  });

  it("overlays series that share category keys", () => {
    const result = multiSeriesFromData(
      bag({
        status: [
          { key: "open", label: "Open", value: 4 },
          { key: "closed", label: "Closed", value: 2 },
        ],
        queue: [
          { key: "open", label: "Open", value: 3 },
          { key: "closed", label: "Closed", value: 1 },
        ],
      }),
    );

    expect(result.series.map((series) => series.key).sort()).toEqual(["queue", "status"]);
    const queue = result.series.find((series) => series.key === "queue");
    expect(queue?.values).toEqual([3, 1]);
  });
});
