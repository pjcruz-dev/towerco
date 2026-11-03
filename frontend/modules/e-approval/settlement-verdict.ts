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
  /** Cash already moved, and a remainder is still open. */
  outstanding?: boolean;
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

export type SettlementRecord = {
  status: string;
  reference?: string | null;
  date?: string | null;
  amount?: string | null;
};

export type SettlementBalance = {
  direction: SettlementDirection;
  /** Absolute pesos still to move. */
  amount: string;
  net: string;
  current: string;
  closed: boolean;
  prior_status?: string | null;
  prior_reference?: string | null;
  prior_date?: string | null;
  prior_amount?: string | null;
};

/**
 * A later revision compares the new difference with cash already moved.
 * The banner asks only for what is still open.
 */
export function settlementVerdictWithBalance(
  verdict: SettlementVerdict,
  record: SettlementRecord | null | undefined,
  balance: SettlementBalance | null | undefined,
): SettlementVerdict {
  const net = Number(balance?.net ?? "0");
  if (
    balance &&
    !balance.closed &&
    Number.isFinite(net) &&
    Math.abs(net) > 0.004 &&
    (balance.direction === "payment" || balance.direction === "surrender")
  ) {
    const amountLabel = formatGridCurrencyDisplay(balance.amount);
    const priorLabel = formatGridCurrencyDisplay(balance.prior_amount?.trim() ? balance.prior_amount : balance.net);
    const priorVerb = balance.prior_status === "received" ? "Received" : "Paid";
    const reference = balance.prior_reference?.trim() ?? "";
    const date = balance.prior_date?.trim() ?? "";
    const stamp = [
      reference !== "" ? `reference ${reference}` : "",
      date !== "" ? `dated ${date}` : "",
    ]
      .filter((part) => part !== "")
      .join(", ");

    return {
      ...verdict,
      direction: balance.direction,
      amount: Number(balance.amount),
      outstanding: true,
      title: balance.direction === "surrender" ? `Still to receive ${amountLabel}.` : `Still to pay ${amountLabel}.`,
      detail: `${priorVerb} ${priorLabel}${stamp !== "" ? `, ${stamp}` : ""}.`,
    };
  }

  return settlementVerdictAfterCapture(verdict, record);
}

/** Once the cash has moved, the banner records that instead of asking for it again. */
export function settlementVerdictAfterCapture(
  verdict: SettlementVerdict,
  record: SettlementRecord | null | undefined,
): SettlementVerdict {
  const status = (record?.status ?? "").trim().toLowerCase();
  if (status !== "paid" && status !== "received" && status !== "settled") {
    return verdict;
  }

  const amountLabel = formatGridCurrencyDisplay(
    record?.amount?.trim() ? record.amount : verdict.amount.toFixed(2),
  );
  const reference = record?.reference?.trim() ?? "";
  const date = record?.date?.trim() ?? "";
  const stamp = [
    reference !== "" ? `Reference ${reference}` : "",
    date !== "" ? `dated ${date}` : "",
  ]
    .filter((part) => part !== "")
    .join(", ");
  const recorded = stamp !== "" ? `${stamp}. ` : "";

  if (status === "received") {
    return {
      ...verdict,
      direction: "settled",
      title: `Received ${amountLabel}.`,
      detail: `${recorded}The employee returned the difference.`,
    };
  }
  if (status === "settled") {
    return {
      ...verdict,
      direction: "settled",
      title: "Settled. No cash moved.",
      detail: recorded !== "" ? stamp + "." : verdict.detail,
    };
  }

  return {
    ...verdict,
    direction: "settled",
    title: `Paid ${amountLabel}.`,
    detail: `${recorded}The company already paid the difference.`,
  };
}

type SettlementStep = {
  step_order: number;
  confirm_settlement?: boolean | null;
};

/**
 * This pending step is marked Settle the cash. Even totals still open the panel.
 * A step that is not marked does not ask, including the last approver.
 */
export function approverMustConfirmSettlement(input: {
  direction: SettlementDirection | null;
  pendingStepOrder: number | null;
  steps: SettlementStep[];
}): boolean {
  if (input.direction == null || input.pendingStepOrder == null) {
    return false;
  }

  return input.steps.some(
    (step) => step.confirm_settlement === true && step.step_order === input.pendingStepOrder,
  );
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
