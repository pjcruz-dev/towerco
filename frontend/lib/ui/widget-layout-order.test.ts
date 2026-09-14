import { describe, expect, it } from "vitest";

import {
  applyWidgetOrder,
  moveWidgetOrderId,
  resolveWidgetOrderIds,
  widgetOrderIsCustom,
} from "@/lib/ui/widget-layout-order";

describe("widget-layout-order", () => {
  it("applies saved order and appends new widgets", () => {
    const widgets = [{ id: "a" }, { id: "b" }, { id: "c" }];
    expect(applyWidgetOrder(widgets, ["c", "a"]).map((w) => w.id)).toEqual(["c", "a", "b"]);
  });

  it("moves widgets up and down", () => {
    expect(moveWidgetOrderId(["a", "b", "c"], "b", -1)).toEqual(["b", "a", "c"]);
    expect(moveWidgetOrderId(["a", "b", "c"], "b", 1)).toEqual(["a", "c", "b"]);
    expect(moveWidgetOrderId(["a", "b", "c"], "a", -1)).toEqual(["a", "b", "c"]);
  });

  it("detects custom order", () => {
    const defaults = ["a", "b", "c"];
    expect(widgetOrderIsCustom(defaults, [])).toBe(false);
    expect(widgetOrderIsCustom(defaults, ["a", "b", "c"])).toBe(false);
    expect(widgetOrderIsCustom(defaults, ["c", "a", "b"])).toBe(true);
    expect(resolveWidgetOrderIds(defaults, ["c"])).toEqual(["c", "a", "b"]);
  });
});
