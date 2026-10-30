import { describe, expect, it } from "vitest";

import {
  activityLinkChoices,
  applyExpenseVatSummary,
  applyPerDiemActivityDays,
  applyGridRowAmountFormula,
  expenseCellApplies,
  expenseColumnVisible,
  expenseGridKeepsTypedAmount,
  expenseLineMoney,
  expenseRowCapMessage,
  gridColumnFooterValue,
  inclusiveDays,
  isMoneyGridColumn,
  perDiemDaysBetweenTrips,
} from "@/modules/e-approval/grid-row-formulas";
import type { GridColumnDef } from "@/modules/e-approval/field-options";
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

describe("activityLinkChoices", () => {
  const activityField: EApprovalFormFieldInput = {
    type: "grid",
    name: "activity_lines",
    label: "Activity report",
    options: {
      columns: [
        { label: "From site", type: "select" },
        { label: "To site", type: "select" },
        { label: "Nature of activity", type: "textarea" },
      ],
    },
  };

  it("lists activity rows by nature and route", () => {
    const choices = activityLinkChoices(
      [activityField],
      {
        activity_lines: JSON.stringify({
          rows: [
            { "0": "", "1": "", "2": "" },
            { "0": "Makati", "1": "Cebu", "2": "Tower inspection" },
          ],
        }),
      },
      "activity_lines",
    );

    expect(choices).toEqual([
      { value: "2", label: "2 — Tower inspection — Makati → Cebu" },
    ]);
  });
});

const expenseColumns: GridColumnDef[] = [
  {
    label: "Expense type",
    type: "select",
    choices: [
      { value: "land", label: "Transportation - Land", data: { vatable: true } },
      { value: "per_diem", label: "Per Diem", data: { vatable: false, per_diem: true } },
    ],
  },
  { label: "Days", type: "number" },
  { label: "VAT treatment", type: "select" },
  { label: "VAT", type: "currency" },
];

