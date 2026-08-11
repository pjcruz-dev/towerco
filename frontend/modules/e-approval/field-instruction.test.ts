import { describe, expect, it } from "vitest";

import {
  companionSizeIsComplete,
  formatCompanionSizeDisplay,
  parseCompanionSizeValue,
  serializeCompanionSizeValue,
} from "@/modules/e-approval/field-companion-size";
import {
  formatInstructionBodyForDisplay,
  parseInstructionBody,
} from "@/modules/e-approval/field-instruction";
import { defaultFieldForType } from "@/modules/e-approval/field-types";
import { isComposeStructuralFieldType } from "@/modules/e-approval/form-compose-structural";
import { validateSubmissionValues } from "@/modules/e-approval/field-validation";

describe("instruction field", () => {
  it("is structural and skipped in submission validation", () => {
    const field = defaultFieldForType("instruction", 0);
    expect(field.type).toBe("instruction");
    expect(isComposeStructuralFieldType("instruction")).toBe(true);
    expect(parseInstructionBody(field).length).toBeGreaterThan(0);
    expect(validateSubmissionValues([field], {})).toEqual([]);
  });

  it("collapses soft-wrapped paste lines so text can fill full width", () => {
    const pasted = [
      "a. Check actual condition of the building particularly at deck",
      "area, floor slab, existing walls columns or beams.",
      "b. Ask the lessor if As-Built Plans of the Bldg. is available",
      "(get photocopy) if not, get the As-found plan.",
    ].join("\n");

    expect(formatInstructionBodyForDisplay(pasted)).toBe(
      [
        "a. Check actual condition of the building particularly at deck area, floor slab, existing walls columns or beams.",
        "b. Ask the lessor if As-Built Plans of the Bldg. is available (get photocopy) if not, get the As-found plan.",
      ].join("\n\n"),
    );
  });
});

describe("companion size helpers", () => {
  it("serializes W×H and NA", () => {
    expect(serializeCompanionSizeValue({ w: "10", h: "12" })).toBe(JSON.stringify({ w: "10", h: "12" }));
    expect(parseCompanionSizeValue(JSON.stringify({ na: true }))).toEqual({ na: true });
    expect(companionSizeIsComplete(JSON.stringify({ w: "10", h: "12" }))).toBe(true);
    expect(formatCompanionSizeDisplay(JSON.stringify({ w: "10", h: "12" }))).toBe("10 × 12");
  });
});
