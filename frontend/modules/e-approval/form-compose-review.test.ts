import { describe, expect, it } from "vitest";

import {
  appendComposeReviewStep,
  buildChecklistMatrixReviewTable,
  buildComposeReviewSummaryRows,
  formatComposeReviewValue,
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
});
