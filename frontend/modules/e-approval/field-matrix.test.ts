import { describe, expect, it } from "vitest";

import {
  matrixHasCompleteAnswers,
  parseMatrixFieldOptions,
  parseMatrixState,
  parseMatrixValue,
  resolveMatrixDisplayLabels,
  serializeMatrixValue,
  setMatrixCellValue,
  setMatrixNoteValue,
} from "@/modules/e-approval/field-matrix";
import { defaultFieldForType } from "@/modules/e-approval/field-types";
import { validateSubmissionValues } from "@/modules/e-approval/field-validation";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

describe("field-matrix helpers", () => {
  it("seeds catalog matrix with Yes/No rows", () => {
    const field = defaultFieldForType("matrix", 0);
    expect(field.type).toBe("matrix");
    expect(parseMatrixFieldOptions(field)).toMatchObject({
      rows: [
        { value: "a", label: "A. Cut and Fill" },
        { value: "b", label: "B. Slope Protection" },
      ],
      columns: [
        { value: "yes", label: "Yes" },
        { value: "no", label: "No" },
      ],
    });
  });

  it("stores per-row answers as JSON", () => {
    const next = setMatrixCellValue("", "a", "yes");
    expect(parseMatrixValue(next)).toEqual({ a: "yes" });
    expect(setMatrixCellValue(next, "b", "no")).toBe(JSON.stringify({ a: "yes", b: "no" }));
    expect(serializeMatrixValue({ a: "yes", b: "" })).toBe(JSON.stringify({ a: "yes" }));
  });

  it("stores optional per-row notes without breaking legacy answers", () => {
    let value = setMatrixCellValue("", "a", "yes");
    value = setMatrixNoteValue(value, "a", "5 m Approx.");
    expect(parseMatrixState(value)).toEqual({
      a: { value: "yes", note: "5 m Approx." },
    });
    expect(JSON.parse(value)).toEqual({
      answers: { a: "yes" },
      notes: { a: "5 m Approx." },
    });
    expect(
      resolveMatrixDisplayLabels(value, parseMatrixFieldOptions(defaultFieldForType("matrix", 0))),
    ).toContain("(5 m Approx.)");
  });

  it("requires every row when validating", () => {
    const field: EApprovalFormFieldInput = {
      ...defaultFieldForType("matrix", 0),
      name: "c2",
      label: "C2",
      validation: { required: true },
    };
    const options = parseMatrixFieldOptions(field);

    expect(matrixHasCompleteAnswers('{"a":"yes"}', options)).toBe(false);
    expect(validateSubmissionValues([field], { c2: '{"a":"yes"}' })).toEqual([
      { fieldName: "c2", message: "C2 requires an answer for every row." },
    ]);

    const complete = JSON.stringify({ a: "yes", b: "no" });
    expect(matrixHasCompleteAnswers(complete, options)).toBe(true);
    expect(validateSubmissionValues([field], { c2: complete })).toEqual([]);
    expect(resolveMatrixDisplayLabels(complete, options)).toBe(
      "A. Cut and Fill: Yes; B. Slope Protection: No",
    );
  });
});
