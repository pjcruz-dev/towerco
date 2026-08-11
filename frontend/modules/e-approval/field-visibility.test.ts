import { describe, expect, it } from "vitest";

import { mergeFieldOptions } from "@/modules/e-approval/field-options";
import { isFieldVisible, parseFieldVisibility, patchFieldVisibility } from "@/modules/e-approval/field-visibility";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function fieldWithOptions(options: Record<string, unknown>): EApprovalFormFieldInput {
  return {
    label: "Attachment",
    name: "file_upload_1",
    type: "file",
    step_order: 1,
    options,
  };
}

describe("field visibility", () => {
  it("removes visibility when disabled and merged into field options", () => {
    const field = fieldWithOptions({
      layout: { width: "full" },
      visibility: {
        mode: "show_when",
        field: "title",
        operator: "equals",
        value: "yes",
      },
    });

    const patch = patchFieldVisibility(field, null);
    const merged = mergeFieldOptions(field, patch);

    expect(parseFieldVisibility({ ...field, options: merged })).toBeNull();
    expect(merged.layout).toEqual({ width: "full" });
    expect("visibility" in merged).toBe(false);
  });

  it("ignores equals rules without a comparison value", () => {
    const field = fieldWithOptions({
      visibility: {
        mode: "show_when",
        field: "title",
        operator: "equals",
      },
    });

    expect(parseFieldVisibility(field)).toBeNull();
    expect(isFieldVisible(field, { title: "TEST" })).toBe(true);
  });
});
