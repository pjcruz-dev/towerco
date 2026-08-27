/** Legal acknowledgment required before saving or applying an electronic signature. */
export const SIGNATURE_CONSENT_LABEL =
  "I agree to use an electronic signature. I understand it is voluntary, legally binding, and carries the same weight as a handwritten ink signature for approvals and printed documents.";

export const SIGNATURE_CONSENT_HINT =
  "Required before you can save or apply a signature.";

/** Default retention copy when the organization has not configured a specific period. */
export const SIGNATURE_STORAGE_RETENTION_DEFAULT =
  "for as long as related approval records and your user profile are retained, or until you clear your signature";

/**
 * Consent to store the signature image for reuse on approvals and PDF footers.
 */
export function signatureStorageConsentLabel(
  companyName: string,
  retentionPeriod: string = SIGNATURE_STORAGE_RETENTION_DEFAULT,
): string {
  const company = companyName.trim() || "this organization";
  return `I consent to ${company} storing this signature image to apply to my approvals and PDF footers, retained ${retentionPeriod}.`;
}
