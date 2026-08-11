import { describe, expect, it } from "vitest";

import { buildFieldDisplayGroups } from "@/modules/e-approval/form-field-groups";
import {
  buildBuilderFieldSearchIndex,
  filterBuilderFieldSearch,
} from "@/modules/e-approval/builder-field-search";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(name: string, type: string, label?: string): EApprovalFormFieldInput {
  return { name, type, label: label ?? name, step_order: 1 };
}

describe("builder field search", () => {
  it("indexes fields with labels, names, and types", () => {
    const fields = [
      field("section_a", "section", "Header"),
      field("purchase_amount", "currency", "Purchase amount"),
      field("notes", "textarea", "Notes"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const index = buildBuilderFieldSearchIndex(fields, groups);

    expect(index.map((entry) => entry.name)).toEqual(["section_a", "purchase_amount", "notes"]);
    expect(index[1]?.typeLabel).toBe("Currency");
  });

  it("filters by label, api key, and type tokens", () => {
    const fields = [
      field("vendor_name", "text", "Vendor name"),
      field("vendor_email", "email", "Vendor email"),
      field("total_cost", "currency", "Total cost"),
    ];
    const groups = buildFieldDisplayGroups(fields);
    const index = buildBuilderFieldSearchIndex(fields, groups);

    expect(filterBuilderFieldSearch(index, "vendor").map((entry) => entry.name)).toEqual([
      "vendor_name",
      "vendor_email",
    ]);
    expect(filterBuilderFieldSearch(index, "currency")[0]?.name).toBe("total_cost");
    expect(filterBuilderFieldSearch(index, "vendor email")[0]?.name).toBe("vendor_email");
  });
});
