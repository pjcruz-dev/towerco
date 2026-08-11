import type { PaginatedMeta } from "@/lib/api/paginated";

export type EApprovalFormFieldInput = {
  id?: string;
  type: string;
  name: string;
  label: string;
  semantic_type?: string | null;
  step_order?: number;
  validation?: Record<string, unknown> | null;
  /** Legacy imports may store a string list at the root; modern fields use `{ choices: [...] }`. */
  options?: Record<string, unknown> | unknown[] | null;
};

export type EApprovalWorkflowStepInput = {
  id?: string;
  type: string;
  approverId?: string;
  source_field?: string;
  mappings?: Record<string, string>;
  default_approver_id?: string;
  /** Used when manager / field / role (or fixed user) primary resolution fails. */
  fallback_approver_id?: string;
  /** Parallel band completion: all (default) | any | n_of_m */
  parallel_mode?: "all" | "any" | "n_of_m";
  /** Required approvals when parallel_mode is n_of_m */
  parallel_quorum?: number;
  /** How `when` conditions combine: and (default) | or */
  when_logic?: "and" | "or";
  when?: EApprovalWorkflowCondition[];
  step_order?: number;
  condition?: Record<string, unknown> | null;
};

export type EApprovalWorkflowConditionOperator =
  | "equals"
  | "not_equals"
  | "contains"
  | "gt"
  | "gte"
  | "lt"
  | "lte"
  | "is_empty"
  | "is_not_empty"
  | "in";

export type EApprovalWorkflowCondition = {
  field: string;
  operator: EApprovalWorkflowConditionOperator;
  value?: string;
};

export type EApprovalFormListRow = {
  id: string;
  name: string;
  description: string | null;
  category: string;
  status: string;
  schema_version: number;
  created_at: string | null;
  /** True when an active external share link can be re-copied by submitters. */
  has_shareable_public_link?: boolean | null;
};

export type EApprovalFormRevision = {
  revision: number;
  label: string;
  event: string;
  status: string;
  schema_version: number;
  field_count: number;
  step_count: number;
  saved_at?: string;
  saved_by?: { id: string; name: string };
  snapshot?: Record<string, unknown>;
};

export type EApprovalFormTemplate = {
  id: string;
  name: string;
  description: string;
  category: string;
  field_count: number;
  step_count: number;
  source?: "system" | "tenant";
  editable?: boolean;
  updated_at?: string | null;
};

export type EApprovalFormTemplateDefinition = {
  id: string;
  name: string;
  description: string | null;
  category: string;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  source: "tenant";
  editable: boolean;
};

export type EApprovalFormDetail = EApprovalFormListRow & {
  submissions_count?: number;
  pending_submissions_count?: number;
  accepts_new_submissions?: boolean;
  revisions?: EApprovalFormRevision[];
  metadata_json: Record<string, unknown> | null;
  restricted_to?: string | null;
  brand_logo_url: string | null;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  owner_code: string;
  doc_type_code: string;
  doc_no_custom_enabled?: boolean;
  doc_no_template?: string | null;
};

export type EApprovalOpenCashAdvance = {
  id: string;
  document_no: string;
  created_at: string | null;
  requestor_id: string;
  requestor_name: string | null;
  requested_amount: number;
  reimbursed_amount: number;
  open_balance: number;
  prefill_values?: Record<string, string>;
};

export type EApprovalOpenPurchaseRequisition = {
  id: string;
  document_no: string;
  created_at: string | null;
  requestor_id: string;
  requestor_name: string | null;
  requisition_title: string | null;
  estimated_total: number;
  committed_amount: number;
  open_balance: number;
  prefill_values?: Record<string, string>;
};

export type EApprovalSubmissionListRow = {
  id: string;
  document_no: string;
  status: string;
  current_step: number;
  returned_from_step?: number | null;
  force_full_restart?: boolean;
  approval_cycle?: number;
  last_revision_routing?: string | null;
  last_revision_routing_reason?: string | null;
  form_id: string;
  form_name?: string;
  requestor: { id: string; name: string; email: string } | null;
  created_at: string | null;
  updated_at?: string | null;
};

export type EApprovalRelatedSubmissionRow = {
  id: string;
  document_no: string;
  status: string;
  form_id: string;
  form_name: string | null;
  form_family?: string | null;
  relationship: "parent" | "child";
  amount_label: string | null;
  amount_value: string | null;
  created_at: string | null;
};

