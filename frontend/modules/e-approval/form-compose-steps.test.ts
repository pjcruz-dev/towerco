import { describe, expect, it } from "vitest";

import {
  buildDisplayGroupsForComposeStep,
  buildFormComposeSteps,
} from "@/modules/e-approval/form-compose-steps";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function makeField(name: string, type: string, label = name): EApprovalFormFieldInput {
  return { name, type, label, step_order: 1, options: {} };
}

describe("buildDisplayGroupsForComposeStep", () => {
  it("keeps the section header visible for a stepped section", () => {
    const fields = [
      makeField("section_a", "section", "A1. General"),
      makeField("a1_name", "text", "Name"),
      makeField("section_b", "section", "A2. Vicinity Map - New sites"),
      makeField("site_name", "text", "Site Name/ID"),
      makeField("longitude", "text", "Longitude"),
    ];

    const steps = buildFormComposeSteps(fields, "sections");
    expect(steps).toHaveLength(2);

    const groups = buildDisplayGroupsForComposeStep(steps[1]!, fields);
    expect(groups).toHaveLength(1);
    expect(groups[0]?.header?.field.label).toBe("A2. Vicinity Map - New sites");
    expect(groups[0]?.items.map((item) => item.field.name)).toEqual(["site_name", "longitude"]);
  });

  it("shows section headings on page-break steps instead of hiding them", () => {
    const fields = [
      makeField("intro", "text", "Intro"),
      makeField("break_1", "page_break", "Step 2"),
      makeField("section_b", "section", "B. Site Survey"),
      makeField("checkbox_17", "checkbox", "Checkbox 17"),
    ];

    const steps = buildFormComposeSteps(fields, "page_breaks");
    expect(steps).toHaveLength(2);

    const groups = buildDisplayGroupsForComposeStep(steps[1]!, fields);
    expect(groups).toHaveLength(1);
    expect(groups[0]?.header?.field.label).toBe("B. Site Survey");
    expect(groups[0]?.items.map((item) => item.field.name)).toEqual(["checkbox_17"]);
  });

  it("keeps mid-step section headings visible as canvas fields", () => {
    const fields = [
      makeField("intro", "text", "Intro"),
      makeField("break_1", "page_break", "Step 2"),
      makeField("checkbox_17", "checkbox", "Checkbox 17"),
      makeField("section_b", "section", "B. Site Survey"),
      makeField("notes", "text", "Notes"),
    ];

    const steps = buildFormComposeSteps(fields, "page_breaks");
    const groups = buildDisplayGroupsForComposeStep(steps[1]!, fields);

    expect(groups[0]?.header).toBeNull();
    expect(groups[0]?.items.map((item) => item.field.name)).toEqual([
      "checkbox_17",
      "section_b",
      "notes",
    ]);
  });

  it("hides empty section-only steps for requestor compose by default", () => {
    const fields = [
      makeField("section_a", "section", "A1"),
      makeField("name", "text", "Name"),
      makeField("section_b", "section", "B"),
      makeField("notes", "text", "Notes"),
      makeField("section_c", "section", "C empty"),
    ];

    expect(buildFormComposeSteps(fields, "sections")).toHaveLength(2);
  });

  it("keeps empty section-only steps for the visual builder", () => {
    const fields = [
      makeField("section_a", "section", "A1"),
      makeField("name", "text", "Name"),
      makeField("section_b", "section", "B"),
      makeField("notes", "text", "Notes"),
      makeField("section_c", "section", "C empty"),
    ];

    const steps = buildFormComposeSteps(fields, "sections", { includeEmptySteps: true });
    expect(steps).toHaveLength(3);
    expect(steps[2]?.label).toBe("C empty");
    expect(steps[2]?.fieldIndices).toEqual([4]);

    const groups = buildDisplayGroupsForComposeStep(steps[2]!, fields);
    expect(groups).toHaveLength(1);
    expect(groups[0]?.header?.field.name).toBe("section_c");
    expect(groups[0]?.items).toEqual([]);
  });
});
