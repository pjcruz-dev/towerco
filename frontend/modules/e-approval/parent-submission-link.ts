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

  return formMetadataString(metadata, "form_family") === "liquidation";
}

export function formUsesCashAdvanceParentPicker(
  metadata: Record<string, unknown> | null | undefined,
): boolean {
  if (formMetadataString(metadata, "form_family") === "liquidation") {
    return true;
  }

  return formRequiresParentSubmission(metadata) && formMetadataString(metadata, "parent_form_family") === "cash_advance";
}

export function parentSubmissionLinkLabel(metadata: Record<string, unknown> | null | undefined): string {
  const family = formMetadataString(metadata, "parent_form_family");
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

export function applyParentPrefillValues(
  currentValues: Record<string, string>,
  prefillValues: Record<string, string | null | undefined> | undefined,
  options?: { overwriteKeys?: string[] },
): Record<string, string> {
  if (!prefillValues) {
    return currentValues;
  }

  const overwrite = new Set(options?.overwriteKeys ?? []);
  const next = { ...currentValues };

  for (const [fieldName, rawValue] of Object.entries(prefillValues)) {
    if (rawValue == null || String(rawValue).trim() === "") {
      continue;
    }

    if (overwrite.has(fieldName) || (next[fieldName] ?? "").trim() === "") {
      next[fieldName] = String(rawValue);
    }
  }

  return next;
}

/** Apply / clear Cash Advance parent selection on a liquidation form. */
export function applyCashAdvanceParentSelection(
  currentValues: Record<string, string>,
  item: {
    document_no: string;
    requested_amount: number;
    prefill_values?: Record<string, string>;
  } | null,
): Record<string, string> {
  if (!item) {
    return {
      ...currentValues,
      cash_advance_document_no: "",
      cash_advance_amount: "",
    };
  }

  const amount = formatComputedFieldAmount(item.requested_amount);
  const next = applyParentPrefillValues(
    currentValues,
    {
      ...item.prefill_values,
      cash_advance_document_no: item.document_no,
      cash_advance_amount: amount,
    },
    { overwriteKeys: ["cash_advance_document_no", "cash_advance_amount"] },
  );
  next.cash_advance_document_no = item.document_no;
  next.cash_advance_amount = amount;
  return next;
}
