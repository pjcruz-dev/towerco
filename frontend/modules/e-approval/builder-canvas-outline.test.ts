import { describe, expect, it } from "vitest";

import {
  buildBuilderCanvasOutline,
  builderCanvasSectionAnchorId,
  shouldShowBuilderCanvasOutline,
} from "@/modules/e-approval/builder-canvas-outline";
import { buildFieldDisplayGroups } from "@/modules/e-approval/form-field-groups";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(name: string, type: string, label?: string): EApprovalFormFieldInput {
  return { name, type, label: label ?? name, step_order: 1 };
}

describe("builder canvas outline", () => {
  it("builds outline entries from display groups", () => {
    const fields = [
      field("section_a", "section", "Requisition details"),
      field("title", "text"),
      field("amount", "currency"),
      field("section_b", "section", "Line items"),
      field("grid", "grid"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const outline = buildBuilderCanvasOutline(groups);

    expect(outline).toHaveLength(2);
    expect(outline[0]?.label).toBe("Requisition details");
    expect(outline[0]?.fieldCount).toBe(2);
    expect(outline[0]?.anchorId).toBe(builderCanvasSectionAnchorId(0));
    expect(outline[1]?.label).toBe("Line items");
    expect(outline[1]?.fieldCount).toBe(1);
  });

  it("prefixes outline labels with step numbers in stepped mode", () => {
    const fields = [
      field("section_a", "section", "Header"),
      field("title", "text"),
      field("section_b", "section", "Details"),
      field("amount", "currency"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const outline = buildBuilderCanvasOutline(groups, { stepped: true });

    expect(outline[0]?.label).toBe("Step 1 · Header");
    expect(outline[0]?.stepIndex).toBe(1);
    expect(outline[1]?.label).toBe("Step 2 · Details");
  });

  it("shows outline for long forms and multi-section forms", () => {
    const short = buildFieldDisplayGroups([field("a", "text"), field("b", "text")]);
    expect(shouldShowBuilderCanvasOutline(short)).toBe(false);

    const many = buildFieldDisplayGroups(
      Array.from({ length: 8 }, (_, index) => field(`f_${index}`, "text")),
    );
    expect(shouldShowBuilderCanvasOutline(many)).toBe(true);

    const sections = buildFieldDisplayGroups([
      field("section_a", "section", "A"),
      field("a", "text"),
      field("section_b", "section", "B"),
      field("b", "text"),
    ]);
    expect(shouldShowBuilderCanvasOutline(sections)).toBe(true);
  });
});
