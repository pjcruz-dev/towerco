import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";

export type DynPrintSettings = {
  list_label: string;
  document_title: string;
  layout: string;
  status_stamp: boolean;
  signatures: boolean;
  signature_style?: string;
  company_name: string | null;
  company_address: string | null;
  subtitle_fields: string[];
  subtitle_prefix?: string | null;
  title_align?: string;
  highlight_fields?: string[];
  currency_fields?: string[];
  terms_paragraphs?: string[];
  related_expand?: Array<{
    field: string;
    title: string;
    fields: string[];
  }>;
  workflow_stages?: Array<{
    stage: string;
    plan: string | null;
    actual: string | null;
  }>;
  milestone_fields?: string[];
  remarks_field?: string | null;
  show_site_assignment?: boolean;
  department?: string | null;
  /** Portrait or landscape default for print preview. */
  orientation?: "portrait" | "landscape" | null;
  /** Metacoresoft-style HTML body with {{tokens}}. When set, print page prefers this over layout presets. */
  template_html?: string | null;
  /** Optional CSS for template_html (Styles tab). */
  template_css?: string | null;
  /** Named template id (multi-template per entity). */
  id?: string;
  /** Display name in Manage Printables list. */
  name?: string;
  updated_at?: string | null;
  /** All printables for this entity (Metacoresoft multi-template). */
  templates?: Array<DynPrintSettings & { id: string; name: string; updated_at?: string | null }>;
  default_template_id?: string | null;
};

export type DynEntitySummary = {
  id: string;
  slug: string;
  name: string;
  description: string | null;
  module_pack: string;
  storage_mode: string;
  is_active: boolean;
  sort_order: number;
  related_tabs?: Array<Record<string, unknown>>;
  print_settings?: DynPrintSettings;
  created_at?: string | null;
  updated_at?: string | null;
};

export type DynConditionalRule = {
  action: "show" | "hide" | "require";
  field: string;
  op: "eq" | "neq" | "empty" | "not_empty";
  value?: string;
};

export type DynRelationFilterOp = "eq" | "neq" | "gt" | "lt" | "contains" | "in";

export type DynRelationFilter = {
  field: string;
  op: DynRelationFilterOp;
  value: string;
};

export type DynField = {
  id: string;
  entity_id: string;
  name: string;
  label: string;
  type: string;
  is_required: boolean;
  is_system_field?: boolean;
  show_in_table: boolean;
  is_filterable: boolean;
  is_key?: boolean;
  calculate_totals?: boolean;
  field_order: number;
  column_span: number;
  form_group_id: string | null;
  view_group_id?: string | null;
  options: unknown;
  placeholder?: string | null;
  target_entity_id?: string | null;
  target_entity_slug?: string | null;
  conditional_rules?: DynConditionalRule[] | { rules?: DynConditionalRule[] } | null;
};

export type DynResolvedRelation = {
  id: string;
  title: string | null;
  entity_slug: string | null;
};

export type DynEntityDetail = DynEntitySummary & {
  fields: DynField[];
  field_groups: Array<{
    id: string;
    name: string;
    description?: string | null;
    icon?: string | null;
    applies_to_form: boolean;
    applies_to_view: boolean;
    start_collapsed?: boolean;
    sort_order: number;
  }>;
  record_count?: number;
};

export type DynRecordListRow = {
  id: string;
  entity_id: string;
  entity_slug: string;
  status: string | null;
  title: string | null;
  parent_record_id: string | null;
  source_external_id?: string | null;
  columns: Record<string, unknown>;
  updated_at: string | null;
  created_at: string | null;
};

export type DynRecordDetail = {
  id: string;
  entity: DynEntityDetail;
  status: string | null;
  title: string | null;
  values: Record<string, unknown>;
  resolved_relations?: Record<string, DynResolvedRelation>;
  workflow_actions?: Array<{
    id: string;
    label: string;
    from_status: string;
    to_status: string;
    confirm?: string | null;
    variant?: string;
  }>;
  parent_record_id: string | null;
  created_by?: string | null;
  created_by_name?: string | null;
  updated_by?: string | null;
  updated_by_name?: string | null;
  updated_at: string | null;
  created_at: string | null;
};

export async function fetchDynEntities(params?: {
  module_pack?: string;
  active_only?: boolean;
}): Promise<DynEntitySummary[]> {
  const response = await apiClient.get<{ data: DynEntitySummary[] }>("/dynamic-entities/entities", {
    params,
  });
  return response.data.data;
}

export type DynRelationshipGraphNode = {
  id: string;
  slug: string;
  name: string;
  module_pack: string;
  fields: Array<{
    id: string;
    name: string;
    label: string;
    type: string;
    is_key: boolean;
    target_entity_id: string | null;
  }>;
};

export type DynRelationshipGraphEdge = {
  id: string;
  kind: "relationship" | "related_tab" | string;
  source_entity_id: string;
  target_entity_id: string;
  field_id: string | null;
  field_name: string | null;
  field_label: string | null;
  cardinality: string;
  related_tab_label?: string | null;
  usage_count?: number;
  soft_disabled?: boolean;
};

