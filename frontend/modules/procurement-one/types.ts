export type ProcurementDocumentTypeRow = {
  id: string;
  label: string;
  code: string;
};

export type ProcurementFormSchemaField = {
  id: string;
  type: string;
  name: string;
  label: string;
  semantic_type?: string | null;
  step_order?: number;
  validation?: Record<string, unknown> | null;
  options?: Record<string, unknown> | unknown[] | null;
};

export type ProcurementFormSchema = {
  form: {
    id: string;
    name: string;
    description: string | null;
    status: string;
    metadata: Record<string, unknown> | null;
  } | null;
  fields: ProcurementFormSchemaField[];
};

export type ProcurementDocumentKind = "purchase_requisition" | "purchase_order" | "ap_invoice";

export type ProcurementStatusRow = {
  key: string;
  label: string;
  terminal?: boolean;
};

export type ProcurementNumberingSeries = {
  prefix: string;
  padding: number;
  reset_rule: string;
  next_sequence: number;
};

export type ProcurementPlanFeatures = {
  plan_tier: string;
  enabled: boolean;
  goods_receipt: boolean;
  advanced_numbering: boolean;
  inventory: boolean;
  ap_invoices: boolean;
  payment_tracking: boolean;
  rfq_sourcing: boolean;
  vendor_contracts: boolean;
  reporting_exports: boolean;
};

export type ProcurementRfqBidVersion = {
  id: string;
  version_no: number;
  total_amount: number;
  total_amount_monthly?: number | null;
  total_amount_yearly?: number | null;
  normalized_annual_amount?: number | null;
  currency_code: string;
  validity_until: string | null;
  avg_lead_time_days: number | null;
  notes: string | null;
  submitted_via: string;
  portal_contact_name: string | null;
  captured_by: { id: string; name: string } | null;
  recorded_at: string | null;
  lines: Array<{
    rfq_line_id: string;
    description: string | null;
    quantity: number;
    unit_price: number;
    monthly_unit_price?: number | null;
    yearly_unit_price?: number | null;
    amount: number;
    amount_monthly?: number | null;
    amount_yearly?: number | null;
    normalized_annual_amount?: number | null;
    quote_basis?: string | null;
    quote_basis_label?: string | null;
    lead_time_days: number | null;
    notes: string | null;
  }>;
  attachments: Array<{
    id: string;
    file_name: string;
    mime_type: string | null;
    size_bytes: number | null;
  }>;
};

export type ProcurementRfqBidAttachment = {
  id: string;
  file_name: string;
  mime_type: string | null;
  size_bytes: number | null;
  uploaded_via: string;
};

export type ProcurementRfqScoringPolicy = {
  weight_price: number;
  weight_lead_time: number;
  weight_accreditation: number;
  weight_line_coverage: number;
  vendor_portal_enabled: boolean;
  notify_buyer_on_bid: boolean;
  notify_buyer_email: boolean;
  auto_close_at_deadline: boolean;
  vendor_inbox_enabled: boolean;
};

export type ProcurementApInvoiceMatchPolicy = {
  match_mode: "two_way" | "three_way";
  tolerance_percent: number;
  mode: "warn" | "block";
  require_grn_posted: boolean;
};

export type ProcurementInventoryPolicy = {
  inventory_mode: "none" | "simple";
  default_receipt_location_id: string | null;
  auto_create_assets_on_deploy: boolean;
};

