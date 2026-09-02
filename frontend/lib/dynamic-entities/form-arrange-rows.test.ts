import { describe, expect, it } from "vitest";

import {
  clampSpan,
  flattenRowsToPatches,
  moveFieldInRows,
  packFieldsIntoRows,
  setFieldSpan,
  type ArrangeRow,
} from "@/lib/dynamic-entities/form-arrange-rows";
import type { DynField } from "@/lib/api/modules/dynamic-entities-api";

function field(partial: Partial<DynField> & Pick<DynField, "id" | "name" | "label">): DynField {
  return {
    entity_id: "e1",
    type: "text",
    is_required: false,
    show_in_table: true,
    is_filterable: false,
    field_order: 10,
    column_span: 6,
    form_group_id: null,
    options: null,
    ...partial,
  };
}

describe("form-arrange-rows", () => {
  it("packs three 4-span fields into one row", () => {
    const rows = packFieldsIntoRows([
      field({ id: "1", name: "a", label: "A", field_order: 10, column_span: 4 }),
      field({ id: "2", name: "b", label: "B", field_order: 20, column_span: 4 }),
      field({ id: "3", name: "c", label: "C", field_order: 30, column_span: 4 }),
    ]);
    expect(rows).toHaveLength(1);
    expect(rows[0]!.fields.map((f) => f.id)).toEqual(["1", "2", "3"]);
  });

  it("wraps when span would exceed 12", () => {
    const rows = packFieldsIntoRows([
      field({ id: "1", name: "a", label: "A", field_order: 10, column_span: 6 }),
      field({ id: "2", name: "b", label: "B", field_order: 20, column_span: 6 }),
      field({ id: "3", name: "c", label: "C", field_order: 30, column_span: 6 }),
    ]);
    expect(rows).toHaveLength(2);
    expect(rows[0]!.fields).toHaveLength(2);
    expect(rows[1]!.fields).toHaveLength(1);
  });

  it("excludes workflow system chrome fields", () => {
    const rows = packFieldsIntoRows([
      field({ id: "1", name: "workflows", label: "Workflows", field_order: 1 }),
      field({ id: "2", name: "title", label: "Title", field_order: 10, column_span: 12 }),
    ]);
    expect(rows[0]!.fields.map((f) => f.name)).toEqual(["title"]);
  });

  it("clamps span when adjusting within a row", () => {
    const rows: ArrangeRow[] = [
      {
        id: "r1",
        fields: [
          { id: "1", name: "a", label: "A", type: "text", column_span: 4 },
          { id: "2", name: "b", label: "B", type: "text", column_span: 4 },
          { id: "3", name: "c", label: "C", type: "text", column_span: 4 },
        ],
      },
    ];
    const next = setFieldSpan(rows, "1", 8);
    expect(next[0]!.fields[0]!.column_span).toBe(4); // 12 - 4 - 4
  });

  it("flattens rows to ordered patches", () => {
    const patches = flattenRowsToPatches([
      {
        id: "r1",
        fields: [
          { id: "a", name: "a", label: "A", type: "text", column_span: 6 },
          { id: "b", name: "b", label: "B", type: "text", column_span: 6 },
        ],
      },
      {
        id: "r2",
        fields: [{ id: "c", name: "c", label: "C", type: "text", column_span: 12 }],
      },
    ]);
    expect(patches).toEqual([
      { id: "a", field_order: 10, column_span: 6 },
      { id: "b", field_order: 20, column_span: 6 },
      { id: "c", field_order: 30, column_span: 12 },
    ]);
  });

  it("inserts into a full 4/4/4 row and overflows the last field", () => {
    const rows: ArrangeRow[] = [
      {
        id: "r1",
        fields: [
          { id: "1", name: "a", label: "A", type: "text", column_span: 4 },
          { id: "2", name: "b", label: "B", type: "text", column_span: 4 },
          { id: "3", name: "c", label: "C", type: "text", column_span: 4 },
        ],
      },
      {
        id: "r2",
        fields: [{ id: "4", name: "d", label: "D", type: "text", column_span: 4 }],
      },
    ];
    const next = moveFieldInRows(rows, "4", "2");
    expect(next[0]!.fields.map((f) => f.id)).toEqual(["1", "4", "2"]);
    expect(next[1]!.fields.map((f) => f.id)).toContain("3");
  });

  it("moves onto a row droppable id", () => {
    const rows: ArrangeRow[] = [
      {
        id: "r1",
        fields: [{ id: "1", name: "a", label: "A", type: "text", column_span: 4 }],
      },
      {
        id: "r2",
        fields: [{ id: "2", name: "b", label: "B", type: "text", column_span: 4 }],
      },
    ];
    const next = moveFieldInRows(rows, "2", "arrange-row:r1");
    expect(next[0]!.fields.map((f) => f.id)).toEqual(["1", "2"]);
  });

  it("clampSpan bounds values", () => {
    expect(clampSpan(0)).toBe(1);
    expect(clampSpan(99)).toBe(12);
    expect(clampSpan(4.6)).toBe(5);
  });
});