describe("expense column visibility", () => {
  it("hides Days and VAT until an expense type is chosen", () => {
    const row = { "0": "", "1": "", "2": "", "3": "" };

    expect(expenseColumnVisible(expenseColumns, [row], expenseColumns[1])).toBe(false);
    expect(expenseColumnVisible(expenseColumns, [row], expenseColumns[2])).toBe(false);
    expect(expenseColumnVisible(expenseColumns, [row], expenseColumns[3])).toBe(false);
  });

  it("keeps Days off the grid and shows VAT for a VATable type", () => {
    const perDiem = { "0": "per_diem", "1": "2", "2": "non_vatable", "3": "0.00" };
    const land = { "0": "land", "1": "", "2": "vatable", "3": "12.00" };

    expect(expenseCellApplies(expenseColumns, perDiem, expenseColumns[1])).toBe(false);
    expect(expenseCellApplies(expenseColumns, perDiem, expenseColumns[3])).toBe(false);
    expect(expenseCellApplies(expenseColumns, land, expenseColumns[1])).toBe(false);
    expect(expenseColumnVisible(expenseColumns, [land], expenseColumns[1])).toBe(false);
    expect(expenseColumnVisible(expenseColumns, [land], expenseColumns[3])).toBe(true);
    expect(expenseColumnVisible(expenseColumns, [perDiem, land], expenseColumns[1])).toBe(false);
    expect(expenseColumnVisible(expenseColumns, [perDiem, land], expenseColumns[3])).toBe(true);
  });

  it("hides Quantity and Rate when Amount is the actual beside a forecast", () => {
    const columns: GridColumnDef[] = [
      { label: "Quantity", type: "number" },
      { label: "Rate", type: "currency" },
      { label: "Amount", type: "currency" },
      { label: "Forecast amount", type: "currency" },
    ];
    const row = { "0": "2", "1": "4000", "2": "5000", "3": "5000" };

    expect(expenseColumnVisible(columns, [row], columns[0])).toBe(false);
    expect(expenseColumnVisible(columns, [row], columns[1])).toBe(false);
    expect(expenseColumnVisible(columns, [row], columns[2])).toBe(true);
    expect(expenseLineMoney(columns, row)).toBe(5000);
  });

  it("hides Quantity and Rate on a reimbursement grid and keeps the typed amount", () => {
    const columns: GridColumnDef[] = [
      { label: "OR No", type: "text" },
      { label: "Expense type", type: "select" },
      { label: "Quantity", type: "number" },
      { label: "Rate", type: "currency" },
      { label: "Amount", type: "currency" },
    ];
    const row = { "0": "OR-1", "1": "land", "2": "2", "3": "4000", "4": "600" };

    expect(expenseColumnVisible(columns, [row], columns[2])).toBe(false);
    expect(expenseColumnVisible(columns, [row], columns[3])).toBe(false);
    expect(expenseColumnVisible(columns, [row], columns[4])).toBe(true);
    expect(expenseLineMoney(columns, row)).toBe(600);
  });

  it("hides a column the form builder turned off without shifting the others", () => {
    const columns: GridColumnDef[] = [
      { label: "From date – To date", type: "date_range" },
      { label: "Amount", type: "currency", hidden: true },
    ];

    expect(expenseColumnVisible(columns, [], columns[0]!)).toBe(true);
    expect(expenseColumnVisible(columns, [], columns[1]!)).toBe(false);
  });

  it("keeps a reimbursement per diem amount as typed when there is no rate column", () => {
    const columns: GridColumnDef[] = [
      { label: "From date – To date", type: "date_range" },
      { label: "OR No", type: "text" },
      { label: "Expense type", type: "select" },
      { label: "Amount", type: "currency" },
    ];
    const row = {
      "0": JSON.stringify({ from: "2026-10-16", to: "2026-10-19" }),
      "1": "OR-1",
      "2": "per_diem",
      "3": "350",
    };

    expect(expenseGridKeepsTypedAmount(columns)).toBe(true);
    expect(expenseLineMoney(columns, row)).toBe(350);
  });

  it("rejects a forecast per diem or lodging rate above the daily max", () => {
    const columns: GridColumnDef[] = [
      { label: "From date – To date", type: "date_range" },
      { label: "Expense type", type: "select" },
      { label: "Amount", type: "currency" },
    ];
    const dates = JSON.stringify({ from: "2026-11-02", to: "2026-11-04" });

    expect(
      expenseRowCapMessage(columns, { "0": dates, "1": "per_diem", "2": "400" }, 350, 2000),
    ).toBe("Per diem cannot exceed ₱350.00 per day.");
    expect(
      expenseRowCapMessage(columns, { "0": dates, "1": "lodging", "2": "2500" }, 350, 2000),
    ).toBe("Lodging cannot exceed ₱2,000.00 per day.");
    expect(expenseRowCapMessage(columns, { "0": dates, "1": "per_diem", "2": "350" }, 350, 2000)).toBeNull();
    expect(expenseRowCapMessage(columns, { "0": dates, "1": "lodging", "2": "2000" }, 350, 2000)).toBeNull();
  });
});

describe("forecast rate totals", () => {
  const rate: GridColumnDef = { label: "Rate", type: "currency" };
  const amount: GridColumnDef = { label: "Amount", type: "currency" };

  it("totals Rate when the line has no Amount column", () => {
    expect(isMoneyGridColumn(rate, [rate])).toBe(true);
  });

  it("leaves Rate out of the total when Amount is the line total", () => {
    expect(isMoneyGridColumn(rate, [rate, amount])).toBe(false);
    expect(isMoneyGridColumn(amount, [rate, amount])).toBe(true);
  });
});

