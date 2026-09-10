import { describe, expect, it } from "vitest";

/**
 * Mirrors sort resolution in DashboardKindWidget — keep in sync when changing sort UX.
 */
function resolveSeriesSort(value: unknown): "asc" | "desc" | "none" {
  if (value === "asc" || value === "desc" || value === "none") return value;
  return "desc";
}

function sortSeries(
  rows: Array<{ key: string; label: string; value: number }>,
  sort: "asc" | "desc" | "none",
) {
  if (sort === "asc") return [...rows].sort((a, b) => a.value - b.value);
  if (sort === "none") return rows;
  return [...rows].sort((a, b) => b.value - a.value);
}

describe("dashboard series sort", () => {
  const rows = [
    { key: "a", label: "A", value: 3 },
    { key: "b", label: "B", value: 1 },
    { key: "c", label: "C", value: 2 },
  ];

  it("sorts low to high", () => {
    expect(sortSeries(rows, "asc").map((r) => r.key)).toEqual(["b", "c", "a"]);
  });

  it("sorts high to low", () => {
    expect(sortSeries(rows, "desc").map((r) => r.key)).toEqual(["a", "c", "b"]);
  });

  it("keeps as provided for none", () => {
    expect(sortSeries(rows, "none").map((r) => r.key)).toEqual(["a", "b", "c"]);
  });

  it("persists none instead of falling back to desc", () => {
    expect(resolveSeriesSort("none")).toBe("none");
    expect(resolveSeriesSort("asc")).toBe("asc");
    expect(resolveSeriesSort(undefined)).toBe("desc");
  });
});
