import { describe, expect, it } from "vitest";

import { applyGridRowAmountFormula } from "@/modules/e-approval/grid-row-formulas";
import { PO_GRID_COLUMNS, PO_GRID_FIELD_NAME } from "@/modules/e-approval/purchase-order-template";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

const poGridField: EApprovalFormFieldInput = {
  type: "grid",
  name: PO_GRID_FIELD_NAME,
  label: "Line items",
  step_order: 1,
  options: { columns: PO_GRID_COLUMNS },
};

describe("applyGridRowAmountFormula", () => {
  it("computes amount as qty × unit price − discount", () => {
    const raw = JSON.stringify({
      rows: [{ "0": "A", "3": "10", "4": "100", "5": "50", "6": "0" }],
    });

    const patched = applyGridRowAmountFormula(poGridField, raw);
    const parsed = JSON.parse(patched) as { rows: Record<string, string>[] };

    expect(parsed.rows[0]["6"]).toBe("950.00");
  });
});