describe("expense VAT summary", () => {
  const forecast: EApprovalFormFieldInput = {
    type: "grid",
    name: "expense_lines",
    label: "Forecast lines",
    options: {
      columns: [
        {
          label: "Expense type",
          type: "select",
          choices: [
            { value: "land", label: "Transportation - Land", data: { vatable: false } },
            { value: "sea", label: "Transportation - Sea", data: { vatable: true } },
            { value: "per_diem", label: "Per Diem", data: { vatable: false, per_diem: true } },
          ],
        },
        { label: "Amount", type: "currency" },
        { label: "VAT treatment", type: "select" },
        { label: "Days", type: "number" },
        { label: "VAT", type: "currency", locked: true },
        { label: "Date", type: "date_range" },
      ],
    },
  };
  const totals: EApprovalFormFieldInput[] = [
    { type: "currency", name: "requested_amount", label: "Requested amount" },
    { type: "currency", name: "vat_total", label: "VAT" },
    { type: "currency", name: "non_vatable_total", label: "Non-VATable" },
    { type: "currency", name: "vatable_net", label: "VATable, net of VAT" },
  ];

  it("splits inclusive 12% VAT into the summary totals", () => {
    const activity: EApprovalFormFieldInput = {
      type: "grid",
      name: "activity_lines",
      label: "Activity report",
      options: { columns: [{ label: "Date", type: "date_range" }] },
    };
    const values = applyExpenseVatSummary([activity, forecast, ...totals], {
      activity_lines: JSON.stringify({
        rows: [{ "0": JSON.stringify({ from: "2026-10-14", to: "2026-10-16" }) }],
      }),
      expense_lines: JSON.stringify({
        rows: [
          { "0": "land", "1": "500", "2": "non_vatable", "3": "", "4": "" },
          { "0": "sea", "1": "500", "2": "vatable", "3": "", "4": "" },
          { "0": "per_diem", "1": "3000", "2": "non_vatable", "3": "2", "4": "", "5": JSON.stringify({ from: "2026-10-14", to: "2026-10-14" }) },
        ],
      }),
    });

    const grid = JSON.parse(values.expense_lines ?? "{}") as { rows: Record<string, string>[] };
    expect(grid.rows[1]?.["4"]).toBe("53.57");
    expect(grid.rows[0]?.["4"]).toBe("0.00");
    expect(values.vat_total).toBe("53.57");
    expect(values.non_vatable_total).toBe("3500.00");
    expect(values.vatable_net).toBe("446.43");
    expect(values.requested_amount).toBeUndefined();
  });

  it("totals the rounded line VAT so the column adds up", () => {
    const values = applyExpenseVatSummary([forecast, ...totals], {
      expense_lines: JSON.stringify({
        rows: [
          { "0": "sea", "1": "1000", "2": "vatable", "3": "", "4": "" },
          { "0": "sea", "1": "500", "2": "vatable", "3": "", "4": "" },
          { "0": "sea", "1": "500", "2": "vatable", "3": "", "4": "" },
          { "0": "sea", "1": "500", "2": "vatable", "3": "", "4": "" },
        ],
      }),
    });

    expect(values.vat_total).toBe("267.85");
    expect(values.vatable_net).toBe("2232.15");
  });
});

