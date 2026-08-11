import { describe, expect, it } from "vitest";

import { isProcurementLinkField } from "@/modules/e-approval/procurement-link-fields";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(partial: Partial<EApprovalFormFieldInput> & Pick<EApprovalFormFieldInput, "name" | "type">): EApprovalFormFieldInput {
  return {
    label: partial.label ?? partial.name,
    ...partial,
  };
}

describe("isProcurementLinkField", () => {
  it("treats bare site_id as a live sites picker", () => {
    expect(isProcurementLinkField(field({ name: "site_id", type: "select" }))).toBe(true);
  });

  it("does not hijack site_id when master data is configured", () => {
    expect(
      isProcurementLinkField(
        field({
          name: "site_id",
          type: "select",
          options: { master_data_key: "siteid", choices: [] },
        }),
      ),
    ).toBe(false);
  });

  it("does not hijack site_id when static choices are present", () => {
    expect(
      isProcurementLinkField(
        field({
          name: "site_id",
          type: "select",
          options: {
            choices: [{ value: "SITE-001", label: "SITE-001" }],
          },
        }),
      ),
    ).toBe(false);
  });

  it("ignores unrelated field names", () => {
    expect(isProcurementLinkField(field({ name: "affiliation", type: "radio" }))).toBe(false);
  });
});
