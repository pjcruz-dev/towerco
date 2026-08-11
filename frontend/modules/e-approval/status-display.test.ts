import { describe, expect, it } from "vitest";

import {
  describeParallelWaitingRule,
  getCurrentPendingApprovals,
  sortApprovalTrailRows,
} from "@/modules/e-approval/status-display";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";

function row(
  partial: Partial<EApprovalApprovalRow> & Pick<EApprovalApprovalRow, "id" | "status">,
): EApprovalApprovalRow {
  return {
    remarks: null,
    acted_at: null,
    step_order: 1,
    approver: { id: "u", name: "User", email: "u@test" },
    submission: null,
    ...partial,
  };
}

describe("waiting on helpers", () => {
  it("returns pending rows on the current step", () => {
    const approvals = [
      row({ id: "a", status: "approved", step_order: 1 }),
      row({ id: "b", status: "pending", step_order: 2, approver: { id: "1", name: "Legal", email: "l@t" } }),
      row({ id: "c", status: "pending", step_order: 2, approver: { id: "2", name: "Finance", email: "f@t" } }),
      row({ id: "d", status: "pending", step_order: 3 }),
    ];

    expect(getCurrentPendingApprovals(approvals, 2).map((item) => item.id)).toEqual(["b", "c"]);
  });

  it("describes parallel completion rules", () => {
    const band = [
      row({ id: "1", status: "pending", parallel_mode: "any" }),
      row({ id: "2", status: "pending", parallel_mode: "any" }),
    ];
    expect(describeParallelWaitingRule(band)).toMatch(/Any one/i);
    expect(
      describeParallelWaitingRule([
        row({ id: "1", status: "pending", parallel_mode: "n_of_m", parallel_quorum: 2 }),
        row({ id: "2", status: "pending", parallel_mode: "n_of_m", parallel_quorum: 2 }),
        row({ id: "3", status: "pending", parallel_mode: "n_of_m", parallel_quorum: 2 }),
      ]),
    ).toMatch(/At least 2 of 3/);
  });

  it("sorts trail with acted parallel peers before pending within a step", () => {
    const sorted = sortApprovalTrailRows([
      row({ id: "wait", status: "pending", step_order: 3 }),
      row({ id: "done", status: "approved", step_order: 3, acted_at: "2026-08-01T10:00:00.000Z" }),
      row({ id: "old", status: "superseded", is_prior_cycle: true, step_order: 1 }),
    ]);
    expect(sorted.map((item) => item.id)).toEqual(["done", "wait", "old"]);
  });
});
