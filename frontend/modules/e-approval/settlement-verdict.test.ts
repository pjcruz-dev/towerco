import { describe, expect, it } from "vitest";

import {
  approverMustConfirmSettlement,
  reimbursementPayVerdict,
  settlementVerdictFromValues,
} from "@/modules/e-approval/settlement-verdict";

describe("settlement verdict", () => {
  it("reads a surrender when actual expenses are below the cash released", () => {
    const verdict = settlementVerdictFromValues([
      { field_name: "total_reimbursement", value: "10600.00" },
      { field_name: "cash_advance_amount", value: "14050.00" },
    ]);

    expect(verdict?.direction).toBe("surrender");
    expect(verdict?.amount).toBe(3450);
    expect(verdict?.title).toBe("Surrender. The employee returns ₱3,450.00.");
  });

  it("reads a payment when actual expenses are above the cash released", () => {
    const verdict = settlementVerdictFromValues([
      { field_name: "total_reimbursement", value: "17050" },
      { field_name: "cash_advance_amount", value: "14050" },
    ]);

    expect(verdict?.direction).toBe("payment");
    expect(verdict?.amount).toBe(3000);
    expect(verdict?.title).toBe("Pay the employee ₱3,000.00.");
  });

  it("asks only the last running approver when no step is marked", () => {
    const steps = [{ step_order: 2 }, { step_order: 3 }, { step_order: 4 }];

    expect(
      approverMustConfirmSettlement({ direction: "surrender", pendingStepOrder: 2, steps }),
    ).toBe(false);
    expect(
      approverMustConfirmSettlement({ direction: "surrender", pendingStepOrder: 4, steps }),
    ).toBe(true);
  });

  it("asks only the steps marked to confirm settlement", () => {
    const steps = [
      { step_order: 2, confirm_settlement: true },
      { step_order: 3 },
      { step_order: 4 },
    ];

    expect(
      approverMustConfirmSettlement({ direction: "payment", pendingStepOrder: 2, steps }),
    ).toBe(true);
    expect(
      approverMustConfirmSettlement({ direction: "payment", pendingStepOrder: 4, steps }),
    ).toBe(false);
    expect(
      approverMustConfirmSettlement({ direction: "settled", pendingStepOrder: 2, steps }),
    ).toBe(false);
  });

  it("pays the reimbursement total and does not describe a surrender", () => {
    const verdict = reimbursementPayVerdict([
      { field_name: "total_reimbursement", value: "8500.00" },
    ]);

    expect(verdict?.direction).toBe("payment");
    expect(verdict?.title).toBe("Pay the employee ₱8,500.00.");
    expect(verdict?.title).not.toContain("Surrender");
  });
});