describe("per diem days from activities", () => {
  it("counts each day on the line, including the first and the last", () => {
    expect(inclusiveDays("2026-10-14", "2026-10-14")).toBe(1);
    expect(inclusiveDays("2026-10-14", "2026-10-16")).toBe(3);
    expect(inclusiveDays("2026-10-14T08:30", "2026-10-16T17:00")).toBe(3);
    expect(inclusiveDays("2026-10-14", "2026-10-18")).toBe(5);
    expect(inclusiveDays("2026-10-16", "2026-10-14")).toBeNull();
  });

  it("counts the lodging span between the first date and the last date", () => {
    expect(perDiemDaysBetweenTrips([{ from: "2026-10-14", to: "2026-10-18" }])).toBe(3);
    expect(
      perDiemDaysBetweenTrips([
        { from: "2026-10-14", to: "2026-10-14" },
        { from: "2026-10-15", to: "2026-10-17" },
        { from: "2026-10-18", to: "2026-10-18" },
      ]),
    ).toBe(3);
    expect(
      perDiemDaysBetweenTrips([
        { from: "2026-10-14", to: "2026-10-15" },
        { from: "2026-10-15", to: "2026-10-16" },
        { from: "2026-10-16", to: "2026-10-17" },
      ]),
    ).toBe(2);
    expect(perDiemDaysBetweenTrips([{ from: "2026-10-14", to: "2026-10-14" }])).toBe(0);
    expect(perDiemDaysBetweenTrips([])).toBeNull();
  });

  it("multiplies a per diem rate by the activity days", () => {
    const fields: EApprovalFormFieldInput[] = [
      {
        type: "grid",
        name: "activity_lines",
        label: "Activity report",
        options: {
          columns: [{ label: "Date", type: "date_range" }],
        },
      },
      {
        type: "grid",
        name: "expense_lines",
        label: "Forecast lines",
        options: {
          columns: [
            { label: "Date", type: "date_range" },
            { label: "Expense type", type: "select" },
            { label: "Amount", type: "currency" },
            { label: "VAT treatment", type: "select" },
            { label: "VAT", type: "currency" },
          ],
        },
      },
      { type: "currency", name: "non_vatable_total", label: "Non-VATable" },
      { type: "currency", name: "vat_total", label: "VAT" },
      { type: "currency", name: "vatable_net", label: "VATable, net of VAT" },
    ];
    const range = (from: string, to: string) => JSON.stringify({ from, to });
    const values = applyExpenseVatSummary(fields, {
      expense_lines: JSON.stringify({
        rows: [
          { "0": range("2026-10-14", "2026-10-16"), "1": "per_diem", "2": "1200", "3": "non_vatable", "4": "" },
          { "0": range("2026-10-14", "2026-10-18"), "1": "lodging", "2": "500", "3": "non_vatable", "4": "" },
        ],
      }),
    });

    const grid = JSON.parse(values.expense_lines ?? "{}") as { rows: Record<string, string>[] };
    expect(grid.rows[0]?.["2"]).toBe("1200");
    expect(grid.rows[1]?.["2"]).toBe("500");
    // Per diem 14–16 Oct is 3 × 1200. Lodging 14–18 Oct is 4 nights × 500.
    expect(values.non_vatable_total).toBe("5600.00");
    expect(values.vat_total).toBe("0.00");
  });

  it("writes rate times days into Amount when the grid has a Rate column", () => {
    const fields: EApprovalFormFieldInput[] = [
      {
        type: "grid",
        name: "activity_lines",
        label: "Activity report",
        options: {
          columns: [
            { label: "Date", type: "date_range" },
            { label: "Nature of activity", type: "text" },
          ],
        },
      },
      {
        type: "grid",
        name: "expense_lines",
        label: "Forecast lines",
        options: {
          columns: [
            { label: "Date", type: "date_range" },
            { label: "Expense type", type: "select" },
            { label: "Rate", type: "currency" },
            { label: "Amount", type: "currency" },
            { label: "Days", type: "number" },
          ],
        },
      },
    ];
    const range = (from: string, to: string) => JSON.stringify({ from, to });
    const values = applyPerDiemActivityDays(fields, {
      expense_lines: JSON.stringify({
        rows: [
          { "0": range("2026-10-14", "2026-10-16"), "1": "per_diem", "2": "1000", "3": "1.00", "4": "9" },
          { "0": range("2026-10-14", "2026-10-18"), "1": "lodging", "2": "500", "3": "500.00", "4": "9" },
        ],
      }),
    });
    const grid = JSON.parse(values.expense_lines ?? "{}") as { rows: Record<string, string>[] };
    expect(grid.rows[0]?.["2"]).toBe("1000");
    expect(grid.rows[0]?.["3"]).toBe("3000.00");
    expect(grid.rows[0]?.["4"]).toBe("");
    expect(grid.rows[1]?.["3"]).toBe("2000.00");
    expect(grid.rows[1]?.["4"]).toBe("");
  });
});

describe("gridColumnFooterValue", () => {
  it("totals a forecast amount as the daily rate times the dates", () => {
    const columns: GridColumnDef[] = [
      { label: "From date – To date", type: "date_range" },
      {
        label: "Expense type",
        type: "select",
        choices: [
          { value: "per_diem", label: "Per Diem", data: { per_diem: true } },
          { value: "car_rental", label: "Car Rental", data: { vatable: true } },
        ],
      },
      { label: "Amount", type: "currency", show_total: true },
    ];
    const range = (from: string, to: string) => JSON.stringify({ from, to });
    const rows = [
      { "0": range("2026-11-16", "2026-11-17"), "1": "per_diem", "2": "350" },
      { "0": range("2026-11-16", "2026-11-16"), "1": "car_rental", "2": "3500" },
    ];

    expect(gridColumnFooterValue(columns, rows, 2)).toBe(4200);
  });
});
