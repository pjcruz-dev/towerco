import type {
  EApprovalApprovalRow,
  EApprovalCommentRow,
  EApprovalPrintPayload,
  EApprovalSubmissionDetail,
  EApprovalSubmissionListRow,
} from "@/modules/e-approval/types";
import type { EApprovalWorkflowPreviewResponse } from "@/lib/api/modules/e-approval-api";

/**
 * Ephemeral Document Approval sample for the interactive tour
 * (Title, Approver 1–3, Attachments only — matches live Document Approval form).
 * Never persisted / never seeded.
 */
export const E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO = "ATC-DA-F-418";
export const E_APPROVAL_TOUR_SAMPLE_FORM_NAME = "Document Approval";

export const eApprovalTourSampleRequestor = {
  id: "tour-requestor",
  name: "Maria Santos",
  email: "maria.santos@towerco.example",
} as const;

export const eApprovalTourSampleSubmittedAt = "2026-08-25T09:42:00+08:00";

/** List rows for tour gallery/table fixtures (Document Approval only — same set in both views). */
export const eApprovalTourSampleListRows: EApprovalSubmissionListRow[] = [
  {
    id: "tour-sample",
    document_no: E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO,
    status: "pending",
    current_step: 1,
    form_id: "tour-sample",
    form_name: E_APPROVAL_TOUR_SAMPLE_FORM_NAME,
    requestor: {
      id: eApprovalTourSampleRequestor.id,
      name: eApprovalTourSampleRequestor.name,
      email: eApprovalTourSampleRequestor.email,
    },
    created_at: eApprovalTourSampleSubmittedAt,
  },
];

export const eApprovalTourSampleFormFields: NonNullable<EApprovalSubmissionDetail["form_fields"]> = [
  { id: "f-title", type: "text", name: "title", label: "Title" },
  { id: "f-appr1", type: "approver", name: "approver_1", label: "Approver 1" },
  { id: "f-appr2", type: "approver", name: "approver_2", label: "Approver 2" },
  { id: "f-appr3", type: "approver", name: "approver_3", label: "Approver 3" },
  { id: "f-files", type: "file", name: "attachments", label: "Attachments" },
];

export const eApprovalTourSampleValues: EApprovalSubmissionDetail["values"] = [
  {
    field_id: "f-title",
    field_name: "title",
    field_type: "text",
    label: "Title",
    value: "Network Operations Handover Procedure",
    display_value: "Network Operations Handover Procedure",
  },
  {
    field_id: "f-appr1",
    field_name: "approver_1",
    field_type: "approver",
    label: "Approver 1",
    value: "tour-mgr",
    display_value: "James Rivera",
    display_subtitle: "james.rivera@towerco.example",
  },
  {
    field_id: "f-appr2",
    field_name: "approver_2",
    field_type: "approver",
    label: "Approver 2",
    value: "tour-qa",
    display_value: "Alya Mendoza",
    display_subtitle: "alya.mendoza@towerco.example",
  },
  {
    field_id: "f-appr3",
    field_name: "approver_3",
    field_type: "approver",
    label: "Approver 3",
    value: "tour-final",
    display_value: "Carlos Nguyen",
    display_subtitle: "carlos.nguyen@towerco.example",
  },
  {
    field_id: "f-files",
    field_name: "attachments",
    field_type: "file",
    label: "Attachments",
    value: "sop-noc-014-rev3.pdf",
    display_value: "sop-noc-014-rev3.pdf",
  },
];

export const eApprovalTourSampleAttachments: EApprovalSubmissionDetail["attachments"] = [
  {
    id: "tour-att-1",
    field_name: "attachments",
    file_name: "sop-noc-014-rev3.pdf",
    file_path: "",
  },
  {
    id: "tour-att-2",
    field_name: "attachments",
    file_name: "sop-noc-014-rev3-appendix.pdf",
    file_path: "",
  },
];

export const eApprovalTourSampleApprovals: EApprovalApprovalRow[] = [
  {
    id: "tour-appr-1",
    status: "pending",
    remarks: null,
    signature: null,
    acted_at: null,
    step_order: 1,
    approver: {
      id: "tour-mgr",
      name: "James Rivera",
      email: "james.rivera@towerco.example",
    },
    submission: {
      id: "tour-sample",
      document_no: E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO,
      status: "pending",
    },
  },
  {
    id: "tour-appr-2",
    status: "pending",
    remarks: null,
    signature: null,
    acted_at: null,
    step_order: 2,
    approver: {
      id: "tour-qa",
      name: "Alya Mendoza",
      email: "alya.mendoza@towerco.example",
    },
    submission: {
      id: "tour-sample",
      document_no: E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO,
      status: "pending",
    },
  },
  {
    id: "tour-appr-3",
    status: "pending",
    remarks: null,
    signature: null,
    acted_at: null,
    step_order: 3,
    approver: {
      id: "tour-final",
      name: "Carlos Nguyen",
      email: "carlos.nguyen@towerco.example",
    },
    submission: {
      id: "tour-sample",
      document_no: E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO,
      status: "pending",
    },
  },
];

export const eApprovalTourSampleComments: EApprovalCommentRow[] = [
  {
    id: "tour-cmt-1",
    message: "Submitted for Document Approval. PDF attachments are on the request.",
    user_name: eApprovalTourSampleRequestor.name,
    created_at: eApprovalTourSampleSubmittedAt,
    replies: [],
  },
];

export const eApprovalTourSampleWorkflowSteps = [
  {
    step_order: 1,
    title: "Approver 1",
    status: "pending" as const,
    approverName: "James Rivera",
    note: "Current step",
  },
  {
    step_order: 2,
    title: "Approver 2",
    status: "waiting" as const,
    approverName: "Alya Mendoza",
    note: "Waiting",
  },
  {
    step_order: 3,
    title: "Approver 3",
    status: "waiting" as const,
    approverName: "Carlos Nguyen",
    note: "Waiting",
  },
];

