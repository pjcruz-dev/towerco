import { describe, expect, it } from "vitest";

import {
  parseSizeMatrixRows,
  parseSizeMatrixValue,
  resolveSizeMatrixDisplayLabels,
  setSizeMatrixRowValue,
  sizeMatrixHasCompleteAnswers,
  sizeMatrixRowInput,
} from "@/modules/e-approval/field-size-matrix";
import { defaultFieldForType } from "@/modules/e-approval/field-types";
import { validateSubmissionValues } from "@/modules/e-approval/field-validation";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

describe("field-size-matrix", () => {
  it("seeds catalog defaults including text rows", () => {
    const field = defaultFieldForType("size_matrix", 0);
    expect(field.type).toBe("size_matrix");
    const rows = parseSizeMatrixRows(field);
    expect(rows.map((r) => r.label)).toEqual([
      "Roofdeck",
      "Elevator Shaft",
      "Water Tank",
      "Wall",
      "Other (specify)",
      "Existing Utilities",
    ]);
    expect(sizeMatrixRowInput(rows[4]!)).toBe("text");
    expect(sizeMatrixRowInput(rows[5]!)).toBe("text");
  });

  it("stores sizes, NA, and free-text rows", () => {
    let value = setSizeMatrixRowValue("", "roofdeck", { w: "10", h: "12" });
    expect(parseSizeMatrixValue(value)).toEqual({ roofdeck: { w: "10", h: "12" } });
    value = setSizeMatrixRowValue(value, "elevator_shaft", { na: true });
    expect(parseSizeMatrixValue(value).elevator_shaft).toEqual({ na: true });
    value = setSizeMatrixRowValue(value, "other", { text: "Parapet extension" });
    expect(parseSizeMatrixValue(value).other).toEqual({ text: "Parapet extension" });
    expect(
      resolveSizeMatrixDisplayLabels(value, parseSizeMatrixRows(defaultFieldForType("size_matrix", 0))),
    ).toContain("Other (specify): Parapet extension");
  });

  it("requires size or NA for size rows only; text rows stay optional", () => {
    const field: EApprovalFormFieldInput = {
      ...defaultFieldForType("size_matrix", 0),
      name: "components",
      label: "Components",
      validation: { required: true },
    };
    const rows = parseSizeMatrixRows(field);
    expect(sizeMatrixHasCompleteAnswers('{"roofdeck":{"w":"10","h":"12"}}', rows)).toBe(false);
    expect(validateSubmissionValues([field], { components: '{"roofdeck":{"w":"10","h":"12"}}' })).toEqual([
      { fieldName: "components", message: "Components requires size or NA for every size row." },
    ]);

    const complete = JSON.stringify({
      roofdeck: { w: "10", h: "12" },
      elevator_shaft: { na: true },
      water_tank: { na: true },
      wall: { na: true },
    });
    expect(validateSubmissionValues([field], { components: complete })).toEqual([]);

    const withOptionalText = JSON.stringify({
      ...JSON.parse(complete),
      other: { text: "Generator pad" },
    });
    expect(validateSubmissionValues([field], { components: withOptionalText })).toEqual([]);
  });
});
