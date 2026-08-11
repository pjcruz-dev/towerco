import { describe, expect, it } from "vitest";

import {
  buildCanvasSortableIds,
  canvasFieldSortableId,
  resolveFieldInsertIndexFromCanvasTarget,
  sectionGroupInsertIndex,
} from "@/modules/e-approval/builder-layout-rows";
import { buildFieldDisplayGroups } from "@/modules/e-approval/form-field-groups";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(
  partial: Pick<EApprovalFormFieldInput, "type" | "name" | "label"> &
    Partial<EApprovalFormFieldInput>,
): EApprovalFormFieldInput {
  return {
    type: partial.type,
    name: partial.name,
    label: partial.label,
    ...partial,
  };
}

describe("resolveFieldInsertIndexFromCanvasTarget — section drops", () => {
  const fields: EApprovalFormFieldInput[] = [
    field({ type: "section", name: "section", label: "Section" }),
    field({ type: "text", name: "contact_person", label: "Contact Person" }),
    field({ type: "phone", name: "tel_no", label: "Tel No" }),
    field({ type: "section", name: "bank_details", label: "Bank Details" }),
  ];

  const groups = buildFieldDisplayGroups(fields);
  const layoutRows: never[] = [];
  const sortableIds = buildCanvasSortableIds(fields, layoutRows, groups);

  it("inserts after a section header (into that section), not before it", () => {
    const bankHeaderId = canvasFieldSortableId(fields[3]!, 3);
    const insertAt = resolveFieldInsertIndexFromCanvasTarget(
      bankHeaderId,
      fields,
      layoutRows,
      groups,
      sortableIds,
    );
    expect(insertAt).toBe(4);
  });

  it("inserts after the first section header into the first section", () => {
    const firstHeaderId = canvasFieldSortableId(fields[0]!, 0);
    const insertAt = resolveFieldInsertIndexFromCanvasTarget(
      firstHeaderId,
      fields,
      layoutRows,
      groups,
      sortableIds,
    );
    expect(insertAt).toBe(1);
  });

  it("sectionGroupInsertIndex targets the end of Bank Details", () => {
    expect(groups).toHaveLength(2);
    expect(sectionGroupInsertIndex(groups[1]!, fields.length)).toBe(4);
  });
});
