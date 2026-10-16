import { formatGridCurrencyDisplay } from "@/modules/e-approval/grid-row-formulas";
import { parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";

export type SettlementDirection = "surrender" | "payment" | "settled";

export type SettlementVerdict = {
  direction: SettlementDirection;
  /** Pesos that move. Zero when the totals match. */
  amount: number;
  actual: number;
  released: number;
  title: string;
  detail: string;
};

type ValueRow = {
  field_name?: string | null;
  value?: string | null;
};

/** Actual expenses minus cash released. Negative means the employee returns cash. */
export function settlementVerdictFromValues(rows: ValueRow[]): SettlementVerdict | null {
  const actual = readAmount(rows, "total_reimbursement");
  const released = readAmount(rows, "cash_advance_amount");
  if (actual === null || released === null) {
    return null;
  }

  const delta = Math.round((actual - released) * 100) / 100;
  const amount = Math.abs(delta);
  const direction: SettlementDirection =
    delta > 0.004 ? "payment" : delta < -0.004 ? "surrender" : "settled";
  const actualLabel = formatGridCurrencyDisplay(actual.toFixed(2));
  const releasedLabel = formatGridCurrencyDisplay(released.toFixed(2));
  const amountLabel = formatGridCurrencyDisplay(amount.toFixed(2));

  if (direction === "surrender") {
    return {
      direction,
      amount,
      actual,
      released,
      title: `Surrender. The employee returns ${amountLabel}.`,
      detail: `Actual expenses ${actualLabel} are below cash released ${releasedLabel}.`,
    };
  }
  if (direction === "payment") {
    return {
      direction,
      amount,
      actual,
      released,
      title: `Pay the employee ${amountLabel}.`,
      detail: `Actual expenses ${actualLabel} are above cash released ${releasedLabel}.`,
    };
  }

  return {
    direction,
    amount: 0,
    actual,
    released,
    title: "Settled. No cash to move.",
    detail: `Actual expenses ${actualLabel} match cash released ${releasedLabel}.`,
  };
}

type SettlementStep = {
  step_order: number;
  confirm_settlement?: boolean | null;
};

/**
 * Cash must move, and this approver is one of the steps marked to confirm it.
 * When no step is marked, the last approver who actually runs confirms.
 */
export function approverMustConfirmSettlement(input: {
  direction: SettlementDirection | null;
  pendingStepOrder: number | null;
  steps: SettlementStep[];
}): boolean {
  if (input.direction !== "surrender" && input.direction !== "payment") {
    return false;
  }
  if (input.pendingStepOrder == null) {
    return false;
  }

  const explicit = input.steps.filter((step) => step.confirm_settlement === true);
  if (explicit.length > 0) {
    return explicit.some((step) => step.step_order === input.pendingStepOrder);
  }

  const orders = input.steps
    .map((step) => step.step_order)
    .filter((order) => Number.isFinite(order));
  if (orders.length === 0) {
    return false;
  }

  return input.pendingStepOrder === Math.max(...orders);
}

/** Reimbursement has no cash advance and no forecast. The actual total is the amount to pay. */
export function reimbursementPayVerdict(rows: ValueRow[]): SettlementVerdict | null {
  const actual = readAmount(rows, "total_reimbursement");
  if (actual === null) {
    return null;
  }

  const amountLabel = formatGridCurrencyDisplay(actual.toFixed(2));

  return {
    direction: "payment",
    amount: actual,
    actual,
    released: 0,
    title: `Pay the employee ${amountLabel}.`,
    detail: `Actual expenses ${amountLabel}. There is no cash advance on a reimbursement.`,
  };
}

function readAmount(rows: ValueRow[], name: string): number | null {
  const row = rows.find((item) => item.field_name === name);
  if (!row) {
    return null;
  }
  return parseSubmissionAmount(row.value ?? "");
}