/** Live-shaped workflow path preview for the tour sample (Start → Approver 1–3 → End). */
export const eApprovalTourSampleWorkflowPreview: EApprovalWorkflowPreviewResponse = {
  workflow_mode: "form_fields",
  matched_rule_id: null,
  matched_rule_label: null,
  definition_source: "workflow_snapshot",
  resolved_steps: [
    {
      step_order: 1,
      type: "field",
      label: "Approver 1",
      resolved_user_id: "tour-mgr",
      resolved_user_name: "James Rivera",
      resolved_user_email: "james.rivera@towerco.example",
      mapping_source_field: "approver_1",
      path_reason: null,
      warning: null,
      runtime_status: "pending",
      approval_id: "tour-appr-1",
      runtime_approver: {
        id: "tour-mgr",
        name: "James Rivera",
        email: "james.rivera@towerco.example",
      },
    },
    {
      step_order: 2,
      type: "field",
      label: "Approver 2",
      resolved_user_id: "tour-qa",
      resolved_user_name: "Alya Mendoza",
      resolved_user_email: "alya.mendoza@towerco.example",
      mapping_source_field: "approver_2",
      path_reason: null,
      warning: null,
      runtime_status: "pending",
      approval_id: "tour-appr-2",
      runtime_approver: {
        id: "tour-qa",
        name: "Alya Mendoza",
        email: "alya.mendoza@towerco.example",
      },
    },
    {
      step_order: 3,
      type: "field",
      label: "Approver 3",
      resolved_user_id: "tour-final",
      resolved_user_name: "Carlos Nguyen",
      resolved_user_email: "carlos.nguyen@towerco.example",
      mapping_source_field: "approver_3",
      path_reason: null,
      warning: null,
      runtime_status: "pending",
      approval_id: "tour-appr-3",
      runtime_approver: {
        id: "tour-final",
        name: "Carlos Nguyen",
        email: "carlos.nguyen@towerco.example",
      },
    },
  ],
  skipped_steps: [],
};
/** Compose defaults aligned with Document Approval fields only. */
export const eApprovalTourSampleComposeDefaults = {
  title: "Network Operations Handover Procedure",
  approver1: "James Rivera",
  approver2: "Alya Mendoza",
  approver3: "Carlos Nguyen",
  files: ["sop-noc-014-rev3.pdf", "sop-noc-014-rev3-appendix.pdf"],
} as const;

export function formatTourSampleTimestamp(value: string): string {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

/** Drawn-style SVG signature for tour print stamps (data URL, never persisted). */
export function eApprovalTourFakeSignatureDataUrl(name: string, ink = "#0f172a"): string {
  const safe = name
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="320" height="96" viewBox="0 0 320 96">
  <rect width="320" height="96" fill="transparent"/>
  <path d="M18 62 C48 18, 78 86, 108 44 S158 18, 188 52 S238 78, 278 40" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.85"/>
  <text x="24" y="78" font-family="Segoe Script, Brush Script MT, Lucida Handwriting, cursive" font-size="28" fill="${ink}">${safe}</text>
</svg>`;
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

const TOUR_REQUESTOR_SIGNATURE = eApprovalTourFakeSignatureDataUrl("Maria Santos", "#1e3a5f");
const TOUR_MANAGER_SIGNATURE = eApprovalTourFakeSignatureDataUrl("James Rivera", "#14532d");

/**
 * Print / PDF sample — Title, Approvers, Attachments + signed stamps.
 */
export const eApprovalTourSamplePrintPayload: EApprovalPrintPayload = {
  document_no: E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO,
  form_name: E_APPROVAL_TOUR_SAMPLE_FORM_NAME,
  status: "Pending",
  requestor: eApprovalTourSampleRequestor.name,
  requestor_signature: TOUR_REQUESTOR_SIGNATURE,
  created_at: formatTourSampleTimestamp(eApprovalTourSampleSubmittedAt),
  brand_logo_url: null,
  print_template_kind: "generic",
  fields: [
    { key: "title", label: "Title", value: "Network Operations Handover Procedure" },
    { key: "approver_1", label: "Approver 1", value: "James Rivera" },
    { key: "approver_2", label: "Approver 2", value: "Alya Mendoza" },
    { key: "approver_3", label: "Approver 3", value: "Carlos Nguyen" },
    { key: "attachments", label: "Attachments", value: null },
  ],
  approvals: [
    {
      step: 1,
      approver: "James Rivera",
      status: "approved",
      remarks: "Approved — proceed to Approver 2.",
      signature: TOUR_MANAGER_SIGNATURE,
      acted_at: formatTourSampleTimestamp("2026-08-25T11:20:00+08:00"),
    },
    {
      step: 2,
      approver: "Alya Mendoza",
      status: "pending",
      remarks: null,
      signature: null,
      acted_at: null,
    },
    {
      step: 3,
      approver: "Carlos Nguyen",
      status: "pending",
      remarks: null,
      signature: null,
      acted_at: null,
    },
  ],
  attachments: [
    {
      id: "tour-att-1",
      field_name: "attachments",
      file_name: "sop-noc-014-rev3.pdf",
    },
    {
      id: "tour-att-2",
      field_name: "attachments",
      file_name: "sop-noc-014-rev3-appendix.pdf",
    },
  ],
  template: {
    header: { title: E_APPROVAL_TOUR_SAMPLE_FORM_NAME },
    footer: {
      showRequestorSignature: true,
      showApprovalHistory: true,
    },
    blocks: {
      signatures: [],
    },
  },
  show_approval_trail: true,
};
