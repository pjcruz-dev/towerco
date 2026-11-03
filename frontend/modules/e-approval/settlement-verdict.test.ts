import { describe, expect, it } from "vitest";

import {
  approverMustConfirmSettlement,
  reimbursementPayVerdict,
  settlementVerdictAfterCapture,
  settlementVerdictFromValues,
  settlementVerdictWithBalance,
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

  it("does not ask the last approver when no step is marked", () => {
    const steps = [{ step_order: 2 }, { step_order: 3 }, { step_order: 4 }];

    expect(
      approverMustConfirmSettlement({ direction: "surrender", pendingStepOrder: 2, steps }),
    ).toBe(false);
    expect(
      approverMustConfirmSettlement({ direction: "surrender", pendingStepOrder: 4, steps }),
    ).toBe(false);
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
    ).toBe(true);
  });

  it("pays the reimbursement total and does not describe a surrender", () => {
    const verdict = reimbursementPayVerdict([
      { field_name: "total_reimbursement", value: "8500.00" },
    ]);

    expect(verdict?.direction).toBe("payment");
    expect(verdict?.title).toBe("Pay the employee ₱8,500.00.");
    expect(verdict?.title).not.toContain("Surrender");
  });

  it("says the difference was paid once a payment is recorded", () => {
    const base = settlementVerdictFromValues([
      { field_name: "total_reimbursement", value: "70009.00" },
      { field_name: "cash_advance_amount", value: "3500.00" },
    ]);

    const recorded = settlementVerdictAfterCapture(base!, {
      status: "paid",
      amount: "66509.00",
      reference: "gggdfd6666",
      date: "2026-10-31",
    });

    expect(recorded.title).toBe("Paid ₱66,509.00.");
    expect(recorded.detail).toContain("gggdfd6666");
    expect(recorded.title).not.toContain("Pay the employee");
  });

  it("asks for the unpaid remainder after a revision", () => {
    const base = settlementVerdictFromValues([
      { field_name: "total_reimbursement", value: "30000.00" },
      { field_name: "cash_advance_amount", value: "15000.00" },
    ]);

    const open = settlementVerdictWithBalance(
      base!,
      { status: "paid", amount: "5000.00", reference: "AS312FDRD222", date: "2026-11-03" },
      {
        direction: "payment",
        amount: "10000.00",
        net: "5000.00",
        current: "15000.00",
        closed: false,
        prior_status: "paid",
        prior_reference: "AS312FDRD222",
        prior_date: "2026-11-03",
        prior_amount: "5000.00",
      },
    );

    expect(open.title).toBe("Still to pay ₱10,000.00.");
    expect(open.detail).toContain("AS312FDRD222");
    expect(open.direction).toBe("payment");
  });
});
