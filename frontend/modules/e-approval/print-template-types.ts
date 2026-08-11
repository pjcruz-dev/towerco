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

export type EApprovalPrintTemplate = {
  layout_kind: string;
  page?: { size?: string; marginMm?: number };
  header?: {
    showLogo?: boolean;
    title?: string;
    showDocumentNo?: boolean;
    showStatus?: boolean;
    showDate?: boolean;
    showRequestor?: boolean;
  };
  footer?: {
    showApprovalHistory?: boolean;
    showRequestorSignature?: boolean;
    showPageNumbers?: boolean;
    text?: string;
  };
  blocks?: EApprovalPrintTemplateBlocks;
};

export type ApprovalHistorySlot = {
  key: string;
  label: string;
  subtitle?: string;
  signature: string | null;
  kind: "requestor" | "prepared_by" | "approver";
};