export type DynRelationshipGraphLayout = {
  positions: Record<string, { x: number; y: number }>;
  viewport: { x?: number; y?: number; zoom?: number } | null;
};

export type DynRelationshipGraph = {
  nodes: DynRelationshipGraphNode[];
  edges: DynRelationshipGraphEdge[];
  layout: Record<string, { x: number; y: number }>;
  viewport: { x?: number; y?: number; zoom?: number } | null;
};

export async function fetchDynRelationshipGraph(params?: {
  module_pack?: string;
}): Promise<DynRelationshipGraph> {
  const response = await apiClient.get<{ data: DynRelationshipGraph }>(
    "/dynamic-entities/relationship-graph",
    { params },
  );
  return response.data.data;
}

export async function saveDynRelationshipGraphLayout(payload: {
  positions: Record<string, { x: number; y: number }>;
  viewport?: { x?: number; y?: number; zoom?: number } | null;
  reset?: boolean;
}): Promise<DynRelationshipGraphLayout> {
  const response = await apiClient.put<{ data: DynRelationshipGraphLayout }>(
    "/dynamic-entities/relationship-graph/layout",
    payload,
  );
  return response.data.data;
}

export async function createDynRelationshipEdge(payload: {
  source_entity_id: string;
  target_entity_id: string;
  label?: string;
  name?: string;
  related_tab_label?: string;
  sync_related_tab?: boolean;
}): Promise<DynRelationshipGraphEdge> {
  const response = await apiClient.post<{ data: DynRelationshipGraphEdge }>(
    "/dynamic-entities/relationship-graph/edges",
    payload,
  );
  return response.data.data;
}

export async function updateDynRelationshipEdge(
  fieldId: string,
  payload: {
    label?: string;
    target_entity_id?: string | null;
    related_tab_label?: string;
    sync_related_tab?: boolean;
  },
): Promise<DynRelationshipGraphEdge> {
  const response = await apiClient.patch<{ data: DynRelationshipGraphEdge }>(
    `/dynamic-entities/relationship-graph/edges/${fieldId}`,
    payload,
  );
  return response.data.data;
}

export async function deleteDynRelationshipEdge(
  fieldId: string,
  options?: { force?: boolean; soft_disable?: boolean },
): Promise<DynRelationshipGraphEdge> {
  const response = await apiClient.delete<{ data: DynRelationshipGraphEdge }>(
    `/dynamic-entities/relationship-graph/edges/${fieldId}`,
    {
      params: {
        force: options?.force ? 1 : undefined,
        soft_disable: options?.soft_disable ? 1 : undefined,
      },
    },
  );
  return response.data.data;
}

export async function fetchDynEntity(entity: string): Promise<DynEntityDetail> {
  const response = await apiClient.get<{ data: DynEntityDetail }>(`/dynamic-entities/entities/${entity}`);
  return response.data.data;
}

export async function updateDynEntity(
  entity: string,
  payload: {
    name?: string;
    description?: string | null;
    module_pack?: string;
    sort_order?: number;
    is_active?: boolean;
    related_tabs_json?: unknown;
    print_settings_json?: DynPrintSettings | Record<string, unknown> | null;
  },
): Promise<DynEntityDetail> {
  const response = await apiClient.patch<{ data: DynEntityDetail }>(
    `/dynamic-entities/entities/${entity}`,
    payload,
  );
  return response.data.data;
}

export async function createDynEntity(payload: {
  name: string;
  slug?: string;
  module_pack?: string;
  description?: string;
}): Promise<DynEntityDetail> {
  const response = await apiClient.post<{ data: DynEntityDetail }>("/dynamic-entities/entities", payload);
  return response.data.data;
}

export async function createDynField(
  entity: string,
  payload: {
    label: string;
    name?: string;
    type?: string;
    show_in_table?: boolean;
    is_filterable?: boolean;
    is_required?: boolean;
    is_key?: boolean;
    calculate_totals?: boolean;
    column_span?: number;
    field_order?: number;
    form_group_id?: string | null;
    view_group_id?: string | null;
    placeholder?: string | null;
    options_json?: unknown;
    target_entity_id?: string | null;
    conditional_rules_json?: DynConditionalRule[] | null;
  },
): Promise<DynField> {
  const response = await apiClient.post<{ data: DynField }>(
    `/dynamic-entities/entities/${entity}/fields`,
    payload,
  );
  return response.data.data;
}

