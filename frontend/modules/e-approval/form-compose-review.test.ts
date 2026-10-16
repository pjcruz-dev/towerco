import { describe, expect, it } from "vitest";

import {
  appendComposeReviewStep,
  buildChecklistMatrixReviewTable,
  buildComposeReviewSummaryRows,
  buildGridReviewTable,
  formatComposeReviewValue,
  placeReviewTotals,
} from "@/modules/e-approval/form-compose-review";
import { buildFormComposeSteps } from "@/modules/e-approval/form-compose-steps";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function makeField(
  name: string,
  type: string,
  label = name,
  options: Record<string, unknown> = {},
): EApprovalFormFieldInput {
  return { name, type, label, step_order: 1, options };
}

describe("form compose review", () => {
  it("appends a review step after content steps", () => {
    const fields = [
      makeField("section_a", "section", "Payee & payment details"),
      makeField("payee", "text", "Payee"),
      makeField("section_b", "section", "Bank & cost charge"),
      makeField("bank_name", "text", "Name of bank"),
    ];
    const steps = buildFormComposeSteps(fields, "sections");
    const withReview = appendComposeReviewStep(steps);

    expect(withReview).toHaveLength(3);
    expect(withReview[2]?.label).toBe("Review & submit");
    expect(withReview[2]?.fields).toEqual([]);
  });

  it("highlights payee amount bank and cost in summary order", () => {
    const fields = [
      makeField("location", "text", "Location"),
      makeField("payee", "text", "Payee"),
      makeField("payment_amount", "currency", "Payment amount"),
      makeField("bank_name", "text", "Name of bank"),
      makeField("cost_application", "checklist_matrix", "Cost application"),
    ];
    const rows = buildComposeReviewSummaryRows(fields, {
      payee: "Alliance Towers",
      payment_amount: "15000",
      bank_name: "BDO",
      cost_application: JSON.stringify({
        saq_site_survey: {
          selected: true,
          cells: { project_site_no: "SITE-1", ref_no: "R-1", or_no: "OR-1" },
        },
      }),
      location: "Manila",
    });

    expect(rows.slice(0, 4).map((row) => row.fieldName)).toEqual([
      "payee",
      "payment_amount",
      "bank_name",
      "cost_application",
    ]);
    const cost = rows.find((row) => row.fieldName === "cost_application");
    expect(cost?.table?.rows).toEqual([["SAQ-Site Survey", "SITE-1", "R-1", "OR-1"]]);
  });

  it("builds a checklist matrix review table from selected rows", () => {
    const field = makeField("cost_application", "checklist_matrix", "Cost application");
    const table = buildChecklistMatrixReviewTable(
      field,
      JSON.stringify({
        cme_labor: {
          selected: true,
          cells: { project_site_no: "A", ref_no: "B", or_no: "C" },
        },
        logistics: { selected: false, cells: {} },
      }),
    );

    expect(table?.headers[0]).toBe("Cost Application");
    expect(table?.rows).toEqual([["CME-Labor", "A", "B", "C"]]);
  });

  it("formats file selections for review", () => {
    const field = makeField("service_invoice", "file", "Service invoice");
    const value = formatComposeReviewValue(field, "", {
      service_invoice: [new File(["x"], "invoice.pdf", { type: "application/pdf" })],
    });
    expect(value).toBe("1 file selected");
  });

  it("shows site stops, choice labels, and grouped amounts in a grid review table", () => {
    const field = makeField("expense_lines", "grid", "Forecast lines", {
      columns: [
        { label: "From site / location", type: "select", master_data_key: "sites" },
        {
          label: "Expense type",
          type: "select",
          choices: [
            { value: "sea", label: "Transportation - Sea" },
            { value: "gasoline", label: "Gasoline" },
          ],
        },
        { label: "Amount", type: "currency" },
        {
          label: "VAT treatment",
          type: "select",
          choices: [{ value: "vatable", label: "VATable" }],
        },
        { label: "VAT", type: "currency" },
      ],
    });

    const rows = buildComposeReviewSummaryRows([field], {
      expense_lines: JSON.stringify({
        rows: [
          {
            "0": JSON.stringify({
              site: "ATC-VIS-0773",
              location: "LGU BrgyUbojan Sagbayan(Borja)",
            }),
            "1": "sea",
            "2": "1333",
            "3": "vatable",
            "4": "142.82",
          },
        ],
      }),
    });

    expect(rows[0]?.table?.rows[0]).toEqual([
      "ATC-VIS-0773 — LGU BrgyUbojan Sagbayan(Borja)",
      "Transportation - Sea",
      "1,333",
      "VATable",
      "142.82",
      "0.00",
      "1,190.18",
    ]);
  });

  it("shows extra site columns under the site id", () => {
    const field = makeField("expense_lines", "grid", "Forecast lines", {
      columns: [{ label: "From site / location", type: "select", master_data_key: "sites" }],
    });
    const rows = buildComposeReviewSummaryRows([field], {
      expense_lines: JSON.stringify({
        rows: [
          {
            "0": JSON.stringify({
              site: "ATC-VIS-0773",
              location: "LGU Brgy Ubojan",
              fields: { name: "Ubojan" },
            }),
          },
        ],
      }),
    });

    expect(rows[0]?.table?.rows[0]).toEqual(["ATC-VIS-0773\nLGU Brgy Ubojan\nUbojan"]);
  });

  it("hides the clock on a date range column when include time is off", () => {
    const field = makeField("expense_lines", "grid", "Forecast lines", {
      columns: [{ label: "From date – To date", type: "date_range", include_time: false }],
    });
    const rows = buildComposeReviewSummaryRows([field], {
      expense_lines: JSON.stringify({
        rows: [{ "0": '{"from":"2026-10-13T00:00","to":"2026-10-14T00:00"}' }],
      }),
    });

    expect(rows[0]?.table?.rows[0]).toEqual(["2026-10-13 – 2026-10-14"]);
  });

  it("places forecast money totals under the line table", () => {
    const fields = [
      makeField("purpose", "text", "Purpose"),
      makeField("expense_lines", "grid", "Forecast lines", {
        columns: [
          { label: "Expense type", type: "select" },
          { label: "Amount", type: "currency" },
        ],
      }),
      makeField("requested_amount", "currency", "Requested amount", {
        computed_from: { operation: "sum_grid_column", source_field: "expense_lines", column: "Amount" },
        layout: { row_id: "money_footer", slot: 0 },
      }),
      makeField("vat_total", "currency", "VAT", {
        layout: { row_id: "money_footer", slot: 1 },
      }),
      makeField("non_vatable_total", "currency", "Non-VATable"),
      makeField("vatable_net", "currency", "VATable, net of VAT"),
      makeField("notes", "textarea", "Notes"),
    ];
    const rows = buildComposeReviewSummaryRows(fields, {
      purpose: "test",
      expense_lines: JSON.stringify({ rows: [{ "0": "land", "1": "500" }] }),
      requested_amount: "3350.00",
      vat_total: "321.42",
      non_vatable_total: "350.00",
      vatable_net: "2678.58",
      notes: "—",
    });

    const lines = rows.find((row) => row.fieldName === "expense_lines");
    expect(lines?.totals).toEqual([
      { label: "Requested amount", value: "3,350.00" },
      { label: "VAT", value: "321.42" },
      { label: "Non-VATable", value: "350.00" },
      { label: "VATable, net of VAT", value: "2,678.58" },
    ]);
    expect(rows.map((row) => row.fieldName)).toEqual(["purpose", "expense_lines", "notes"]);
  });

  it("aligns forecast totals under the amount and VAT columns", () => {
    const placed = placeReviewTotals(
      ["From date – To date", "Expense type", "Amount", "VAT treatment", "VAT", "Non-VATable", "VATable, net of VAT"],
      [
        { label: "Requested amount", value: "7,350.00" },
        { label: "VAT", value: "749.99" },
        { label: "Non-VATable", value: "350.00" },
        { label: "VATable, net of VAT", value: "6,250.01" },
      ],
    );

    expect(placed).toHaveLength(1);
    expect(placed[0]?.map((cell) => cell?.label ?? "")).toEqual([
      "",
      "",
      "Requested amount",
      "",
      "VAT",
      "Non-VATable",
      "VATable, net of VAT",
    ]);
  });

  it("shows non-vatable and vatable net on each forecast line", () => {
    const field = makeField("expense_lines", "grid", "Forecast lines", {
      columns: [
        { label: "Expense type", type: "select" },
        { label: "Amount", type: "currency" },
        {
          label: "VAT treatment",
          type: "select",
          choices: [
            { value: "vatable", label: "VATable" },
            { value: "non_vatable", label: "Non-VATable" },
          ],
        },
        { label: "VAT", type: "currency" },
      ],
    });
    const rows = buildComposeReviewSummaryRows([field], {
      expense_lines: JSON.stringify({
        rows: [
          { "0": "gasoline", "1": "1000", "2": "vatable", "3": "107.14" },
          { "0": "land", "1": "350", "2": "non_vatable", "3": "0.00" },
        ],
      }),
    });

    expect(rows[0]?.table?.headers.slice(-2)).toEqual(["Non-VATable", "VATable, net of VAT"]);
    expect(rows[0]?.table?.rows[0]?.slice(-2)).toEqual(["0.00", "892.86"]);
    expect(rows[0]?.table?.rows[1]?.slice(-2)).toEqual(["350.00", "0.00"]);
  });

  it("places liquidation totals under amount, VAT, and the VAT split", () => {
    const fields = [
      makeField("expense_lines", "grid", "Expense lines", {
        columns: [
          { label: "Expense type", type: "select" },
          { label: "Amount", type: "currency" },
          { label: "VAT treatment", type: "select" },
          { label: "VAT", type: "currency" },
        ],
      }),
      makeField("total_reimbursement", "currency", "Total liquidation amount"),
    ];
    const rows = buildComposeReviewSummaryRows(fields, {
      expense_lines: JSON.stringify({
        rows: [
          { "0": "gasoline", "1": "1000", "2": "vatable", "3": "107.14" },
          { "0": "land", "1": "350", "2": "non_vatable", "3": "0.00" },
        ],
      }),
      total_reimbursement: "1350.00",
    });
    const lines = rows.find((row) => row.fieldName === "expense_lines");
    const placed = placeReviewTotals(lines?.table?.headers ?? [], lines?.totals ?? []);

    expect(lines?.totals?.map((total) => total.label)).toEqual([
      "Total liquidation amount",
      "VAT",
      "Non-VATable",
      "VATable, net of VAT",
    ]);
    expect(placed).toHaveLength(1);
    expect(placed[0]?.map((cell) => cell?.value ?? "")).toEqual([
      "",
      "1,350.00",
      "",
      "107.14",
      "350.00",
      "892.86",
    ]);
  });

  it("formats date_range values as a readable range", () => {
    const field = makeField("activity_period", "date_range", "Activity / travel period");
    expect(formatComposeReviewValue(field, '{"from":"2026-09-22","to":"2026-10-20"}')).toBe(
      "2026-09-22 – 2026-10-20",
    );
    expect(formatComposeReviewValue(field, "2026-01-01|2026-01-05")).toBe("2026-01-01 – 2026-01-05");
  });

  it("leaves Quantity and Rate off a liquidation review table", () => {
    const field = makeField("expense_lines", "grid", "Expense lines", {
      columns: [
        { label: "Expense type", type: "select" },
        { label: "Quantity", type: "number" },
        { label: "Rate", type: "currency" },
        { label: "Amount", type: "currency" },
        { label: "Forecast amount", type: "currency" },
      ],
    });
    const table = buildGridReviewTable(
      field,
      JSON.stringify({
        rows: [{ "0": "per_diem", "1": "5", "2": "350", "3": "600", "4": "350.00" }],
      }),
    );

    expect(table?.headers).toEqual(["Expense type", "Amount", "Forecast amount", "Non-VATable", "VATable, net of VAT"]);
    expect(table?.rows[0]?.slice(0, 3)).toEqual(["per_diem", "600", "350.00"]);
  });
});
