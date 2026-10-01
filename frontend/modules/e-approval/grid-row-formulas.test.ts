import { describe, expect, it } from "vitest";

import { applyGridRowAmountFormula } from "@/modules/e-approval/grid-row-formulas";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

const lineItemsGridField: EApprovalFormFieldInput = {
  type: "grid",
  name: "line_items",
  label: "Line items",
  step_order: 1,
  options: {
    columns: [
      { label: "Description", type: "text" },
      { label: "SKU", type: "text" },
      { label: "Unit", type: "text" },
      { label: "Qty", type: "number" },
      { label: "Unit price", type: "currency" },
      { label: "Discount", type: "currency" },
      { label: "Amount", type: "currency" },
    ],
  },
};

describe("applyGridRowAmountFormula", () => {
  it("computes amount as qty × unit price − discount", () => {
    const raw = JSON.stringify({
      rows: [{ "0": "A", "3": "10", "4": "100", "5": "50", "6": "0" }],
    });

    const patched = applyGridRowAmountFormula(lineItemsGridField, raw);
    const parsed = JSON.parse(patched) as { rows: Record<string, string>[] };

    expect(parsed.rows[0]["6"]).toBe("950.00");
  });
});