export type ProcurementInventoryLocation = {
  id: string;
  code: string;
  name: string;
  location_kind: string;
  location_kind_label: string;
  site_id: string | null;
  is_default_receipt: boolean;
  is_active: boolean;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type ProcurementInventoryStockBalance = {
  id: string;
  location_id: string;
  location: {
    id: string;
    code: string;
    name: string;
    location_kind: string;
  } | null;
  po_line_id: string | null;
  stock_key: string;
  description: string;
  uom: string | null;
  quantity_on_hand: number;
  updated_at: string | null;
};

export type ProcurementInventoryStockMovement = {
  id: string;
  movement_type: string;
  movement_type_label: string;
  transfer_batch_id: string | null;
  location_id: string;
  location: { id: string; code: string; name: string } | null;
  counterparty_location_id: string | null;
  counterparty_location: { id: string; code: string; name: string } | null;
  grn_id: string | null;
  grn_line_id: string | null;
  po_line_id: string | null;
  asset_id: string | null;
  asset: { id: string; asset_code: string; name: string } | null;
  stock_key: string;
  description: string;
  uom: string | null;
  quantity: number;
  notes: string | null;
  metadata: Record<string, unknown>;
  created_by: { id: string; name: string } | null;
  created_at: string | null;
};

export type ProcurementVendorAccreditationPolicy = {
  enabled: boolean;
  mode: "warn" | "block";
};

export type ProcurementPrBudgetPolicy = {
  enabled: boolean;
  mode: "warn" | "block";
};

export type ProcurementContractSpendPolicy = {
  enabled: boolean;
  mode: "warn" | "block";
};

export type ProcurementExportColumnMap = {
  key: string;
  label: string;
  enabled: boolean;
};

export type ProcurementExportSchedulePolicy = {
  enabled: boolean;
  frequency: "monthly";
  day_of_month: number;
  hour: number;
  recipients: string[];
  period: "previous_month" | "current_month";
  last_run_at: string | null;
};

export type ProcurementP2pDashboard = {
  kpis: Array<{ key: string; label: string; value: number | string; tone?: string }>;
  cycle_times: Array<{ key: string; label: string; value: number | string; unit: string }>;
  open_pr_count: number;
  po_outstanding_amount: number;
  po_outstanding_count: number;
  grn_pending_count: number;
};

export type ProcurementVendorSpendDashboard = {
  period_label: string;
  total_spend: number;
  vendor_count: number;
  rows: Array<{
    vendor_code: string | null;
    vendor_name: string | null;
    po_count: number;
    total_spend: number;
    currency_code: string | null;
  }>;
};

export type ProcurementFinanceKpi = {
  key: string;
  label: string;
  value: string;
  change?: string;
  tone?: string;
};

export type ProcurementVendorEmailTemplate = {
  enabled: boolean;
  subject: string;
  body: string;
};

export type ProcurementVendorEmailTemplates = {
  auto_on_approve: boolean;
  auto_on_sent: boolean;
  po_approved: ProcurementVendorEmailTemplate;
  po_sent: ProcurementVendorEmailTemplate;
  po_cancelled: ProcurementVendorEmailTemplate;
  po_voided: ProcurementVendorEmailTemplate;
};

export type ProcurementGrReceiptPolicy = {
  tolerance_percent: number;
  mode: "warn" | "block";
};

export type ProcurementGrnMismatch = {
  type: string;
  severity: "warning" | "critical" | string;
  message: string;
  po_line_id?: string;
  description?: string;
  quantity_received?: number;
  quantity_remaining_before?: number;
  quantity_ordered?: number;
  quantity_cumulative?: number;
};

export type ProcurementGrnPrintPayload = {
  brand: string;
  document_no: string | null;
  status: string;
  status_label: string;
  po_id: string;
  po_document_no: string | null;
  supplier: string | null;
  received_by: { id: string; name: string; email: string | null } | null;
  received_at: string | null;
  posted_at: string | null;
  project_id: string | null;
  rollout_id: string | null;
  site_id: string | null;
  gps_latitude: number | null;
  gps_longitude: number | null;
  gps_accuracy_meters: number | null;
  notes: string | null;
  receipt_warning: string | null;
  mismatches: ProcurementGrnMismatch[];
  lines: Array<{
    description: string;
    uom: string | null;
    quantity_ordered: number;
    quantity_received: number;
    line_notes: string | null;
  }>;
  attachments: Array<{ file_name: string; field_name: string }>;
  printed_at: string;
};

export type ProcurementPoLineReceiptSummary = {
  po_line_id: string;
  description: string;
  quantity_ordered: number;
  quantity_received: number;
  quantity_remaining: number;
};

export type ProcurementLifecycleEvent = {
  id: string;
  action: string;
  reason: string | null;
  document_no: string | null;
  actor: { id: string; name: string } | null;
  metadata: Record<string, unknown>;
  created_at: string | null;
};

export type ProcurementPrLine = {
  id?: string;
  line_order?: number;
  description: string;
  quantity: number;
  unit_price: number;
  amount?: number;
};

export type ProcurementPrAttachment = {
  id: string;
  field_name: string;
  file_name: string;
  mime_type: string | null;
  size_bytes: number | null;
  e_approval_attachment_id: string | null;
};

export type ProcurementPrListRow = {
  id: string;
  document_no: string | null;
  title: string;
  status: string;
  status_label: string;
  department: string | null;
  urgency: string | null;
  estimated_total: number;
  currency: string;
  requestor: { id: string; name: string } | null;
  submitted_at: string | null;
  approved_at: string | null;
  updated_at: string | null;
};

export type ProcurementPrDetail = ProcurementPrListRow & {
  justification: string | null;
  project_id: string | null;
  rollout_id: string | null;
  site_id: string | null;
  boq_line_id: string | null;
  e_approval_submission_id: string | null;
  e_approval_form_id: string | null;
  compose_values?: Record<string, string>;
  committed_po_amount: number;
  open_po_balance: number | null;
  active_rfq: {
    id: string;
    document_no: string | null;
    title: string;
    status: string;
    status_label: string;
    updated_at: string | null;
  } | null;
  rejected_at: string | null;
  cancelled_at: string | null;
  voided_at?: string | null;
  void_reason?: string | null;
  lifecycle_reason?: string | null;
  lifecycle_events?: ProcurementLifecycleEvent[];
  budget_check: {
    policy_enabled: boolean;
    budget_total: number | null;
    committed: number | null;
    available: number | null;
  };
  lines: ProcurementPrLine[];
  attachments: ProcurementPrAttachment[];
};

export type ProcurementPoLine = {
  id?: string;
  line_order?: number;
  item?: string | null;
  description: string;
  uom?: string | null;
  quantity: number;
  unit_price: number;
  discount?: number;
  amount?: number;
  pr_id?: string | null;
  pr_line_id?: string | null;
};

export type ProcurementPoPrLink = {
  id: string;
  document_no: string | null;
  title: string | null;
  allocated_amount: number;
  e_approval_submission_id?: string | null;
};

export type ProcurementPoListRow = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  vendor_code: string | null;
  vendor_name: string | null;
  grand_total: number;
  currency_code: string;
  requestor: { id: string; name: string } | null;
  submitted_at: string | null;
  approved_at: string | null;
  updated_at: string | null;
};

