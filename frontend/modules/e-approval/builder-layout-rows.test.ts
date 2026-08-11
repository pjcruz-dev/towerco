import { describe, expect, it } from "vitest";

import {
  applySortableOrderToLayoutRows,
  buildBuilderGroupSegments,
  buildCanvasSortableIds,
  fieldInsertIndexBeforeSortableId,
  fieldInsertIndexForSortablePosition,
  flattenSortableOrderToFields,
  resolveFieldInsertIndexFromCanvasTarget,
} from "@/modules/e-approval/builder-layout-rows";
import { buildFieldDisplayGroups } from "@/modules/e-approval/form-field-groups";
import { layoutRowBlockDragId, patchFieldLayout } from "@/modules/e-approval/field-layout";
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

describe("custom form builder layout", () => {
  it("groups fields under section headers for canvas rendering", () => {
    const fields = [
      makeField("intro", "text"),
      makeField("details_section", "section"),
      makeField("amount", "number"),
      makeField("notes", "textarea"),
    ];

    const groups = buildFieldDisplayGroups(fields);
    expect(groups).toHaveLength(2);
    expect(groups[0]?.header).toBeNull();
    expect(groups[0]?.items).toHaveLength(1);
    expect(groups[1]?.header?.field.name).toBe("details_section");
    expect(groups[1]?.items.map((e) => e.field.name)).toEqual(["amount", "notes"]);
  });

  it("shows empty column rows at the start of a section", () => {
    const fields = [
      makeField("section_a", "section"),
      makeField("field_a", "text"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const sectionGroup = groups.find((group) => group.header?.field.name === "section_a");
    const layoutRows = [{ id: "row_empty", columns: 3 as const, insert_index: 1 }];

    const segments = buildBuilderGroupSegments(
      sectionGroup!.items,
      layoutRows,
      sectionGroup!.header?.index ?? null,
    );
    expect(segments).toHaveLength(2);
    expect(segments[0]?.kind).toBe("empty-row");
    if (segments[0]?.kind === "empty-row") {
      expect(segments[0].row.columns).toBe(3);
    }
    expect(segments[1]?.kind).toBe("cluster");
  });

  it("keeps empty layout rows under an empty second section", () => {
    const fields = [
      makeField("section_a", "section"),
      makeField("name", "text"),
      makeField("section_b", "section"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const sectionB = groups.find((group) => group.header?.field.name === "section_b");
    expect(sectionB?.items).toHaveLength(0);

    const layoutRows = [{ id: "row_b_empty", columns: 2 as const, insert_index: 3 }];
    const segments = buildBuilderGroupSegments(
      sectionB!.items,
      layoutRows,
      sectionB!.header?.index ?? null,
      null,
    );

    expect(segments).toHaveLength(1);
    expect(segments[0]?.kind).toBe("empty-row");
    if (segments[0]?.kind === "empty-row") {
      expect(segments[0].row.id).toBe("row_b_empty");
    }
  });

  it("includes section headers in canvas sortable order", () => {
    const fields = [
      makeField("section_a", "section"),
      makeField("left", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const ids = buildCanvasSortableIds(fields, [], groups);

    expect(ids[0]).toBe("section_a");
    expect(ids[1]).toBe(layoutRowBlockDragId("row_1"));
    expect(ids[2]).toBe("left");
    expect(ids[3]).toBe("right");
  });

  it("maps sortable positions to flat field insert indices", () => {
    const fields = [
      makeField("first", "text"),
      makeField("second", "text"),
      makeField("third", "text"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const layoutRows = [{ id: "row_scaffold", columns: 2 as const, insert_index: 1 }];
    const ids = buildCanvasSortableIds(fields, layoutRows, groups);

    expect(ids).toEqual(["first", layoutRowBlockDragId("row_scaffold"), "second", "third"]);
    expect(fieldInsertIndexForSortablePosition(0, fields, layoutRows, groups)).toBe(0);
    expect(fieldInsertIndexForSortablePosition(1, fields, layoutRows, groups)).toBe(1);
    expect(fieldInsertIndexForSortablePosition(2, fields, layoutRows, groups)).toBe(1);
    expect(fieldInsertIndexForSortablePosition(3, fields, layoutRows, groups)).toBe(2);
  });

  it("resolves drop targets before row blocks and section fields", () => {
    const fields = [
      makeField("section_a", "section"),
      makeField("alpha", "text"),
      makeField("beta", "text"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const layoutRows = [{ id: "row_scaffold", columns: 4 as const, insert_index: 2 }];
    const sortableIds = buildCanvasSortableIds(fields, layoutRows, groups);

    expect(
      resolveFieldInsertIndexFromCanvasTarget(sortableIds[2]!, fields, layoutRows, groups, sortableIds),
    ).toBe(2);
    expect(fieldInsertIndexBeforeSortableId(layoutRowBlockDragId("row_scaffold"), fields, layoutRows)).toBe(2);
  });

  it("reorders populated fields and updates empty row anchors", () => {
    const fields = [
      makeField("a", "text"),
      makeField("b", "text"),
      makeField("c", "text"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const layoutRows = [{ id: "row_scaffold", columns: 2 as const, insert_index: 3 }];
    const sortableIds = buildCanvasSortableIds(fields, layoutRows, groups);
    expect(sortableIds).toEqual(["a", "b", "c", layoutRowBlockDragId("row_scaffold")]);

    const nextOrder = [sortableIds[3]!, sortableIds[1]!, sortableIds[0]!, sortableIds[2]!];
    const nextFields = flattenSortableOrderToFields(nextOrder, fields);
    expect(nextFields.map((f) => f.name)).toEqual(["b", "a", "c"]);

    const nextRows = applySortableOrderToLayoutRows(nextOrder, fields, layoutRows);
    expect(nextRows[0]?.insert_index).toBe(0);
  });

  it("keeps multiple empty rows ordered after canvas reorder", () => {
    const fields = [makeField("section_a", "section"), makeField("only", "text")];
    const groups = buildFieldDisplayGroups(fields);
    const layoutRows = [
      { id: "row_a", columns: 2 as const, insert_index: 1 },
      { id: "row_b", columns: 3 as const, insert_index: 1 },
    ];
    const sortableIds = buildCanvasSortableIds(fields, layoutRows, groups);
    const nextOrder = [
      sortableIds[0]!,
      layoutRowBlockDragId("row_b"),
      layoutRowBlockDragId("row_a"),
      sortableIds[sortableIds.length - 1]!,
    ];

    const nextRows = applySortableOrderToLayoutRows(nextOrder, fields, layoutRows);
    expect(nextRows.map((row) => row.id)).toEqual(["row_b", "row_a"]);
  });

  it("preserves in-row field reorder when flattening sortable ids", () => {
    const fields = [
      makeField("left_a", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right_a", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
      makeField("left_b", "text", { row_id: "row_1", slot: 0, width: "half", row_columns: 2 }),
      makeField("right_b", "text", { row_id: "row_1", slot: 1, width: "half", row_columns: 2 }),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const sortableIds = buildCanvasSortableIds(fields, [], groups);
    expect(sortableIds).toEqual([
      layoutRowBlockDragId("row_1"),
      "left_a",
      "left_b",
      "right_a",
      "right_b",
    ]);

    // Move right_b up one within the column (swap with right_a in sortable order).
    const nextOrder = [
      sortableIds[0]!,
      "left_a",
      "left_b",
      "right_b",
      "right_a",
    ];
    const nextFields = flattenSortableOrderToFields(nextOrder, fields);
    expect(nextFields.map((f) => f.name)).toEqual(["left_a", "left_b", "right_b", "right_a"]);
  });
});
