/** Tenant-editable print layout JSON stored in e_approval_settings (pdf_layout_form_{id}). */
export type EApprovalPrintTemplateBlocks = {
  party_row?: string[];
  meta_row?: string[];
  requestor_row?: string[];
  line_items_grid?: string;
  tax_summary_left?: string[];
  tax_summary_right?: string[];
  totals?: string[];
  justification?: string[];
  signatures?: string[];
};

/** Default starter codes — tenants can add more from the Print tab. */
export const EAPPROVAL_DEFAULT_SUBSIDIARY_CODES = ["ATC", "ADIC"] as const;

export function normalizeSubsidiaryCode(raw: string): string | null {
  const code = raw.trim().toUpperCase();
  if (!/^[A-Z0-9][A-Z0-9_-]{0,23}$/.test(code)) {
    return null;
  }
  return code;
}

export type EApprovalPrintTemplate = {
  layout_kind?: string;
  page?: { size?: string; marginMm?: number };
  header?: {
    showLogo?: boolean;
    title?: string;
    showDocumentNo?: boolean;
    showStatus?: boolean;
    showDate?: boolean;
    showRequestor?: boolean;
    subtitle?: string;
  };
  footer?: {
    showApprovalHistory?: boolean;
    showRequestorSignature?: boolean;
    showPageNumbers?: boolean;
    /** When false, skip merging PDF/image attachments into the stamped PDF. Default true. */
    appendAttachments?: boolean;
    text?: string;
  };
  blocks?: EApprovalPrintTemplateBlocks;
  /** Custom document body HTML with {{field.*}} / {{system.*}} tokens. */
  template_html?: string;
  template_css?: string;
  orientation?: "portrait" | "landscape";
  /** Form field key used to pick subsidiary logo (default: subsidiary). */
  subsidiary_logo_field?: string;
  /** Ordered subsidiary codes configured for this form. */
  subsidiary_codes?: string[];
  /** Map of subsidiary code → logo URL or storage path. */
  subsidiary_logos?: Record<string, string>;
};

export type ApprovalHistorySlot = {
  key: string;
  label: string;
  subtitle?: string;
  signature: string | null;
  kind: "requestor" | "prepared_by" | "approver";
};
