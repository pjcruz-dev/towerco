import { describe, expect, it } from "vitest";

import {
  assignEntriesToRowSlots,
  clusterEntriesByLayoutRow,
  findAdjacentFieldIndexInRowSlot,
  findLayoutRowInsertIndex,
  findNextAvailableRowSlot,
  moveFieldInRowSlot,
  normalizeFormFieldLayouts,
  parseFieldLayout,
  patchFieldLayout,
} from "@/modules/e-approval/field-layout";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function makeField(
  name: string,
  type: string,
  layout?: Parameters<typeof patchFieldLayout>[1],
): EApprovalFormFieldInput {
  const base: EApprovalFormFieldInput = {
    label: name,
    name,
    type,
    step_order: 1,
    options: {},
  };
  if (layout) {
    base.options = patchFieldLayout(base, layout);
  }
  return base;
}

describe("column row layout helpers", () => {
  it("clusters row members even when they are not adjacent in the flat list", () => {
    const entries = [
      { field: makeField("slot0", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }), index: 2 },
      { field: makeField("between", "textarea"), index: 3 },
      { field: makeField("slot1", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }), index: 4 },
    ];

    const nodes = clusterEntriesByLayoutRow(entries);
    expect(nodes).toHaveLength(2);
    expect(nodes[0]?.kind).toBe("row");
    if (nodes[0]?.kind === "row") {
      expect(nodes[0].entries.map((e) => e.field.name)).toEqual(["slot0", "slot1"]);
    }
    expect(nodes[1]?.kind).toBe("field");
  });

  it("inserts into an empty scaffold row at the scaffold anchor", () => {
    const fields = [makeField("section_a", "section"), makeField("inside", "text")];
    const layoutRows = [{ id: "row_scaffold", columns: 4 as const, insert_index: 1 }];

    expect(findLayoutRowInsertIndex(fields, "row_scaffold", 2, layoutRows)).toBe(1);
  });

  it("preserves stacked fields in the same column when normalizing", () => {
    const fields = [
      makeField("a", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("b", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("c", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
    ];

    const normalized = normalizeFormFieldLayouts(fields, [{ id: "row_1", columns: 2 }]);
    const slots = normalized
      .filter((field) => parseFieldLayout(field).row_id === "row_1")
      .map((field) => parseFieldLayout(field).slot);

    expect(slots).toEqual([0, 0, 1]);
  });

  it("places fields into the requested column slots for rendering", () => {
    const entries = [
      { field: makeField("right", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }), index: 1 },
      { field: makeField("left", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }), index: 0 },
    ];

    const slots = assignEntriesToRowSlots(entries, 2);
    expect(slots[0]?.map((e) => e.field.name)).toEqual(["left"]);
    expect(slots[1]?.map((e) => e.field.name)).toEqual(["right"]);
  });

  it("stacks multiple fields in the same column slot for rendering", () => {
    const entries = [
      { field: makeField("a", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }), index: 0 },
      { field: makeField("b", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }), index: 1 },
      { field: makeField("c", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }), index: 2 },
    ];

    const slots = assignEntriesToRowSlots(entries, 2);
    expect(slots[0]?.map((e) => e.field.name)).toEqual(["a", "b"]);
    expect(slots[1]?.map((e) => e.field.name)).toEqual(["c"]);
  });

  it("inserts after existing fields when stacking into an occupied column", () => {
    const fields = [
      makeField("left_a", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("left_b", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
    ];

    expect(findLayoutRowInsertIndex(fields, "row_1", 0)).toBe(2);
    expect(findLayoutRowInsertIndex(fields, "row_1", 1)).toBe(3);
  });

  it("finds the next open slot up to the configured column count", () => {
    const fields = [
      makeField("a", "text", { row_id: "row_1", slot: 0, width: "third", row_columns: 3 }),
      makeField("b", "text", { row_id: "row_1", slot: 2, width: "third", row_columns: 3 }),
    ];

    expect(findNextAvailableRowSlot("row_1", fields, 3)).toBe(1);
    expect(findNextAvailableRowSlot("row_1", fields, 4)).toBe(1);
  });

  it("does not assign layout slots to section fields", () => {
    const entries = [
      { field: makeField("section_a", "section", { row_id: "row_1", slot: 0 }), index: 0 },
      { field: makeField("value", "text"), index: 1 },
    ];

    const nodes = clusterEntriesByLayoutRow(entries);
    expect(nodes).toHaveLength(2);
    expect(nodes[0]?.kind).toBe("field");
    expect(nodes[1]?.kind).toBe("field");
  });

  it("finds previous/next field in the same column slot", () => {
    const fields = [
      makeField("left_a", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right_a", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
      makeField("left_b", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right_b", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
    ];

    expect(findAdjacentFieldIndexInRowSlot(fields, 3, -1)).toBe(1);
    expect(findAdjacentFieldIndexInRowSlot(fields, 1, 1)).toBe(3);
    expect(findAdjacentFieldIndexInRowSlot(fields, 1, -1)).toBeNull();
    expect(findAdjacentFieldIndexInRowSlot(fields, 2, -1)).toBe(0);
  });

  it("swaps stack_order so column order changes without relying on flat index", () => {
    const fields = [
      makeField("left_a", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right_a", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
      makeField("left_b", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right_b", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
    ];

    const moved = moveFieldInRowSlot(fields, 3, -1);
    expect(moved).not.toBeNull();
    const entries = moved!.fields.map((field, index) => ({ field, index }));
    const slots = assignEntriesToRowSlots(entries, 2);
    expect(slots[1]!.map((e) => e.field.name)).toEqual(["right_b", "right_a"]);
    expect(slots[0]!.map((e) => e.field.name)).toEqual(["left_a", "left_b"]);
  });
});
