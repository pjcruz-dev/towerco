import { describe, expect, it } from "vitest";

import { buildApprovalHistorySlots, buildPdfFooterRows } from "@/modules/e-approval/approval-history-print";
import type { EApprovalPrintApprovalRow, EApprovalPrintPayload } from "@/modules/e-approval/types";

function payload(approvals: EApprovalPrintApprovalRow[]): EApprovalPrintPayload {
  return {
    document_no: "ATC-X-LQ-001",
    form_name: "Liquidation",
    status: "pending",
    requestor: "Ada Requestor",
    created_at: null,
    fields: [],
    approvals,
    template: {},
    show_approval_trail: true,
  };
}

function row(partial: Partial<EApprovalPrintApprovalRow> & Pick<EApprovalPrintApprovalRow, "status">): EApprovalPrintApprovalRow {
  return {
    step: 1,
    approver: "Person",
    remarks: null,
    signature: "data:image/png;base64,abc",
    acted_at: "2026-10-22 10:00",
    ...partial,
  };
}

describe("print approval history", () => {
  it("includes a signed reviewer acknowledgement beside approvals", () => {
    const data = payload([
      row({ step: 3, approver: "CFO", status: "approved", acted_at: "2026-10-22 12:00" }),
      row({ step: 1, approver: "Norelyn Munoz", status: "acknowledged", acted_at: "2026-10-22 09:00" }),
      row({ step: 2, approver: "Waiting", status: "pending", signature: null, acted_at: null }),
    ]);

    const slots = buildApprovalHistorySlots(data);
    expect(slots.map((slot) => slot.label)).toEqual(["Norelyn Munoz", "CFO"]);
    expect(slots[0]).toMatchObject({ kind: "reviewer", subtitle: "Reviewed · 2026-10-22 09:00" });
    expect(slots[1]).toMatchObject({ kind: "approver", subtitle: "Approved · 2026-10-22 12:00" });

    const footer = buildPdfFooterRows(data);
    expect(footer.map((row) => ({ label: row.label, status: row.status, actedAt: row.actedAt }))).toEqual([
      { label: "Norelyn Munoz", status: "REVIEWED", actedAt: "2026-10-22 09:00" },
      { label: "CFO", status: "APPROVED", actedAt: "2026-10-22 12:00" },
    ]);
  });

  it("labels a signed release step as Released", () => {
    const data = payload([
      row({ step: 1, approver: "Carlo Traya", status: "approved", acted_at: "2026-10-30 04:12" }),
      row({
        step: 4,
        approver: "Treasury",
        status: "approved",
        acted_at: "2026-10-30 09:00",
        finance_action: "release",
      }),
    ]);

    const slots = buildApprovalHistorySlots(data);
    expect(slots.map((slot) => slot.subtitle)).toEqual([
      "Approved · 2026-10-30 04:12",
      "Released · 2026-10-30 09:00",
    ]);
    expect(buildPdfFooterRows(data).map((entry) => entry.status)).toEqual(["APPROVED", "RELEASED"]);
  });

  it("uses the proof of payment status and a custom decision word", () => {
    const data = payload([
      row({
        step: 2,
        approver: "Carlo Traya",
        status: "approved",
        acted_at: "2026-10-30 04:12",
        proof_status: "released",
      }),
      row({
        step: 1,
        approver: "Finance",
        status: "approved",
        acted_at: "2026-10-30 03:00",
        decision_label: "Validated",
      }),
    ]);

    expect(buildApprovalHistorySlots(data).map((slot) => slot.subtitle)).toEqual([
      "Validated · 2026-10-30 03:00",
      "Released · 2026-10-30 04:12",
    ]);
  });
});
