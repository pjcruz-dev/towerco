export type EApprovalFinanceProcurementPolicy = {
  liquidation_requires_parent: boolean;
  liquidation_overspend_mode: "block" | "warn";
  liquidation_max_overspend_percent: number;
  po_overspend_mode: "block" | "warn";
  po_max_overspend_percent: number;
};

export function evaluateLiquidationAmountAgainstPolicy(
  amount: number,
  openBalance: number,
  requestedTotal: number,
  policy: EApprovalFinanceProcurementPolicy,
): { blocked: boolean; warning: string | null; policyMaxAmount: number } {
  const strictOpen = Math.max(0, openBalance);
  const percent = policy.liquidation_overspend_mode === "warn" ? policy.liquidation_max_overspend_percent : 0;
  const policyMaxAmount = roundMoney(strictOpen + requestedTotal * (percent / 100));

  if (amount <= strictOpen + 0.0001) {
    return { blocked: false, warning: null, policyMaxAmount };
  }

  if (amount > policyMaxAmount + 0.0001) {
    return { blocked: true, warning: null, policyMaxAmount };
  }

  if (policy.liquidation_overspend_mode === "warn") {
    return {
      blocked: false,
      warning: `Liquidation total exceeds open balance (${formatMoney(strictOpen)}). Tenant policy allows up to ${formatMoney(policyMaxAmount)}.`,
      policyMaxAmount,
    };
  }

  return { blocked: true, warning: null, policyMaxAmount };
}

export function evaluatePurchaseOrderAmountAgainstPolicy(
  amount: number,
  openBalance: number,
  estimatedTotal: number,
  policy: EApprovalFinanceProcurementPolicy,
): { blocked: boolean; warning: string | null; policyMaxAmount: number } {
  const strictOpen = Math.max(0, openBalance);
  const percent = policy.po_overspend_mode === "warn" ? policy.po_max_overspend_percent : 0;
  const policyMaxAmount = roundMoney(strictOpen + estimatedTotal * (percent / 100));

  if (amount <= strictOpen + 0.0001) {
    return { blocked: false, warning: null, policyMaxAmount };
  }

  if (amount > policyMaxAmount + 0.0001) {
    return { blocked: true, warning: null, policyMaxAmount };
  }

  if (policy.po_overspend_mode === "warn") {
    return {
      blocked: false,
      warning: `PO total exceeds open balance (${formatMoney(strictOpen)}). Tenant policy allows up to ${formatMoney(policyMaxAmount)}.`,
      policyMaxAmount,
    };
  }

  return { blocked: true, warning: null, policyMaxAmount };
}

function roundMoney(value: number): number {
  return Math.round(value * 100) / 100;
}

function formatMoney(value: number): string {
  return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
