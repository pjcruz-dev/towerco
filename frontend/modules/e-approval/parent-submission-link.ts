export function formMetadataString(
  metadata: Record<string, unknown> | null | undefined,
  key: string,
): string | null {
  if (!metadata || typeof metadata !== "object") {
    return null;
  }
  const value = metadata[key];
  return typeof value === "string" && value.trim() !== "" ? value.trim() : null;
}

export function formRequiresParentSubmission(metadata: Record<string, unknown> | null | undefined): boolean {
  if (!metadata || typeof metadata !== "object") {
    return false;
  }
  if (metadata.requires_parent_submission === true) {
    return true;
  }

  if (formMetadataString(metadata, "form_family") === "liquidation") {
    return true;
  }

  return (
    formMetadataString(metadata, "form_family") === "purchase_order" &&
    formMetadataString(metadata, "parent_form_family") === "purchase_requisition"
  );
}

export function formUsesCashAdvanceParentPicker(metadata: Record<string, unknown> | null | undefined): boolean {
  if (formMetadataString(metadata, "form_family") === "liquidation") {
    return true;
  }

  return formRequiresParentSubmission(metadata) && formMetadataString(metadata, "parent_form_family") === "cash_advance";
}

export function formUsesPurchaseRequisitionParentPicker(
  metadata: Record<string, unknown> | null | undefined,
): boolean {
  return (
    formRequiresParentSubmission(metadata) &&
    formMetadataString(metadata, "parent_form_family") === "purchase_requisition"
  );
}

export function parentSubmissionLinkLabel(metadata: Record<string, unknown> | null | undefined): string {
  const family = formMetadataString(metadata, "parent_form_family");
  if (family === "purchase_requisition") {
    return "purchase requisition";
  }
  if (family === "cash_advance") {
    return "cash advance";
  }

  return "parent submission";
}

export function parentSubmissionLinkTitle(metadata: Record<string, unknown> | null | undefined): string {
  const label = parentSubmissionLinkLabel(metadata);
  return label.charAt(0).toUpperCase() + label.slice(1);
}

export function parseSubmissionAmount(raw: string | undefined): number | null {
  if (raw === undefined) {
    return null;
  }
  const trimmed = raw.trim().replace(/,/g, "");
  if (trimmed === "") {
    return null;
  }
  const parsed = Number(trimmed);
  return Number.isFinite(parsed) ? parsed : null;
}

/** Plain decimal for `<input type="number">` and API payloads (no thousands separators). */
export function formatComputedFieldAmount(amount: number): string {
  if (!Number.isFinite(amount)) {
    return "0.00";
  }

  return (Math.round(amount * 100) / 100).toFixed(2);
}

export function formatSubmissionAmount(amount: number): string {
  return amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const BALANCE_EPSILON = 0.0001;

export function validateLiquidationAmountAgainstOpenBalance(
  amount: number | null,
  openBalance: number | null | undefined,
): string | null {
  if (amount === null || openBalance == null) {
    return null;
  }

  if (amount > openBalance + BALANCE_EPSILON) {
    return `Amount exceeds the cash advance open balance of ${formatSubmissionAmount(openBalance)}.`;
  }

  return null;
}

export type FinanceProcurementPolicy = {
  po_overspend_mode: "block" | "warn";
  po_max_overspend_percent: number;
};

export function policyMaxPoAmount(
  openBalance: number,
  estimatedTotal: number,
  maxOverspendPercent: number,
): number {
  return openBalance + estimatedTotal * (maxOverspendPercent / 100);
}

export function evaluatePurchaseOrderAmountWithPolicy(
  amount: number | null,
  openBalance: number | null | undefined,
  estimatedTotal: number | null | undefined,
  policy: FinanceProcurementPolicy | null | undefined,
): { blocked: boolean; warning: string | null; helpText: string | null } {
  if (amount === null || openBalance == null || estimatedTotal == null) {
    return { blocked: false, warning: null, helpText: null };
  }

  const mode = policy?.po_overspend_mode ?? "block";
  const percent = mode === "warn" ? Math.max(0, policy?.po_max_overspend_percent ?? 0) : 0;
  const policyMax = policyMaxPoAmount(openBalance, estimatedTotal, percent);

  if (amount <= openBalance + BALANCE_EPSILON) {
    return {
      blocked: false,
      warning: null,
      helpText:
        percent > 0
          ? `Maximum PO amount under tenant policy: ${formatSubmissionAmount(policyMax)}.`
          : `Maximum PO amount for this purchase requisition: ${formatSubmissionAmount(openBalance)}.`,
    };
  }

  if (amount > policyMax + BALANCE_EPSILON) {
    return {
      blocked: true,
      warning: null,
      helpText: `Tenant policy maximum: ${formatSubmissionAmount(policyMax)}.`,
    };
  }

  if (mode === "warn") {
    return {
      blocked: false,
      warning: `PO total exceeds the open balance of ${formatSubmissionAmount(openBalance)}. Tenant policy allows up to ${formatSubmissionAmount(policyMax)} with approver review.`,
      helpText: `Policy maximum: ${formatSubmissionAmount(policyMax)}.`,
    };
  }

  return {
    blocked: true,
    warning: null,
    helpText: `Maximum PO amount for this purchase requisition: ${formatSubmissionAmount(openBalance)}.`,
  };
}

export function validatePurchaseOrderAmountAgainstOpenBalance(
  amount: number | null,
  openBalance: number | null | undefined,
): string | null {
  return evaluatePurchaseOrderAmountWithPolicy(amount, openBalance, openBalance, {
    po_overspend_mode: "block",
    po_max_overspend_percent: 0,
  }).blocked
    ? `PO total exceeds the purchase requisition open balance of ${formatSubmissionAmount(openBalance ?? 0)}.`
    : null;
}

export function applyParentPrefillValues(
  currentValues: Record<string, string>,
  prefillValues: Record<string, string | null | undefined> | undefined,
): Record<string, string> {
  if (!prefillValues) {
    return currentValues;
  }

  const next = { ...currentValues };

  for (const [fieldName, rawValue] of Object.entries(prefillValues)) {
    if (rawValue == null || String(rawValue).trim() === "") {
      continue;
    }

    if ((next[fieldName] ?? "").trim() === "") {
      next[fieldName] = String(rawValue);
    }
  }

  return next;
}
