import { describe, expect, it } from "vitest";

import type { EApprovalApprovalRow } from "@/modules/e-approval/types";
import {
  isSystemSupersedeRemark,
  resolveApprovalTrailHistoryScope,
  splitApprovalTrailCycles,
} from "@/modules/e-approval/status-display";

function row(
  partial: Partial<EApprovalApprovalRow> & Pick<EApprovalApprovalRow, "id" | "status">,
): EApprovalApprovalRow {
  return {
    approval_status: partial.approval_status ?? partial.status,
    step_order: 1,
    is_prior_cycle: false,
    ...partial,
  };
}

describe("approval trail cycle split", () => {
  it("keeps current-cycle rows first and collapses prior separately", () => {
    const { current, prior } = splitApprovalTrailCycles([
      row({
        id: "prior-1",
        status: "approved",
        approval_status: "superseded",
        is_prior_cycle: true,
        step_order: 1,
        remarks: "Superseded by full workflow restart.",
      }),
      row({
        id: "current-pending",
        status: "pending",
        approval_status: "pending",
        step_order: 2,
      }),
      row({
        id: "current-approved",
        status: "approved",
        approval_status: "approved",
        step_order: 1,
      }),
      row({
        id: "parallel-skip",
        status: "invalidated",
        approval_status: "invalidated",
        step_order: 2,
      }),
    ]);

    expect(current.map((r) => r.id)).toEqual(["current-approved", "current-pending", "parallel-skip"]);
    expect(prior.map((r) => r.id)).toEqual(["prior-1"]);
  });

  it("detects system supersede remarks", () => {
    expect(isSystemSupersedeRemark("Superseded by full workflow restart.")).toBe(true);
    expect(isSystemSupersedeRemark("Looks good")).toBe(false);
  });

  it("forces full history for print even when Current only is selected", () => {
    expect(resolveApprovalTrailHistoryScope("current")).toBe("current");
    expect(resolveApprovalTrailHistoryScope("current", { alwaysShowFullHistory: true })).toBe("all");
    expect(resolveApprovalTrailHistoryScope("all", { alwaysShowFullHistory: true })).toBe("all");
  });
});
