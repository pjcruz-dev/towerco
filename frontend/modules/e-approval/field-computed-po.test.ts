import { describe, expect, it } from "vitest";

import { applyComputedFieldValues } from "@/modules/e-approval/field-computed";
import { applyGridRowFormulasToValues } from "@/modules/e-approval/grid-row-formulas";
import { buildPurchaseOrderTemplateFields } from "@/modules/e-approval/purchase-order-template";

describe("purchase order computed fields", () => {
  it("chains vatable, vat, total, and grand total", () => {
    const { fields } = buildPurchaseOrderTemplateFields(1, new Set());

    let values: Record<string, string> = {
      line_items: JSON.stringify({
        rows: [
          { "3": "10", "4": "100", "5": "50" },
          { "3": "5", "4": "200", "5": "0" },
        ],
      }),
      vat_exempt_amount: "100.00",
      zero_rated_amount: "0.00",
      vat_rate: "12",
      less_discount: "200.00",
    };

    values = applyGridRowFormulasToValues(fields, values);
    values = applyComputedFieldValues(fields, values);

    expect(values.vatable_amount).toBe("1950.00");
    expect(values.vat_amount).toBe("234.00");
    expect(values.total_vat_inclusive).toBe("2284.00");
    expect(values.grand_total).toBe("2084.00");
  });
});