export type EApprovalRelatedSubmissionsSummary = {
  kind: "cash_advance_balance" | "purchase_requisition_budget";
  total_label: string;
  total_amount: number;
  committed_label: string;
  committed_amount: number;
  open_balance: number;
};

export type EApprovalRelatedSubmissions = {
  parent: EApprovalRelatedSubmissionRow | null;
  children: EApprovalRelatedSubmissionRow[];
  context_form_family?: string | null;
  summary?: EApprovalRelatedSubmissionsSummary | null;
};

export type EApprovalDocumentLinkRow = {
  id: string;
  direction: "outgoing" | "incoming";
  link_type: string;
  submission_id: string;
  document_no?: string | null;
  form_name?: string | null;
  status?: string | null;
  created_at?: string | null;
  /** Legacy outgoing aliases */
  target_submission_id?: string;
  target_document_no?: string | null;
  target_form_name?: string | null;
  target_status?: string | null;
  /** Legacy incoming aliases */
  source_submission_id?: string;
  source_document_no?: string | null;
  source_form_name?: string | null;
  source_status?: string | null;
};

export type EApprovalRelatedFormNavigation = {
  form_id: string;
  form_name: string;
  form_family?: string | null;
  href: string;
};

export type EApprovalSubmissionDetail = EApprovalSubmissionListRow & {
  viewer_is_requestor?: boolean;
  viewer_pending_approval_id?: string | null;
  /** Approver feedback when status is returned or rejected. */
  revision_remarks?: string | null;
  revision_remarks_at?: string | null;
  revision_remarks_by?: string | null;
  revision_config?: {
    routing: "restart_from_start" | "resume_returning_step";
    material_fields: string[];
    approver_can_force_full_restart: boolean;
  };
  revision_routing_applied?: {
    routing: string;
    reason: string | null;
    current_step: number | null;
  } | null;
  form_schema_version_at_submit?: number | null;
  workflow_version_id?: string | null;
  parent_submission_id?: string | null;
  manual_follow_up_cooldown_minutes?: number;
  manual_follow_up_last_at?: string | null;
  manual_follow_up_next_allowed_at?: string | null;
  form_fields?: {
    id: string;
    type: string | null;
    name: string | null;
    label: string | null;
    semantic_type?: string | null;
    validation?: Record<string, unknown> | null;
    options?: Record<string, unknown> | unknown[] | null;
  }[];
  related_submissions?: EApprovalRelatedSubmissions;
  related_form_navigation?: EApprovalRelatedFormNavigation[];
  document_links?: EApprovalDocumentLinkRow[];
  incoming_document_links?: EApprovalDocumentLinkRow[];
  values: {
    field_id: string;
    field_name: string | null;
    field_type?: string | null;
    label: string | null;
    value: string | null;
    display_value?: string | null;
    display_subtitle?: string | null;
  }[];
  approvals: EApprovalApprovalRow[];
  attachments: {
    id: string;
    field_name: string | null;
    file_name: string;
    file_path: string;
    metadata?: {
      lat?: number;
      lng?: number;
      captured_at?: string;
      caption?: string;
      slot?: string;
    } | null;
  }[];
};

export type EApprovalApprovalRow = {
  id: string;
  status: string;
  /** Raw approval step status when inbox row is deduped by submission. */
  approval_status?: string;
  approval_cycle?: number;
  is_prior_cycle?: boolean;
  remarks: string | null;
  signature?: string | null;
  acted_at: string | null;
  step_order: number | null;
  /** Parallel band completion rule from the workflow step (when present). */
  parallel_mode?: "all" | "any" | "n_of_m" | null;
  parallel_quorum?: number | null;
  approver: { id: string; name: string; email: string } | null;
  submission: {
    id: string;
    document_no: string;
    status: string;
    form_name?: string;
  } | null;
};

export type EApprovalDashboardAction = {
  id: string;
  label: string;
  count: number;
  href: string;
  priority?: "high" | "normal" | "medium";
};

export type EApprovalAuditRow = {
  id: string;
  action: string;
  target_id: string | null;
  remarks: string | null;
  created_at: string | null;
  user: { id: string; name: string; email: string } | null;
};

