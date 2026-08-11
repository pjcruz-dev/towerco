import { describe, expect, it } from "vitest";

import { buildFieldDisplayGroups } from "@/modules/e-approval/form-field-groups";
import {
  isLargeBuilderForm,
  shouldForceBuilderCanvasOutline,
  shouldShowBuilderFieldSearch,
} from "@/modules/e-approval/builder-canvas-performance";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(name: string): EApprovalFormFieldInput {
  return { name, type: "text", label: name, step_order: 1 };
}

describe("builder canvas performance", () => {
  it("enables field search at 50+ fillable fields", () => {
    const small = Array.from({ length: 49 }, (_, index) => field(`f_${index}`));
    const large = Array.from({ length: 50 }, (_, index) => field(`f_${index}`));

    expect(shouldShowBuilderFieldSearch(small)).toBe(false);
    expect(shouldShowBuilderFieldSearch(large)).toBe(true);
  });

  it("marks large forms at 100+ fillable fields and forces outline", () => {
    const fields = Array.from({ length: 100 }, (_, index) => field(`f_${index}`));
    const groups = buildFieldDisplayGroups(fields);

    expect(isLargeBuilderForm(fields)).toBe(true);
    expect(shouldForceBuilderCanvasOutline(fields, groups)).toBe(true);
  });
});
