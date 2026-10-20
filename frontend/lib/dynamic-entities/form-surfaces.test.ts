import { describe, expect, it } from "vitest";

import { fieldsForSurface, groupsForSurface } from "@/lib/dynamic-entities/form-surfaces";

const groups = [
  { id: "details", name: "Identification", surface: "overview" },
  { id: "holders", name: "Holders", surface: "assignment" },
  { id: "legacy", name: "Notes" },
];

describe("form surfaces", () => {
  it("keeps a group with no surface on Details", () => {
    expect(groupsForSurface(groups, "overview").map((group) => group.id)).toEqual(["details", "legacy"]);
    expect(groupsForSurface(groups, "assignment").map((group) => group.id)).toEqual(["holders"]);
  });

  it("puts ungrouped fields on Details and grouped fields on their tab", () => {
    const fields = [
      { id: "serial", form_group_id: "details" },
      { id: "holder", form_group_id: "holders" },
      { id: "loose", form_group_id: null },
    ];
    expect(fieldsForSurface(fields, groups, "overview").map((field) => field.id)).toEqual(["serial", "loose"]);
    expect(fieldsForSurface(fields, groups, "assignment").map((field) => field.id)).toEqual(["holder"]);
  });
});