export type ProcurementPoDetail = ProcurementPoListRow & {
  supplier: string | null;
  ship_to: string | null;
  delivery_date: string | null;
  payment_terms: string | null;
  exchange_rate: number;
  delivery_location: string | null;
  vatable_amount: number;
  vat_exempt_amount: number;
  zero_rated_amount: number;
  vat_rate: number;
  vat_amount: number;
  total_vat_inclusive: number;
  less_discount: number;
  total_amount: number;
  e_approval_submission_id: string | null;
  parent_submission_id?: string | null;
  e_approval_form_id: string | null;
  compose_values?: Record<string, string>;
  sent_at: string | null;
  cancelled_at: string | null;
  voided_at?: string | null;
  void_reason?: string | null;
  lifecycle_reason?: string | null;
  lifecycle_events?: ProcurementLifecycleEvent[];
  metadata: Record<string, unknown>;
  purchase_requisitions: ProcurementPoPrLink[];
  lines: ProcurementPoLine[];
  line_receipt_summary?: ProcurementPoLineReceiptSummary[];
  goods_receipt_count?: number;
  contract_id?: string | null;
  contract?: ProcurementPoContractSummary | null;
};

export type ProcurementPoContractSummary = {
  id: string;
  document_no: string | null;
  title: string;
  status: string;
  spend_ceiling: number | null;
  committed_po_amount: number;
  available_spend: number | null;
  vendor: { id: string; vendor_code: string | null; company_name: string | null } | null;
};

export type ProcurementGrnLine = {
  id?: string;
  line_order?: number;
  po_line_id: string;
  description: string;
  uom?: string | null;
  quantity_ordered: number;
  quantity_received: number;
  line_notes?: string | null;
};

export type ProcurementGrnAttachment = {
  id: string;
  field_name: string;
  file_name: string;
  mime_type: string | null;
  size_bytes: number | null;
};

export type ProcurementGrnListRow = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  po_id: string;
  po_document_no: string | null;
  po_supplier: string | null;
  received_by: { id: string; name: string } | null;
  received_at: string | null;
  posted_at: string | null;
  updated_at: string | null;
};

export type ProcurementGrnDetail = ProcurementGrnListRow & {
  project_id: string | null;
  rollout_id: string | null;
  site_id: string | null;
  inventory_location_id?: string | null;
  inventory_location?: ProcurementInventoryLocation | null;
  stock_movements?: ProcurementInventoryStockMovement[];
  gps_latitude: number | null;
  gps_longitude: number | null;
  gps_accuracy_meters: number | null;
  notes: string | null;
  receipt_warning?: string | null;
  mismatches?: ProcurementGrnMismatch[];
  metadata: Record<string, unknown>;
  purchase_order: {
    id: string;
    document_no: string | null;
    status: string;
    supplier: string | null;
    line_receipt_summary: ProcurementPoLineReceiptSummary[];
  } | null;
  lines: ProcurementGrnLine[];
  attachments: ProcurementGrnAttachment[];
};

