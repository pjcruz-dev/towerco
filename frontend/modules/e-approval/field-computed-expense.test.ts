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
  it("sums Amount on legacy liquidation grids", () => {
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

  it("sums Total on ATC Expense lines of Liquidation grids", () => {
    const fields: EApprovalFormFieldInput[] = [
      {
        type: "grid",
        name: "expense_lines",
        label: "Expense lines of Liquidation",
        options: {
          columns: [
            { label: "Date", type: "date" },
            { label: "OR No", type: "text" },
            { label: "Supplier/Payee", type: "text" },
            { label: "Description", type: "textarea" },
            { label: "Project Site No.", type: "text" },
            { label: "Transportation - Land", type: "currency" },
            { label: "Transportation - Sea", type: "currency" },
            { label: "Transportation - Air", type: "currency" },
            { label: "Gasoline", type: "currency" },
            { label: "Lodging", type: "currency" },
            { label: "Per Diem", type: "currency" },
            { label: "VAT", type: "currency" },
            { label: "Total", type: "currency" },
          ],
        },
      },
      {
        type: "currency",
        name: "total_reimbursement",
        label: "Total liquidation amount",
        options: { read_only: true },
      },
      {
        type: "currency",
        name: "cash_advance_amount",
        label: "Cash advance",
      },
      {
        type: "currency",
        name: "cash_overage_shortage",
        label: "Cash overage (shortage)",
        options: { read_only: true },
      },
    ];

    const values = applyComputedFieldValues(fields, {
      expense_lines: JSON.stringify({
        rows: [
          {
            "0": "2026-08-01",
            "1": "OR-1",
            "2": "Taxi Co",
            "3": "Airport",
            "4": "SITE-1",
            "5": "100",
            "6": "0",
            "7": "0",
            "8": "0",
            "9": "0",
            "10": "0",
            "11": "12",
            "12": "112",
          },
        ],
      }),
      cash_advance_amount: "200",
      total_reimbursement: "",
      cash_overage_shortage: "",
    });

    expect(values.total_reimbursement).toBe("112.00");
    expect(values.cash_overage_shortage).toBe("88.00");
  });

  it("falls back to Total when Amount column is missing from config", () => {
    const fields: EApprovalFormFieldInput[] = [
      {
        type: "grid",
        name: "expense_lines",
        label: "Expense lines of Liquidation",
        options: {
          columns: [
            { label: "Transportation - Land", type: "currency" },
            { label: "VAT", type: "currency" },
            { label: "Total", type: "currency" },
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

    const values = applyComputedFieldValues(fields, {
      expense_lines: JSON.stringify({
        rows: [{ "0": "100", "1": "12", "2": "112" }],
      }),
      total_reimbursement: "",
    });

    expect(values.total_reimbursement).toBe("112.00");
  });
});