export async function fetchDynRecords(
  entity: string,
  params?: {
    page?: number;
    per_page?: number;
    search?: string;
    sort?: string;
    parent_record_id?: string;
    foreign_field?: string;
    status?: string;
    /** Simple equals/contains map, or nested ops: filter[field][eq]=x */
    filter?: Record<string, string | Record<string, string>>;
  },
): Promise<PaginatedEnvelope<DynRecordListRow>> {
  const response = await apiClient.get<{ data: DynRecordListRow[]; meta: PaginatedMeta }>(
    `/dynamic-entities/entities/${entity}/records`,
    { params },
  );
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchDynRecord(recordId: string): Promise<DynRecordDetail> {
  const response = await apiClient.get<{ data: DynRecordDetail }>(`/dynamic-entities/records/${recordId}`);
  return response.data.data;
}

export async function createDynRecord(
  entity: string,
  payload: {
    title?: string;
    status?: string;
    values: Record<string, unknown>;
    parent_record_id?: string | null;
  },
): Promise<DynRecordDetail> {
  const response = await apiClient.post<{ data: DynRecordDetail }>(
    `/dynamic-entities/entities/${entity}/records`,
    payload,
  );
  return response.data.data;
}

export async function updateDynRecord(
  recordId: string,
  payload: { title?: string; status?: string; values?: Record<string, unknown> },
): Promise<DynRecordDetail> {
  const response = await apiClient.patch<{ data: DynRecordDetail }>(
    `/dynamic-entities/records/${recordId}`,
    payload,
  );
  return response.data.data;
}

export async function runDynWorkflowAction(
  recordId: string,
  action: string,
): Promise<DynRecordDetail> {
  const response = await apiClient.post<{ data: DynRecordDetail }>(
    `/dynamic-entities/records/${recordId}/workflow-actions/${encodeURIComponent(action)}`,
  );
  return response.data.data;
}

export async function deleteDynRecord(recordId: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/records/${recordId}`);
}

export async function bulkDynRecords(
  entity: string,
  payload: {
    action: "delete" | "duplicate" | "update";
    ids: string[];
    status?: string | null;
    values?: Record<string, unknown>;
  },
): Promise<{ deleted?: number; duplicated?: number; updated?: number; ids?: string[] }> {
  const response = await apiClient.post<{
    data: { deleted?: number; duplicated?: number; updated?: number; ids?: string[] };
  }>(`/dynamic-entities/entities/${entity}/records/bulk`, payload);
  return response.data.data;
}

export async function exportDynRecordsCsv(
  entity: string,
  params?: { ids?: string[]; search?: string; status?: string },
): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/dynamic-entities/entities/${entity}/records/export`, {
    params: {
      search: params?.search || undefined,
      status: params?.status || undefined,
      ids: params?.ids?.length ? params.ids.join(",") : undefined,
    },
    responseType: "blob",
  });
  return response.data;
}

export type DynImportField = {
  name: string;
  label: string;
  type: string;
  is_required: boolean;
  options?: string[];
  target_entity_id?: string | null;
};

export type DynImportAnalyzeResult = {
  headers: string[];
  suggested_map: Record<string, string>;
  fields: DynImportField[];
  sample_rows: Array<Record<string, string>>;
  row_count: number;
};

export type DynImportResult = {
  dry_run?: boolean;
  created: number;
  updated: number;
  skipped: number;
  would_create?: number;
  would_update?: number;
  would_skip?: number;
  valid_rows?: number;
  error_rows?: number;
  errors: Array<{ row: number; message: string }>;
};

export async function downloadDynImportTemplate(entity: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(
    `/dynamic-entities/entities/${entity}/records/import/template`,
    { responseType: "blob" },
  );
  return response.data;
}

export async function analyzeDynImport(entity: string, file: File): Promise<DynImportAnalyzeResult> {
  const form = new FormData();
  form.append("file", file);
  const response = await apiClient.post<{ data: DynImportAnalyzeResult }>(
    `/dynamic-entities/entities/${entity}/records/import/analyze`,
    form,
  );
  return response.data.data;
}

export async function runDynImport(
  entity: string,
  file: File,
  columnMap: Record<string, string>,
  upsertField?: string | null,
  options?: { dryRun?: boolean },
): Promise<DynImportResult> {
  const form = new FormData();
  form.append("file", file);
  form.append("column_map", JSON.stringify(columnMap));
  if (upsertField) form.append("upsert_field", upsertField);
  if (options?.dryRun) form.append("dry_run", "1");
  const response = await apiClient.post<{ data: DynImportResult }>(
    `/dynamic-entities/entities/${entity}/records/import`,
    form,
  );
  return response.data.data;
}

export async function summarizeDynRecords(
  entity: string,
  params?: { ids?: string[]; search?: string },
): Promise<{ summary: Record<string, number>; row_count: number; headers: string[] }> {
  const response = await apiClient.get<{
    data: { summary: Record<string, number>; row_count: number; headers: string[] };
  }>(`/dynamic-entities/entities/${entity}/records/export`, {
    params: {
      summary_only: 1,
      search: params?.search || undefined,
      ids: params?.ids?.length ? params.ids.join(",") : undefined,
    },
  });
  return response.data.data;
}

export async function updateDynField(
  fieldId: string,
  payload: Partial<{
    label: string;
    type: string;
    is_required: boolean;
    show_in_table: boolean;
    is_filterable: boolean;
    is_key: boolean;
    calculate_totals: boolean;
    form_group_id: string | null;
    view_group_id: string | null;
    field_order: number;
    column_span: number;
    placeholder: string | null;
    options_json: unknown;
    target_entity_id: string | null;
    conditional_rules_json: DynConditionalRule[] | null;
  }>,
): Promise<DynField> {
  const response = await apiClient.patch<{ data: DynField }>(`/dynamic-entities/fields/${fieldId}`, payload);
  return response.data.data;
}