export type ProcurementVendorListRow = {
  id: string;
  vendor_code: string;
  company_name: string;
  tax_id: string;
  category: string | null;
  accreditation_status: string;
  accreditation_status_label: string;
  accredited_at: string | null;
  accreditation_expires_at: string | null;
  is_active: boolean;
  updated_at: string | null;
};

export type ProcurementVendorAccreditationEvent = {
  id: string;
  status_from: string | null;
  status_to: string;
  reason: string | null;
  actor_user_id: string | null;
  submission_id: string | null;
  created_at: string | null;
};

export type ProcurementVendorDocument = {
  id: string;
  document_id: string | null;
  e_approval_attachment_id: string | null;
  document_kind: string;
  label: string;
  file_name: string | null;
  linked_at: string | null;
};

export type ProcurementVendorDetail = ProcurementVendorListRow & {
  schema_version: number;
  master_data_row_id: string | null;
  source_submission_id: string | null;
  contact: Record<string, string>;
  banking: Record<string, string>;
  address: Record<string, string>;
  profile: Record<string, unknown>;
  accreditation_history: ProcurementVendorAccreditationEvent[];
  documents: ProcurementVendorDocument[];
  payment_history?: ProcurementPaymentRequestListRow[];
};

export type ProcurementOneSettings = {
  module_message: string;
  vendor_accreditation_policy: ProcurementVendorAccreditationPolicy;
  pr_budget_policy: ProcurementPrBudgetPolicy;
  vendor_email_templates: ProcurementVendorEmailTemplates;
  gr_receipt_policy: ProcurementGrReceiptPolicy;
  inventory_policy: ProcurementInventoryPolicy;
  ap_invoice_match_policy: ProcurementApInvoiceMatchPolicy;
  rfq_scoring_policy: ProcurementRfqScoringPolicy;
  contract_spend_policy: ProcurementContractSpendPolicy;
  export_column_maps: Record<string, ProcurementExportColumnMap[]>;
  export_schedule: ProcurementExportSchedulePolicy;
  procurement_policy: ProcurementFinancialPolicy;
  document_types: ProcurementDocumentTypeRow[];
  status_catalogs: Record<string, ProcurementStatusRow[]>;
  numbering_series: Record<string, ProcurementNumberingSeries>;
};

export type ProcurementExpenseTypeOption = {
  value: string;
  label: string;
};

export type ProcurementOneMetadata = {
  document_types: ProcurementDocumentTypeRow[];
  status_catalogs: Record<string, ProcurementStatusRow[]>;
  numbering_series: Record<string, ProcurementNumberingSeries>;
  reset_rules: string[];
  plan_features: ProcurementPlanFeatures;
  cost_centers: ProcurementCostCenter[];
  expense_types: ProcurementExpenseTypeOption[];
};

export type ProcurementOneDashboardResponse = {
  kpis: Array<{ key: string; label: string; value: number | string; tone?: string }>;
  budget_kpis?: Array<{ key: string; label: string; value: number | string; tone?: string }>;
  ap_kpis?: Array<{ key: string; label: string; value: number | string; tone?: string }>;
  ap_aging?: ProcurementApAgingSnapshot;
  payment_kpis?: Array<{ key: string; label: string; value: number | string; tone?: string }>;
  contract_kpis?: Array<{ key: string; label: string; value: number | string; tone?: string }>;
  p2p?: ProcurementP2pDashboard;
  vendor_spend?: ProcurementVendorSpendDashboard;
  finance_kpis?: ProcurementFinanceKpi[];
  plan_features?: ProcurementPlanFeatures;
  message: string;
};

export type ProcurementApAgingBucket = {
  key: string;
  label: string;
  count: number;
  amount: number;
};

export type ProcurementApAgingSnapshot = {
  buckets: ProcurementApAgingBucket[];
  total_open: number;
  total_count: number;
};

export type ProcurementApInvoiceListRow = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  vendor_invoice_no: string | null;
  po_id: string;
  po_document_no: string | null;
  po_supplier: string | null;
  grn_id: string | null;
  grand_total: number;
  match_mode: string;
  match_mode_label: string;
  match_status: string;
  invoice_date: string | null;
  due_date: string | null;
  approved_at: string | null;
  updated_at: string | null;
};

