import { describe, expect, it } from "vitest";

import { applyCashAdvanceParentSelection } from "./parent-submission-link";

describe("applyCashAdvanceParentSelection", () => {
  it("fills document no and requested amount from the selected cash advance", () => {
    const next = applyCashAdvanceParentSelection(
      { cash_advance_document_no: "", cash_advance_amount: "", notes: "" },
      {
        document_no: "CA-2026-001",
        requested_amount: 5000,
        prefill_values: { notes: "Site visit" },
      },
    );

    expect(next.cash_advance_document_no).toBe("CA-2026-001");
    expect(next.cash_advance_amount).toBe("5000.00");
    expect(next.notes).toBe("Site visit");
  });

  it("overwrites amount when switching cash advance", () => {
    const next = applyCashAdvanceParentSelection(
      {
        cash_advance_document_no: "CA-OLD",
        cash_advance_amount: "100.00",
      },
      {
        document_no: "CA-NEW",
        requested_amount: 2500.5,
      },
    );

    expect(next.cash_advance_document_no).toBe("CA-NEW");
    expect(next.cash_advance_amount).toBe("2500.50");
  });

  it("replaces forecast lines when another cash advance is selected", () => {
    const next = applyCashAdvanceParentSelection(
      {
        cash_advance_document_no: "CA-OLD",
        expense_lines: "{\"rows\":[{\"0\":\"old\"}]}",
        subsidiary: "ATC",
        notes: "old note",
      },
      {
        document_no: "CA-NEW",
        requested_amount: 800,
        prefill_values: {
          expense_lines: "{\"rows\":[{\"0\":\"new\"}]}",
          subsidiary: "ADIC",
          notes: "new note",
        },
      },
    );

    expect(next.expense_lines).toBe("{\"rows\":[{\"0\":\"new\"}]}");
    expect(next.subsidiary).toBe("ADIC");
    expect(next.notes).toBe("new note");
  });

  it("clears cash advance fields when selection is removed", () => {
    const next = applyCashAdvanceParentSelection(
      {
        cash_advance_document_no: "CA-2026-001",
        cash_advance_amount: "5000.00",
        expense_lines: "{\"rows\":[{\"0\":\"trip\"}]}",
        area: "North",
      },
      null,
    );

    expect(next.cash_advance_document_no).toBe("");
    expect(next.cash_advance_amount).toBe("");
    expect(next.expense_lines).toBe("");
    expect(next.area).toBe("North");
  });
});