export async function createDynFieldGroup(
  entity: string,
  payload: {
    name: string;
    description?: string | null;
    icon?: string | null;
    applies_to_form?: boolean;
    applies_to_view?: boolean;
    start_collapsed?: boolean;
    sort_order?: number;
  },
): Promise<{
  id: string;
  name: string;
  description?: string | null;
  icon?: string | null;
  applies_to_form?: boolean;
  applies_to_view?: boolean;
  start_collapsed?: boolean;
  sort_order?: number;
}> {
  const response = await apiClient.post<{
    data: {
      id: string;
      name: string;
      description?: string | null;
      icon?: string | null;
      applies_to_form?: boolean;
      applies_to_view?: boolean;
      start_collapsed?: boolean;
      sort_order?: number;
    };
  }>(`/dynamic-entities/entities/${entity}/field-groups`, payload);
  return response.data.data;
}

export async function updateDynFieldGroup(
  groupId: string,
  payload: Partial<{
    name: string;
    description: string | null;
    icon: string | null;
    applies_to_form: boolean;
    applies_to_view: boolean;
    start_collapsed: boolean;
    sort_order: number;
  }>,
): Promise<{
  id: string;
  name: string;
  description: string | null;
  icon: string | null;
  applies_to_form: boolean;
  applies_to_view: boolean;
  start_collapsed: boolean;
  sort_order: number;
}> {
  const response = await apiClient.patch<{
    data: {
      id: string;
      name: string;
      description: string | null;
      icon: string | null;
      applies_to_form: boolean;
      applies_to_view: boolean;
      start_collapsed: boolean;
      sort_order: number;
    };
  }>(`/dynamic-entities/field-groups/${groupId}`, payload);
  return response.data.data;
}