export type ProcurementApInvoiceDetail = ProcurementApInvoiceListRow & {
  currency_code: string;
  exchange_rate: number;
  vatable_amount: number;
  vat_amount: number;
  grand_total: number;
  match_variance_amount: number;
  e_approval_submission_id: string | null;
  compose_values?: Record<string, string>;
  notes: string | null;
  metadata: Record<string, unknown>;
  lines: Array<{
    id: string;
    po_line_id: string;
    description: string;
    quantity_invoiced: number;
    unit_price: number;
    amount: number;
  }>;
  credit_notes: Array<{
    id: string;
    document_no: string | null;
    status: string;
    amount: number;
    vendor_credit_note_no: string | null;
  }>;
  payment_balance?: {
    grand_total: number;
    credit_total: number;
    encumbered_total: number;
    paid_total: number;
    open_payable: number;
  };
  payment_requests?: ProcurementPaymentRequestListRow[];
  purchase_order: {
    id: string;
    document_no: string | null;
    supplier: string | null;
    grand_total: number;
    invoiced_total: number;
  } | null;
  goods_receipt: { id: string; document_no: string | null; status: string } | null;
};

export type ProcurementCreditNote = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  po_id: string;
  ap_invoice_id: string | null;
  amount: number;
  vendor_credit_note_no: string | null;
  credit_date: string | null;
  reason: string | null;
};

export type ProcurementPaymentRequestListRow = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  ap_invoice_id: string;
  ap_invoice_document_no: string | null;
  ap_vendor_invoice_no: string | null;
  payment_batch_id: string | null;
  payment_batch_document_no: string | null;
  vendor_code: string | null;
  vendor_name: string | null;
  amount: number;
  currency_code: string;
  scheduled_date: string | null;
  paid_at: string | null;
  reconciled_at: string | null;
  payment_reference: string | null;
  requestor: { id: string; name: string } | null;
  updated_at: string | null;
};

export type ProcurementPaymentRequestDetail = ProcurementPaymentRequestListRow & {
  notes: string | null;
  approved_at: string | null;
  metadata: Record<string, unknown>;
  ap_invoice: {
    id: string;
    document_no: string | null;
    vendor_invoice_no: string | null;
    grand_total: number;
    balance: {
      grand_total: number;
      credit_total: number;
      encumbered_total: number;
      paid_total: number;
      open_payable: number;
    };
  } | null;
  audit_trail: Array<{
    id: string;
    action: string;
    reason: string | null;
    actor: { id: string; name: string } | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
  }>;
};

export type ProcurementPaymentBatchListRow = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  scheduled_date: string | null;
  total_amount: number;
  currency_code: string;
  payment_request_count: number;
  exported_at: string | null;
  reconciled_at: string | null;
  created_by: { id: string; name: string } | null;
  updated_at: string | null;
};

export type ProcurementPaymentBatchDetail = ProcurementPaymentBatchListRow & {
  notes: string | null;
  payment_requests: ProcurementPaymentRequestListRow[];
  audit_trail: Array<{
    id: string;
    action: string;
    reason: string | null;
    actor: { id: string; name: string } | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
  }>;
};

export type ProcurementRfqListRow = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  title: string;
  pr_id: string | null;
  pr_document_no: string | null;
  pr_title: string | null;
  currency_code: string;
  estimated_total: number;
  bidding_closes_at: string | null;
  invited_vendor_count: number;
  bid_count: number;
  awarded_vendor_name: string | null;
  updated_at: string | null;
};

export type ProcurementRfqComparisonRow = {
  bid_id: string;
  vendor_id: string;
  vendor_code: string | null;
  vendor_name: string | null;
  status: string;
  status_label: string;
  total_amount: number;
  total_amount_monthly?: number | null;
  total_amount_yearly?: number | null;
  normalized_annual_amount?: number | null;
  comparison_amount?: number | null;
  currency_code: string;
  avg_lead_time_days: number | null;
  line_coverage_percent: number;
  accreditation_status: string;
  scores: {
    price: number;
    lead_time: number;
    accreditation: number;
    line_coverage: number;
    weighted_total: number;
  };
  rank: number;
};