export type EApprovalDashboardQueueItem = {
  id: string;
  submission_id: string | null;
  document_no: string | null;
  form_name: string | null;
  requestor_name?: string | null;
  status: string;
  step_order?: number | null;
  waiting_since: string | null;
  href: string;
};

export type EApprovalDashboardCapabilities = {
  can_approve: boolean;
  can_create: boolean;
  can_manage_forms: boolean;
  can_audit: boolean;
};

export type EApprovalFinanceProcurementCounts = {
  open_cash_advances: number;
  unliquidated_cash_advances: number;
  prs_without_po: number;
};

export type EApprovalDashboardKpi = {
  key: string;
  label: string;
  value: string;
  change?: string;
  tone?: "neutral" | "success" | "warning" | "danger";
  href?: string | null;
};

export type EApprovalDashboardResponse = {
  kpis: EApprovalDashboardKpi[];
  finance_kpis?: EApprovalDashboardKpi[];
  finance_counts?: EApprovalFinanceProcurementCounts;
  queues?: {
    awaiting_approval: EApprovalDashboardQueueItem[];
    my_attention: EApprovalDashboardQueueItem[];
  };
  capabilities?: EApprovalDashboardCapabilities;
  actions: EApprovalDashboardAction[];
  recent_audit: { id: string; action: string; target_id: string | null; user_name?: string; created_at: string | null }[];
  phase: string;
  message: string;
};

export type EApprovalNotificationCategory = "action" | "update";

export type EApprovalNotificationRow = {
  id: string;
  type: string;
  category: EApprovalNotificationCategory | string;
  submission_id: string | null;
  document_no: string | null;
  form_name: string | null;
  actor_user_id: string | null;
  actor_name: string | null;
  message: string;
  body_preview: string | null;
  href: string | null;
  is_read: boolean;
  created_at: string | null;
};

export type EApprovalMeProfile = {
  user_id: string;
  signature: string | null;
  signature_source: "profile" | "last_approval" | null;
  delegations?: Record<string, unknown>[];
  attachments?: Record<string, unknown>[];
  public_ui?: Record<string, unknown>;
};

export type EApprovalAssignableUser = {
  id: string;
  name: string;
  email: string;
  roles: string[];
};

export type EApprovalPdfLayoutRow = {
  key: string;
  label: string;
  visible: boolean;
  fieldType?: string;
};

export type EApprovalPdfLayoutResponse = {
  layout: EApprovalPdfLayoutRow[];
  layout_persisted?: boolean;
  template: Record<string, unknown>;
  active_preset_id: string;
  presets: Record<string, unknown>[];
  updated_at: string | null;
  updated_by_name: string | null;
};

export type EApprovalPrintField = {
  key: string;
  label: string;
  value: string | null;
};

export type EApprovalPrintApprovalRow = {
  step: number | null;
  approver: string | null;
  status: string;
  remarks?: string | null;
  signature?: string | null;
  acted_at: string | null;
};

export type EApprovalPrintAttachmentRow = {
  id: string;
  file_name: string;
  field_name: string | null;
  metadata?: {
    lat?: number;
    lng?: number;
    captured_at?: string;
    caption?: string;
    slot?: string;
  } | null;
};

export type EApprovalPrintGrid = {
  key: string;
  label: string;
  columns: string[];
  rows: string[][];
};

export type EApprovalPrintPayload = {
  document_no: string;
  form_name: string | null;
  status: string;
  requestor: string | null;
  requestor_signature?: string | null;
  created_at: string | null;
  brand_logo_url?: string | null;
  print_template_kind?: string | null;
  fields: EApprovalPrintField[];
  grids?: EApprovalPrintGrid[];
  approvals: EApprovalPrintApprovalRow[];
  attachments?: EApprovalPrintAttachmentRow[];
  template: Record<string, unknown>;
  show_approval_trail: boolean;
};

export type EApprovalHealthResponse = {
  module: string;
  phase: string;
  status: "ready" | "degraded";
  schema_ready: boolean;
  tables: Record<string, boolean>;
};

export type EApprovalPaginated<T> = {
  data: T[];
  meta: PaginatedMeta;
};

export type EApprovalCommentRow = {
  id: string;
  message: string;
  user_name: string;
  created_at: string | null;
  replies: { id: string; message: string; user_name: string; created_at: string | null }[];
};