export async function deleteDynFieldGroup(groupId: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/field-groups/${groupId}`);
}

export type PurchaseMonitoringReport = {
  message: string | null;
  kpis: {
    total_purchases: number;
    total_transactions: number;
    purchase_orders_total: number;
    purchase_orders_count: number;
    goods_received_total: number;
    goods_received_count: number;
    active_suppliers: number;
  };
  monthly_trend: Array<{ month: string; purchase_orders: number; goods_receipts: number }>;
  by_supplier: Array<{ supplier_id: string; supplier_name: string; total: number }>;
  recent_transactions: Array<{
    id: string;
    ref: string;
    date: string;
    supplier: string;
    type: string;
    status: string;
    total_amount: number;
  }>;
  top_items: Array<{ description: string; supplier: string; qty: number; total_cost: number }>;
  filter_options: {
    transaction_types: string[];
    suppliers: Array<{ id: string; name: string }>;
  };
};

export async function fetchPurchaseMonitoringReport(params?: {
  date_from?: string;
  date_to?: string;
  supplier_id?: string;
  transaction_type?: string;
}): Promise<PurchaseMonitoringReport> {
  const response = await apiClient.get<{ data: PurchaseMonitoringReport }>(
    "/dynamic-entities/reports/purchase-monitoring",
    { params },
  );
  return response.data.data;
}

export async function fetchDynFinanceReport<T = Record<string, unknown>>(
  report: "capex-opex" | "cost-vs-budget" | "materials-issued" | "daily-sales" | "bank-reconciliation",
  params?: Record<string, string | undefined>,
): Promise<T> {
  const response = await apiClient.get<{ data: T }>(`/dynamic-entities/reports/${report}`, { params });
  return response.data.data;
}

export type DynExtendedReportKey =
  | "cas-executive-dashboard"
  | "ar-ap-aging"
  | "stock-on-hand"
  | "petty-cash"
  | "live-tower-build-forecasts"
  | "site-portfolio-status"
  | "rfti-pipeline"
  | "saq-milestone-aging"
  | "permit-status-compliance"
  | "energization-power-status"
  | "colocation-tenancy"
  | "trial-balance"
  | "income-statement"
  | "balance-sheet"
  | "cash-flow"
  | "monthly-management-accounts"
  | "bir-compliance";

export type DynExtendedReport = {
  message: string | null;
  generated_at: string;
  kpis: Array<{ key: string; label: string; value: number | string; format: string; sub: string | null }>;
  charts: Array<{
    id: string;
    title: string;
    type: "bar" | "donut";
    layout?: "vertical" | "horizontal";
    data: Array<{ key: string; label: string; value: number }>;
    valueLabel: string;
  }>;
  tables: Array<{
    id: string;
    title: string;
    columns: Array<{ key: string; label: string; format?: string }>;
    rows: Array<Record<string, unknown>>;
  }>;
  links: Array<{ label: string; href: string }>;
  filter_options: Record<string, unknown>;
};

export async function fetchDynExtendedReport(
  report: DynExtendedReportKey,
  params?: Record<string, string | undefined>,
): Promise<DynExtendedReport> {
  const response = await apiClient.get<{ data: DynExtendedReport }>(`/dynamic-entities/reports/${report}`, {
    params,
  });
  return response.data.data;
}

export type AtcExecutiveDashboard = {
  message: string | null;
  generated_at: string;
  kpis: {
    sites_total: number;
    sites_wip: number;
    sites_rfti_ready: number;
    construction_active: number;
    construction_completed: number;
    open_procurement_requests: number;
    purchases_mtd: number;
    capex_booked: number;
    opex_ytd: number;
    sales_mtd: number;
  };
  portfolio: {
    by_region: Array<{ key: string; label: string; value: number }>;
    by_project_type: Array<{ key: string; label: string; value: number }>;
    by_site_status: Array<{ key: string; label: string; value: number }>;
    by_milestone: Array<{ key: string; label: string; value: number }>;
  };
  rfti_pipeline: {
    aging: Array<{ key: string; label: string; value: number }>;
    by_milestone: Array<{ key: string; label: string; value: number }>;
  };
  construction: {
    active: number;
    completed: number;
    total_budget: number;
    total_actual: number;
    by_status: Array<{ key: string; label: string; value: number }>;
  };
  procurement: {
    open_requests: number;
    purchases_mtd: number;
    purchase_count_mtd: number;
    suppliers_active: number;
  };
  finance: {
    capex: number;
    opex: number;
    sales_mtd: number;
    sales_count_mtd: number;
  };
  attention: Array<{ headline: string; detail: string; href: string; tone: string }>;
  quick_links: Array<{ label: string; href: string }>;
};

export async function fetchAtcExecutiveDashboard(): Promise<AtcExecutiveDashboard> {
  const response = await apiClient.get<{ data: AtcExecutiveDashboard }>(
    "/dynamic-entities/reports/executive-dashboard",
  );
  return response.data.data;
}

export type AtcTicketingBoard = {
  message: string | null;
  generated_at: string;
  kpis: {
    total: number;
    open: number;
    overdue: number;
    critical_open: number;
    resolved_mtd: number;
  };
  by_status: Array<{ key: string; label: string; value: number }>;
  by_priority: Array<{ key: string; label: string; value: number }>;
  by_type: Array<{ key: string; label: string; value: number }>;
  attention: Array<{ headline: string; detail: string; href: string; tone: string }>;
  recent: Array<{
    id: string;
    title: string;
    ticket_number: string;
    status: string;
    priority: string;
    ticket_type: string;
    site: string;
    assigned_to: string;
    due_at: string | null;
    href: string;
  }>;
  quick_links: Array<{ label: string; href: string }>;
};

export async function fetchAtcTicketingBoard(): Promise<AtcTicketingBoard> {
  const response = await apiClient.get<{ data: AtcTicketingBoard }>(
    "/dynamic-entities/reports/ticketing-board",
  );
  return response.data.data;
}

export type DynPdfFormRow = {
  id: string | null;
  code: string;
  name: string;
  file_name: string;
  path: string;
  size_bytes: number | null;
  size_label: string;
  available: boolean;
  source: "bundled" | "uploaded" | "missing";
  preview_url: string | null;
  can_rename: boolean;
  can_delete: boolean;
  updated_at: string | null;
};

export async function listDynPdfForms(): Promise<{
  forms: DynPdfFormRow[];
  total: number;
  available: number;
}> {
  const response = await apiClient.get<{
    data: DynPdfFormRow[];
    meta: { total: number; available: number };
  }>("/dynamic-entities/pdf-forms");
  return {
    forms: response.data.data,
    total: response.data.meta.total,
    available: response.data.meta.available,
  };
}

export async function uploadDynPdfForm(payload: {
  file: File;
  code?: string;
  name?: string;
}): Promise<{ id: string; code: string; name: string; file_name: string }> {
  const form = new FormData();
  form.append("file", payload.file);
  if (payload.code?.trim()) form.append("code", payload.code.trim());
  if (payload.name?.trim()) form.append("name", payload.name.trim());
  const response = await apiClient.post<{
    data: { id: string; code: string; name: string; file_name: string };
  }>("/dynamic-entities/pdf-forms", form);
  return response.data.data;
}

export async function renameDynPdfForm(
  id: string,
  payload: { name: string; code?: string },
): Promise<{ id: string; code: string; name: string; file_name: string }> {
  const response = await apiClient.patch<{
    data: { id: string; code: string; name: string; file_name: string };
  }>(`/dynamic-entities/pdf-forms/${id}`, payload);
  return response.data.data;
}

export async function deleteDynPdfForm(id: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/pdf-forms/${id}`);
}