export type ProcurementRfqDetail = ProcurementRfqListRow & {
  description: string | null;
  bidding_opens_at: string | null;
  awarded_at: string | null;
  award_notes: string | null;
  notes: string | null;
  metadata: Record<string, unknown>;
  requestor: { id: string; name: string } | null;
  lines_source?: "purchase_requisition" | "rfq";
  lines_synced_from_pr_at?: string | null;
  lines: Array<{
    id: string;
    line_order: number;
    pr_line_id: string | null;
    description: string;
    uom: string | null;
    quantity: number;
    target_unit_price: number | null;
    quote_basis?: string | null;
    quote_basis_label?: string | null;
  }>;
  invited_vendors: Array<{
    id: string;
    vendor_id: string;
    vendor_code: string | null;
    vendor_name: string | null;
    invitation_status: string;
    invited_at: string | null;
    responded_at: string | null;
    invitation_email: string | null;
    invitation_sent_at: string | null;
    invitation_opened_at: string | null;
    submitted_via: string | null;
    portal_contact_name: string | null;
  }>;
  vendor_portal_enabled: boolean;
  bids: Array<{
    id: string;
    vendor_id: string;
    vendor_code: string | null;
    vendor_name: string | null;
    status: string;
    status_label: string;
    total_amount: number;
    total_amount_monthly?: number | null;
    total_amount_yearly?: number | null;
    normalized_annual_amount?: number | null;
    currency_code: string;
    validity_until: string | null;
    avg_lead_time_days: number | null;
    submitted_at: string | null;
    notes: string | null;
    version_count?: number;
    current_version_no?: number;
    attachments?: ProcurementRfqBidAttachment[];
    lines: Array<{
      id: string;
      rfq_line_id: string;
      quantity: number;
      unit_price: number;
      monthly_unit_price?: number | null;
      yearly_unit_price?: number | null;
      amount: number;
      amount_monthly?: number | null;
      amount_yearly?: number | null;
      normalized_annual_amount?: number | null;
      quote_basis?: string | null;
      quote_basis_label?: string | null;
      lead_time_days: number | null;
      notes: string | null;
    }>;
  }>;
  comparison_matrix: {
    policy: ProcurementRfqScoringPolicy;
    rows: ProcurementRfqComparisonRow[];
    recommended_bid_id: string | null;
  };
  purchase_order: { id: string; document_no: string | null; status: string } | null;
  audit_trail: Array<{
    id: string;
    action: string;
    reason: string | null;
    actor: { id: string; name: string } | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
  }>;
};

export type ProcurementCostCenter = {
  id: string;
  code: string;
  name: string;
  is_active: boolean;
  notes: string | null;
};

export type ProcurementBudgetLine = {
  id: string;
  project_id: string | null;
  rollout_id: string | null;
  cost_center_id: string | null;
  cost_center: { id: string; code: string; name: string } | null;
  line_code: string | null;
  description: string;
  expense_type: string;
  expense_type_label: string;
  budget_amount: number;
  is_active: boolean;
  notes: string | null;
};

export type ProcurementBudgetUtilization = {
  budget_total: number | null;
  committed: number;
  committed_pr: number;
  committed_po: number;
  available: number | null;
  utilization_percent: number | null;
  source: string;
};

export type ProcurementFinancialPolicy = {
  budget: { enabled: boolean; mode: "warn" | "block" };
  po_overspend: { mode: "warn" | "block"; max_overspend_percent: number };
  liquidation: {
    requires_parent: boolean;
    overspend_mode: "warn" | "block";
    max_overspend_percent: number;
  };
};

export type ProcurementContractListRow = {
  id: string;
  document_no: string | null;
  status: string;
  status_label: string;
  title: string;
  vendor: { id: string; vendor_code: string | null; company_name: string | null } | null;
  site: { id: string; site_code: string | null; name: string | null } | null;
  spend_ceiling: number | null;
  committed_po_amount: number;
  currency_code: string;
  effective_from: string | null;
  end_date: string | null;
  updated_at: string | null;
};

export type ProcurementContractDetail = ProcurementContractListRow & {
  description: string | null;
  owner: { id: string; name: string } | null;
  primary_document_id: string | null;
  primary_document: {
    id: string;
    title: string;
    expires_at: string | null;
    site_id: string | null;
  } | null;
  available_spend: number | null;
  live_committed_po_amount: number;
  activated_at: string | null;
  terminated_at: string | null;
  termination_reason: string | null;
  purchase_order_count: number;
  documents: Array<{
    id: string;
    document_id: string | null;
    document_kind: string;
    label: string;
    file_name: string | null;
    linked_at: string | null;
  }>;
  metadata: Record<string, unknown>;
  lifecycle_events?: ProcurementLifecycleEvent[];
  binder_node_key: string;
};

export type ProcurementContractExpiringSummary = {
  within_30: number;
  within_60: number;
  within_90: number;
};
