import { describe, expect, it } from "vitest";

import {
  isCheckboxMulti,
  isCheckboxTruthy,
  parseCheckboxState,
  parseCheckboxValues,
  serializeCheckboxState,
  serializeCheckboxValues,
  setCheckboxCompanionValue,
  toggleCheckboxValue,
  validateCheckboxCompanions,
} from "@/modules/e-approval/field-checkbox";
import { defaultFieldForType } from "@/modules/e-approval/field-types";
import { parseSelectChoices } from "@/modules/e-approval/field-options";
import { validateSubmissionValues } from "@/modules/e-approval/field-validation";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function makeCheckbox(overrides: Partial<EApprovalFormFieldInput> = {}): EApprovalFormFieldInput {
  return {
    type: "checkbox",
    name: "ack",
    label: "Acknowledgment",
    step_order: 1,
    ...overrides,
  };
}

describe("field-checkbox helpers", () => {
  it("treats empty options as boolean mode and choices as multi", () => {
    expect(isCheckboxMulti(makeCheckbox())).toBe(false);
    expect(isCheckboxMulti(makeCheckbox({ options: { choices: [{ value: "a", label: "A" }] } }))).toBe(true);
    expect(isCheckboxMulti(makeCheckbox({ options: { master_data_key: "sites" } }))).toBe(true);
  });

  it("parses and serializes comma-separated values", () => {
    expect(parseCheckboxValues("a, b, a")).toEqual(["a", "b"]);
    expect(serializeCheckboxValues(["a", "", "b", "a"])).toBe("a,b");
    expect(toggleCheckboxValue("a", "b", true)).toBe("a,b");
    expect(toggleCheckboxValue("a,b", "a", false)).toBe("b");
  });

  it("seeds catalog checkbox with choices", () => {
    const field = defaultFieldForType("checkbox", 0);
    expect(isCheckboxMulti(field)).toBe(true);
    expect(field.options).toMatchObject({
      choices: [
        { value: "a", label: "Option A" },
        { value: "b", label: "Option B" },
      ],
    });
  });

  it("upgrades to JSON when companion values are set", () => {
    const next = setCheckboxCompanionValue("self_supporting", "self_supporting", "height_agl", "15");
    expect(parseCheckboxState(next)).toEqual({
      selected: ["self_supporting"],
      companions: { self_supporting: { height_agl: "15" } },
    });
    expect(JSON.parse(next)).toMatchObject({
      selected: ["self_supporting"],
      companions: { self_supporting: { height_agl: "15" } },
    });
  });

  it("clears companions when an option is unchecked", () => {
    const withCompanion = serializeCheckboxState({
      selected: ["self_supporting", "tower"],
      companions: { self_supporting: { height_agl: "15" } },
    });
    expect(toggleCheckboxValue(withCompanion, "self_supporting", false)).toBe("tower");
  });

  it("parses companion inputs from choice options", () => {
    const choices = parseSelectChoices({
      type: "checkbox",
      name: "structure",
      label: "Structure",
      options: {
        choices: [
          {
            value: "self_supporting",
            label: "Self Supporting Tower or Pole",
            inputs: [{ key: "height_agl", type: "number", suffix: "m.(AGL)", required: true }],
          },
        ],
      },
    });
    expect(choices[0]?.inputs).toEqual([
      { key: "height_agl", type: "number", suffix: "m.(AGL)", required: true },
    ]);
  });
});

describe("checkbox submission validation", () => {
  it("requires boolean checkbox to be checked", () => {
    const field = makeCheckbox({ validation: { required: true } });
    expect(validateSubmissionValues([field], { ack: "false" })).toEqual([
      { fieldName: "ack", message: "Acknowledgment is required." },
    ]);
    expect(validateSubmissionValues([field], { ack: "true" })).toEqual([]);
    expect(isCheckboxTruthy("1")).toBe(true);
  });

  it("requires multi checkbox to have at least one option", () => {
    const field = makeCheckbox({
      validation: { required: true },
      options: { choices: [{ value: "a", label: "A" }, { value: "b", label: "B" }] },
    });
    expect(validateSubmissionValues([field], { ack: "" })).toEqual([
      { fieldName: "ack", message: "Acknowledgment is required." },
    ]);
    expect(validateSubmissionValues([field], { ack: "a,b" })).toEqual([]);
  });

  it("rejects multi values outside static choices", () => {
    const field = makeCheckbox({
      options: { choices: [{ value: "a", label: "A" }] },
    });
    expect(validateSubmissionValues([field], { ack: "z" })).toEqual([
      { fieldName: "ack", message: "Acknowledgment contains an invalid option." },
    ]);
  });

  it("requires companion values when option is checked", () => {
    const field = makeCheckbox({
      label: "Type and Height",
      name: "structure",
      options: {
        choices: [
          {
            value: "self_supporting",
            label: "Self Supporting Tower or Pole",
            inputs: [{ key: "height_agl", type: "number", suffix: "m.(AGL)", required: true }],
          },
        ],
      },
    });

    expect(validateSubmissionValues([field], { structure: "self_supporting" })).toEqual([
      {
        fieldName: "structure",
        message: "Type and Height: enter a value for Self Supporting Tower or Pole (m.(AGL)).",
      },
    ]);

    const filled = setCheckboxCompanionValue(
      "self_supporting",
      "self_supporting",
      "height_agl",
      "15",
    );
    expect(validateSubmissionValues([field], { structure: filled })).toEqual([]);
    expect(
      validateCheckboxCompanions(filled, parseSelectChoices(field), "Type and Height"),
    ).toBeNull();
  });

  it("validates size companions with W×H or NA", () => {
    const field = makeCheckbox({
      label: "Cabin",
      name: "cabin",
      options: {
        choices: [
          {
            value: "dry_wall",
            label: "Dry Wall Type Panel",
            inputs: [{ key: "size", type: "size", required: true }],
          },
        ],
      },
    });

    expect(validateSubmissionValues([field], { cabin: "dry_wall" })).toEqual([
      {
        fieldName: "cabin",
        message: "Cabin: enter size or NA for Dry Wall Type Panel.",
      },
    ]);

    const sized = setCheckboxCompanionValue(
      "dry_wall",
      "dry_wall",
      "size",
      JSON.stringify({ w: "10", h: "12" }),
    );
    expect(validateSubmissionValues([field], { cabin: sized })).toEqual([]);
  });
});