export async function downloadDynPdfFormBlob(id: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/dynamic-entities/pdf-forms/${id}/file`, {
    responseType: "blob",
  });
  return response.data;
}

export type DynHtmlReportRow = {
  id: string;
  name: string;
  slug: string;
  path: string;
  description: string | null;
  is_system: boolean;
  has_builder?: boolean;
  created_at: string | null;
  updated_at: string | null;
  html_source?: string;
  css_source?: string;
  js_source?: string;
  builder_json?: DynReportBuilderDef | null;
};

export type DynReportBuilderFilter = {
  field: string;
  op?: string;
  value?: string | number | null;
};

export type DynReportBuilderDef = {
  id?: string;
  title: string;
  caption?: string;
  slug?: string;
  entity_slug: string;
  format?: "summary" | "detail" | "matrix";
  metric?: "count" | "sum";
  metric_field?: string;
  group_by?: string;
  group_by_2?: string;
  matrix_column?: string;
  date_grouping?: "exact" | "day" | "week" | "month" | "year";
  filters?: DynReportBuilderFilter[];
  chart?: string;
  sort_by?: "metric" | "group";
  direction?: "asc" | "desc";
  row_limit?: number;
  currency_prefix?: string;
  saved_slug?: string;
};

export type DynReportBuilderPreview = {
  columns: Array<{ key: string; label: string; numeric: boolean }>;
  rows: Array<Record<string, unknown>>;
  totals: Record<string, number>;
  meta: Record<string, unknown>;
};

export async function listDynHtmlReports(): Promise<{ rows: DynHtmlReportRow[]; total: number }> {
  const response = await apiClient.get<{
    data: DynHtmlReportRow[];
    meta: { total: number };
  }>("/dynamic-entities/html-reports");
  return { rows: response.data.data ?? [], total: response.data.meta?.total ?? 0 };
}

export async function fetchDynHtmlReport(id: string): Promise<DynHtmlReportRow> {
  const response = await apiClient.get<{ data: DynHtmlReportRow }>(
    `/dynamic-entities/html-reports/${id}`,
  );
  return response.data.data;
}

export async function createDynHtmlReport(payload: {
  name: string;
  slug?: string;
  description?: string;
  html_source?: string;
  css_source?: string;
  js_source?: string;
  builder_json?: DynReportBuilderDef | null;
}): Promise<DynHtmlReportRow> {
  const response = await apiClient.post<{ data: DynHtmlReportRow }>(
    "/dynamic-entities/html-reports",
    payload,
  );
  return response.data.data;
}

export async function updateDynHtmlReport(
  id: string,
  payload: Partial<{
    name: string;
    slug: string;
    description: string | null;
    html_source: string;
    css_source: string;
    js_source: string;
    builder_json: DynReportBuilderDef | null;
  }>,
): Promise<DynHtmlReportRow> {
  const response = await apiClient.patch<{ data: DynHtmlReportRow }>(
    `/dynamic-entities/html-reports/${id}`,
    payload,
  );
  return response.data.data;
}

export async function deleteDynHtmlReport(id: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/html-reports/${id}`);
}

export async function duplicateDynHtmlReport(id: string): Promise<DynHtmlReportRow> {
  const response = await apiClient.post<{ data: DynHtmlReportRow }>(
    `/dynamic-entities/html-reports/${id}/duplicate`,
  );
  return response.data.data;
}

export type DynEmailTemplateRow = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  entity_slug: string | null;
  subject: string;
  default_to: string | null;
  cc: string | null;
  bcc: string | null;
  is_system: boolean;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
  body_html?: string;
  body_text?: string | null;
};

export async function listDynEmailTemplates(): Promise<{
  rows: DynEmailTemplateRow[];
  total: number;
}> {
  const response = await apiClient.get<{
    data: DynEmailTemplateRow[];
    meta: { total: number };
  }>("/dynamic-entities/email-templates");
  return { rows: response.data.data ?? [], total: response.data.meta?.total ?? 0 };
}

export async function fetchDynEmailTemplate(id: string): Promise<DynEmailTemplateRow> {
  const response = await apiClient.get<{ data: DynEmailTemplateRow }>(
    `/dynamic-entities/email-templates/${id}`,
  );
  return response.data.data;
}

export async function createDynEmailTemplate(payload: {
  name: string;
  slug?: string;
  description?: string | null;
  entity_slug?: string | null;
  subject: string;
  body_html?: string;
  body_text?: string | null;
  default_to?: string | null;
  cc?: string | null;
  bcc?: string | null;
  is_active?: boolean;
}): Promise<DynEmailTemplateRow> {
  const response = await apiClient.post<{ data: DynEmailTemplateRow }>(
    "/dynamic-entities/email-templates",
    payload,
  );
  return response.data.data;
}

export async function updateDynEmailTemplate(
  id: string,
  payload: Partial<{
    name: string;
    slug: string;
    description: string | null;
    entity_slug: string | null;
    subject: string;
    body_html: string;
    body_text: string | null;
    default_to: string | null;
    cc: string | null;
    bcc: string | null;
    is_active: boolean;
  }>,
): Promise<DynEmailTemplateRow> {
  const response = await apiClient.patch<{ data: DynEmailTemplateRow }>(
    `/dynamic-entities/email-templates/${id}`,
    payload,
  );
  return response.data.data;
}

