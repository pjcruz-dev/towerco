import { describe, expect, it } from "vitest";

import { applyComputedFieldValues } from "./field-computed";
import type { EApprovalFormFieldInput } from "./types";

const expenseFields: EApprovalFormFieldInput[] = [
  {
    type: "grid",
    name: "expense_lines",
    label: "Expense lines",
    options: {
      columns: [
        { label: "Date", type: "date" },
        { label: "Category", type: "text" },
        { label: "Description", type: "text" },
        { label: "Amount", type: "currency" },
      ],
    },
  },
  {
    type: "currency",
    name: "total_reimbursement",
    label: "Total liquidation amount",
    options: {
      read_only: true,
      computed_from: {
        operation: "sum_grid_column",
        source_field: "expense_lines",
        column: "Amount",
      },
    },
  },
];

describe("expense line computed totals", () => {
  it("sums Amount on liquidation and reimbursement grids", () => {
    const values = applyComputedFieldValues(expenseFields, {
      expense_lines: JSON.stringify({
        rows: [
          { "0": "2026-08-01", "1": "Travel", "2": "Taxi", "3": "123213" },
          { "0": "2026-08-02", "1": "Travel", "2": "Meal", "3": "123213" },
          { "0": "2026-08-03", "1": "Travel", "2": "Hotel", "3": "123213" },
        ],
      }),
      total_reimbursement: "",
    });

    expect(values.total_reimbursement).toBe("369639.00");
  });
});