export async function deleteDynEmailTemplate(id: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/email-templates/${id}`);
}

export type DynScheduledTaskRow = {
  id: string;
  number: number;
  name: string;
  description: string | null;
  command_key: string;
  schedule: string;
  schedule_display: string;
  cron_expression: string;
  execution_label: string;
  is_system: boolean;
  is_active: boolean;
  last_run_at: string | null;
  next_run_at: string | null;
  last_status: string | null;
  last_error: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type DynScheduledTaskMeta = {
  total: number;
  runner: {
    title: string;
    hint: string;
    command: string;
    local_command: string;
  };
  commands: Array<{
    key: string;
    name: string;
    description: string;
    default_schedule: string;
    execution_label: string;
  }>;
  schedule_presets: Array<{
    value: string;
    label: string;
    cron_expression: string;
  }>;
};

export async function listDynScheduledTasks(): Promise<{
  rows: DynScheduledTaskRow[];
  meta: DynScheduledTaskMeta;
}> {
  const response = await apiClient.get<{
    data: DynScheduledTaskRow[];
    meta: DynScheduledTaskMeta;
  }>("/dynamic-entities/scheduled-tasks");
  return {
    rows: response.data.data ?? [],
    meta: response.data.meta ?? {
      total: 0,
      runner: {
        title: "System Cron Runner",
        hint: "",
        command: "php artisan schedule:run",
        local_command: "docker exec toweros-api php artisan schedule:run",
      },
      commands: [],
      schedule_presets: [],
    },
  };
}

export async function createDynScheduledTask(payload: {
  name: string;
  description?: string | null;
  command_key: string;
  schedule: string;
  cron_expression?: string | null;
  is_active?: boolean;
}): Promise<DynScheduledTaskRow> {
  const response = await apiClient.post<{ data: DynScheduledTaskRow }>(
    "/dynamic-entities/scheduled-tasks",
    payload,
  );
  return response.data.data;
}

export async function updateDynScheduledTask(
  id: string,
  payload: Partial<{
    name: string;
    description: string | null;
    command_key: string;
    schedule: string;
    cron_expression: string | null;
    is_active: boolean;
  }>,
): Promise<DynScheduledTaskRow> {
  const response = await apiClient.patch<{ data: DynScheduledTaskRow }>(
    `/dynamic-entities/scheduled-tasks/${id}`,
    payload,
  );
  return response.data.data;
}

export async function deleteDynScheduledTask(id: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/scheduled-tasks/${id}`);
}

export async function runDynScheduledTask(id: string): Promise<DynScheduledTaskRow> {
  const response = await apiClient.post<{ data: DynScheduledTaskRow }>(
    `/dynamic-entities/scheduled-tasks/${id}/run`,
  );
  return response.data.data;
}

export async function toggleDynScheduledTask(id: string): Promise<DynScheduledTaskRow> {
  const response = await apiClient.post<{ data: DynScheduledTaskRow }>(
    `/dynamic-entities/scheduled-tasks/${id}/toggle`,
  );
  return response.data.data;
}

export async function syncDynScheduledTasks(): Promise<{ synced: number }> {
  const response = await apiClient.post<{ data: { synced: number } }>(
    "/dynamic-entities/scheduled-tasks/sync",
  );
  return response.data.data;
}

export async function renderDynHtmlReport(slug: string): Promise<{
  id: string;
  name: string;
  slug: string;
  document_html: string;
}> {
  const response = await apiClient.get<{
    data: { id: string; name: string; slug: string; document_html: string };
  }>(`/dynamic-entities/html-reports/render/${slug}`);
  return response.data.data;
}

export async function previewDynReportBuilder(
  def: DynReportBuilderDef,
): Promise<DynReportBuilderPreview> {
  const response = await apiClient.post<{ data: DynReportBuilderPreview }>(
    "/dynamic-entities/report-builder/preview",
    def,
  );
  return response.data.data;
}

export async function saveDynReportBuilder(def: DynReportBuilderDef): Promise<DynHtmlReportRow> {
  const response = await apiClient.post<{ data: DynHtmlReportRow }>(
    "/dynamic-entities/report-builder/save",
    def,
  );
  return response.data.data;
}

export type DynReportBuilderAiBuildResult = {
  definition: DynReportBuilderDef;
  source: "ai" | "heuristic" | "heuristic_fallback" | string;
  model: string | null;
  notes: string | null;
};

export async function aiBuildDynReportBuilder(payload: {
  prompt: string;
  entity_slug?: string;
}): Promise<DynReportBuilderAiBuildResult> {
  const response = await apiClient.post<{ data: DynReportBuilderAiBuildResult }>(
    "/dynamic-entities/report-builder/ai-build",
    payload,
    { timeout: 120_000 },
  );
  return response.data.data;
}

export type DynWorkflowTriggerMode = "manual" | "on_create" | "on_update";

export type DynWorkflowRow = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  entity_slug: string;
  trigger_mode: DynWorkflowTriggerMode;
  status_field: string;
  status_matches: string[];
  role_ids: string[];
  is_active: boolean;
  sort_order: number;
  created_at: string | null;
  updated_at: string | null;
  definition_json?: Record<string, unknown>;
  action?: Record<string, unknown>;
};

export async function listDynWorkflows(params?: {
  entity_slug?: string;
}): Promise<{ rows: DynWorkflowRow[]; total: number }> {
  const response = await apiClient.get<{
    data: DynWorkflowRow[];
    meta: { total: number };
  }>("/dynamic-entities/workflows", { params });
  return { rows: response.data.data ?? [], total: response.data.meta?.total ?? 0 };
}

export async function fetchDynWorkflow(id: string): Promise<DynWorkflowRow> {
  const response = await apiClient.get<{ data: DynWorkflowRow }>(
    `/dynamic-entities/workflows/${id}`,
  );
  return response.data.data;
}

export async function createDynWorkflow(payload: {
  name: string;
  description?: string;
  entity_slug: string;
  trigger_mode?: DynWorkflowTriggerMode;
  status_field?: string;
  status_matches?: string | string[];
  role_ids?: string[];
  is_active?: boolean;
}): Promise<DynWorkflowRow> {
  const response = await apiClient.post<{ data: DynWorkflowRow }>(
    "/dynamic-entities/workflows",
    payload,
  );
  return response.data.data;
}

export async function updateDynWorkflow(
  id: string,
  payload: Partial<{
    name: string;
    description: string | null;
    entity_slug: string;
    trigger_mode: DynWorkflowTriggerMode;
    status_field: string;
    status_matches: string | string[];
    role_ids: string[];
    is_active: boolean;
    sort_order: number;
    definition_json: Record<string, unknown>;
  }>,
): Promise<DynWorkflowRow> {
  const response = await apiClient.patch<{ data: DynWorkflowRow }>(
    `/dynamic-entities/workflows/${id}`,
    payload,
  );
  return response.data.data;
}

export async function deleteDynWorkflow(id: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/workflows/${id}`);
}

export type DynEntityHookEvent =
  | "before_create"
  | "after_create"
  | "before_update"
  | "after_update"
  | "before_delete"
  | "after_delete"
  | "before_action";

export type DynEntityHookWhenRule = {
  field: string;
  op: "eq" | "neq" | "filled" | "empty" | "changed";
  value?: string;
};

export type DynEntityHookAction =
  | { type: "mirror_field"; from: string; to: string }
  | { type: "set_field"; field: string; value?: unknown }
  | { type: "clear_field"; field: string };

export type DynEntityHookDefinition = {
  when: DynEntityHookWhenRule[];
  actions: DynEntityHookAction[];
};

export type DynEntityHookRow = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  entity_slug: string;
  events: DynEntityHookEvent[];
  definition_json: DynEntityHookDefinition;
  is_active: boolean;
  sort_order: number;
  created_at?: string | null;
  updated_at?: string | null;
};

export type DynEntityHookStats = {
  total: number;
  active: number;
  inactive: number;
  by_event: Record<string, { active: number; inactive: number }>;
};

export async function listDynEntityHooks(params?: {
  entity_slug?: string;
}): Promise<{ rows: DynEntityHookRow[]; total: number; stats: DynEntityHookStats | null }> {
  const response = await apiClient.get<{
    data: DynEntityHookRow[];
    meta: { total: number; stats?: DynEntityHookStats };
  }>("/dynamic-entities/hooks", { params });
  return {
    rows: response.data.data ?? [],
    total: response.data.meta?.total ?? 0,
    stats: response.data.meta?.stats ?? null,
  };
}

export async function fetchDynEntityHook(id: string): Promise<DynEntityHookRow> {
  const response = await apiClient.get<{ data: DynEntityHookRow }>(`/dynamic-entities/hooks/${id}`);
  return response.data.data;
}

export async function createDynEntityHook(payload: {
  name: string;
  description?: string;
  entity_slug: string;
  events: DynEntityHookEvent[];
  definition_json: DynEntityHookDefinition;
  is_active?: boolean;
  sort_order?: number;
}): Promise<DynEntityHookRow> {
  const response = await apiClient.post<{ data: DynEntityHookRow }>(
    "/dynamic-entities/hooks",
    payload,
  );
  return response.data.data;
}

export async function updateDynEntityHook(
  id: string,
  payload: Partial<{
    name: string;
    description: string | null;
    entity_slug: string;
    events: DynEntityHookEvent[];
    definition_json: DynEntityHookDefinition;
    is_active: boolean;
    sort_order: number;
  }>,
): Promise<DynEntityHookRow> {
  const response = await apiClient.patch<{ data: DynEntityHookRow }>(
    `/dynamic-entities/hooks/${id}`,
    payload,
  );
  return response.data.data;
}

export async function deleteDynEntityHook(id: string): Promise<void> {
  await apiClient.delete(`/dynamic-entities/hooks/${id}`);
}

export async function toggleDynEntityHook(id: string): Promise<DynEntityHookRow> {
  const response = await apiClient.post<{ data: DynEntityHookRow }>(
    `/dynamic-entities/hooks/${id}/toggle`,
  );
  return response.data.data;
}

