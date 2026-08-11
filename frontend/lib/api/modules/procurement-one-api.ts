import type {
  ProcurementApAgingSnapshot,
  ProcurementApInvoiceDetail,
  ProcurementApInvoiceListRow,
  ProcurementBudgetLine,
  ProcurementBudgetUtilization,
  ProcurementCostCenter,
  ProcurementCreditNote,
  ProcurementDocumentKind,
  ProcurementFormSchema,
  ProcurementGrnDetail,
  ProcurementGrnListRow,
  ProcurementGrnPrintPayload,
  ProcurementInventoryLocation,
  ProcurementInventoryStockBalance,
  ProcurementInventoryStockMovement,
  ProcurementOneDashboardResponse,
  ProcurementOneMetadata,
  ProcurementOneSettings,
  ProcurementPaymentBatchDetail,
  ProcurementPaymentBatchListRow,
  ProcurementPaymentRequestDetail,
  ProcurementPaymentRequestListRow,
  ProcurementContractDetail,
  ProcurementContractExpiringSummary,
  ProcurementContractListRow,
  ProcurementPoDetail,
  ProcurementPoLine,
  ProcurementPoListRow,
  ProcurementPrDetail,
  ProcurementPrLine,
  ProcurementPrListRow,
  ProcurementRfqDetail,
  ProcurementRfqBidVersion,
  ProcurementRfqListRow,
  ProcurementVendorDetail,
  ProcurementVendorListRow,
} from "@/modules/procurement-one/types";
import { apiClient } from "@/lib/api/client";

export async function fetchProcurementOneDashboard(): Promise<ProcurementOneDashboardResponse> {
  const response = await apiClient.get<{ data: ProcurementOneDashboardResponse }>("/procurement-one/dashboard");
  return response.data.data;
}

export async function fetchProcurementOneMetadata(): Promise<ProcurementOneMetadata> {
  const response = await apiClient.get<{ data: ProcurementOneMetadata }>("/procurement-one/metadata");
  return response.data.data;
}

export async function fetchProcurementOneSettings(): Promise<ProcurementOneSettings> {
  const response = await apiClient.get<{ data: ProcurementOneSettings }>("/procurement-one/settings");
  return response.data.data;
}

const formSchemaPath: Record<ProcurementDocumentKind, string> = {
  purchase_requisition: "/procurement-one/prs/form-schema",
  purchase_order: "/procurement-one/pos/form-schema",
  ap_invoice: "/procurement-one/ap-invoices/form-schema",
};

export async function fetchProcurementFormSchema(kind: ProcurementDocumentKind): Promise<ProcurementFormSchema> {
  const response = await apiClient.get<{ data: ProcurementFormSchema }>(formSchemaPath[kind]);
  return response.data.data;
}

export async function fetchProcurementVendorFormSchema(): Promise<ProcurementFormSchema> {
  const response = await apiClient.get<{ data: ProcurementFormSchema }>("/procurement-one/vendors/form-schema");
  return response.data.data;
}

export async function createProcurementPrFromValues(values: Record<string, string>): Promise<ProcurementPrDetail> {
  const response = await apiClient.post<{ data: ProcurementPrDetail }>("/procurement-one/prs", { values });
  return response.data.data;
}

export async function updateProcurementPrFromValues(
  id: string,
  values: Record<string, string>,
): Promise<ProcurementPrDetail> {
  const response = await apiClient.patch<{ data: ProcurementPrDetail }>(`/procurement-one/prs/${id}`, { values });
  return response.data.data;
}

export async function createProcurementPoFromValues(
  values: Record<string, string>,
  options?: { parentSubmissionId?: string | null },
): Promise<ProcurementPoDetail> {
  const response = await apiClient.post<{ data: ProcurementPoDetail }>("/procurement-one/pos", {
    values,
    parent_submission_id: options?.parentSubmissionId ?? undefined,
  });
  return response.data.data;
}

export async function createProcurementPoFromPrWithValues(
  prId: string,
  values: Record<string, string>,
): Promise<ProcurementPoDetail> {
  const response = await apiClient.post<{ data: ProcurementPoDetail }>(`/procurement-one/prs/${prId}/pos`, { values });
  return response.data.data;
}

export async function updateProcurementPoFromValues(
  id: string,
  values: Record<string, string>,
): Promise<ProcurementPoDetail> {
  const response = await apiClient.patch<{ data: ProcurementPoDetail }>(`/procurement-one/pos/${id}`, { values });
  return response.data.data;
}

export async function createProcurementApInvoiceFromPoWithValues(
  poId: string,
  values: Record<string, string>,
): Promise<{ invoice: ProcurementApInvoiceDetail; warning: string | null }> {
  const response = await apiClient.post<{ data: { invoice: ProcurementApInvoiceDetail; warning: string | null } }>(
    `/procurement-one/pos/${poId}/ap-invoices`,
    { values },
  );
  return response.data.data;
}

export async function updateProcurementApInvoiceFromValues(
  id: string,
  values: Record<string, string>,
): Promise<ProcurementApInvoiceDetail> {
  const response = await apiClient.patch<{ data: ProcurementApInvoiceDetail }>(
    `/procurement-one/ap-invoices/${id}`,
    { values },
  );
  return response.data.data;
}

export async function updateProcurementOneSettings(
  payload: Partial<ProcurementOneSettings>,
): Promise<ProcurementOneSettings> {
  const response = await apiClient.put<{ data: ProcurementOneSettings }>("/procurement-one/settings", payload);
  return response.data.data;
}

export async function fetchProcurementVendors(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  sort?: string;
}): Promise<{
  data: ProcurementVendorListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementVendorListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/vendors", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementVendor(id: string): Promise<ProcurementVendorDetail> {
  const response = await apiClient.get<{ data: ProcurementVendorDetail }>(`/procurement-one/vendors/${id}`);
  return response.data.data;
}

export async function updateProcurementVendorAccreditation(
  id: string,
  payload: { status: string; reason?: string; accreditation_expires_at?: string | null },
): Promise<ProcurementVendorDetail> {
  const response = await apiClient.patch<{ data: ProcurementVendorDetail }>(
    `/procurement-one/vendors/${id}/accreditation`,
    payload,
  );
  return response.data.data;
}

export async function migrateProcurementVendorsFromMasterData(): Promise<{ created: number; updated: number; total: number }> {
  const response = await apiClient.post<{ data: { created: number; updated: number; total: number } }>(
    "/procurement-one/vendors/migrate-from-master-data",
  );
  return response.data.data;
}

export async function fetchProcurementPrs(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  mine?: boolean;
  sort?: string;
}): Promise<{
  data: ProcurementPrListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementPrListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/prs", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementPr(id: string): Promise<ProcurementPrDetail> {
  const response = await apiClient.get<{ data: ProcurementPrDetail }>(`/procurement-one/prs/${id}`);
  return response.data.data;
}

export type ProcurementPrPayload = {
  title: string;
  department?: string;
  urgency?: string;
  justification?: string;
  currency?: string;
  project_id?: string | null;
  rollout_id?: string | null;
  site_id?: string | null;
  boq_line_id?: string | null;
  lines: ProcurementPrLine[];
};

export async function createProcurementPr(payload: ProcurementPrPayload): Promise<ProcurementPrDetail> {
  const response = await apiClient.post<{ data: ProcurementPrDetail }>("/procurement-one/prs", payload);
  return response.data.data;
}

export async function updateProcurementPr(id: string, payload: Partial<ProcurementPrPayload>): Promise<ProcurementPrDetail> {
  const response = await apiClient.patch<{ data: ProcurementPrDetail }>(`/procurement-one/prs/${id}`, payload);
  return response.data.data;
}

export async function submitProcurementPr(id: string): Promise<{ pr: ProcurementPrDetail; warning: string | null }> {
  const response = await apiClient.post<{ data: { pr: ProcurementPrDetail; warning: string | null } }>(
    `/procurement-one/prs/${id}/submit`,
  );
  return response.data.data;
}

export async function cancelProcurementPr(id: string, reason?: string): Promise<ProcurementPrDetail> {
  const response = await apiClient.post<{ data: ProcurementPrDetail }>(`/procurement-one/prs/${id}/cancel`, {
    reason,
  });
  return response.data.data;
}

export async function voidProcurementPr(id: string, reason: string): Promise<ProcurementPrDetail> {
  const response = await apiClient.post<{ data: ProcurementPrDetail }>(`/procurement-one/prs/${id}/void`, { reason });
  return response.data.data;
}

export async function uploadProcurementPrAttachment(
  id: string,
  file: File,
  fieldName = "quotes",
): Promise<{ id: string; file_name: string; field_name: string; e_approval_attachment_id: string | null }> {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("field_name", fieldName);
  const response = await apiClient.post<{
    data: { id: string; file_name: string; field_name: string; e_approval_attachment_id: string | null };
  }>(`/procurement-one/prs/${id}/attachments`, formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return response.data.data;
}

export async function fetchProcurementPos(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  pr_id?: string;
  mine?: boolean;
  sort?: string;
}): Promise<{
  data: ProcurementPoListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementPoListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/pos", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementPo(id: string): Promise<ProcurementPoDetail> {
  const response = await apiClient.get<{ data: ProcurementPoDetail }>(`/procurement-one/pos/${id}`);
  return response.data.data;
}

export type ProcurementPoPayload = {
  pr_ids?: string[];
  vendor_code?: string;
  vendor_name?: string;
  supplier?: string;
  ship_to?: string;
  delivery_date?: string;
  payment_terms?: string;
  currency_code?: string;
  exchange_rate?: number;
  delivery_location?: string;
  vat_exempt_amount?: number;
  zero_rated_amount?: number;
  vat_rate?: number;
  less_discount?: number;
  lines?: ProcurementPoLine[];
  allocations?: Array<{ pr_id: string; amount: number }>;
};

export async function createProcurementPo(payload: ProcurementPoPayload): Promise<ProcurementPoDetail> {
  const response = await apiClient.post<{ data: ProcurementPoDetail }>("/procurement-one/pos", payload);
  return response.data.data;
}

export async function createProcurementPoFromPr(prId: string, payload: ProcurementPoPayload): Promise<ProcurementPoDetail> {
  const response = await apiClient.post<{ data: ProcurementPoDetail }>(`/procurement-one/prs/${prId}/pos`, payload);
  return response.data.data;
}

export async function updateProcurementPo(id: string, payload: Partial<ProcurementPoPayload & { status?: string }>): Promise<ProcurementPoDetail> {
  const response = await apiClient.patch<{ data: ProcurementPoDetail }>(`/procurement-one/pos/${id}`, payload);
  return response.data.data;
}

export async function submitProcurementPo(id: string): Promise<{ po: ProcurementPoDetail; warning: string | null }> {
  const response = await apiClient.post<{ data: { po: ProcurementPoDetail; warning: string | null } }>(
    `/procurement-one/pos/${id}/submit`,
  );
  return response.data.data;
}

export async function cancelProcurementPo(id: string, reason?: string): Promise<ProcurementPoDetail> {
  const response = await apiClient.post<{ data: ProcurementPoDetail }>(`/procurement-one/pos/${id}/cancel`, {
    reason,
  });
  return response.data.data;
}

export async function voidProcurementPo(id: string, reason: string): Promise<ProcurementPoDetail> {
  const response = await apiClient.post<{ data: ProcurementPoDetail }>(`/procurement-one/pos/${id}/void`, { reason });
  return response.data.data;
}

export async function sendProcurementPoVendorEmail(
  id: string,
  event: "po_approved" | "po_sent" = "po_sent",
): Promise<ProcurementPoDetail> {
  const response = await apiClient.post<{ data: { po: ProcurementPoDetail; sent: boolean } }>(
    `/procurement-one/pos/${id}/send-vendor-email`,
    { event },
  );
  return response.data.data.po;
}

export async function fetchProcurementGrns(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  po_id?: string;
  sort?: string;
}): Promise<{
  data: ProcurementGrnListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementGrnListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/grns", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementGrn(id: string): Promise<ProcurementGrnDetail> {
  const response = await apiClient.get<{ data: ProcurementGrnDetail }>(`/procurement-one/grns/${id}`);
  return response.data.data;
}

export async function fetchProcurementGrnPrint(id: string): Promise<ProcurementGrnPrintPayload> {
  const response = await apiClient.get<{ data: ProcurementGrnPrintPayload }>(`/procurement-one/grns/${id}/print`);
  return response.data.data;
}

export type ProcurementGrnPayload = {
  post?: boolean;
  project_id?: string | null;
  rollout_id?: string | null;
  site_id?: string | null;
  inventory_location_id?: string | null;
  gps_latitude?: number | null;
  gps_longitude?: number | null;
  gps_accuracy_meters?: number | null;
  received_at?: string | null;
  notes?: string | null;
  lines?: Array<{
    po_line_id: string;
    quantity_received: number;
    line_notes?: string | null;
  }>;
};

export async function createProcurementGrnFromPo(
  poId: string,
  payload: ProcurementGrnPayload,
): Promise<{ grn: ProcurementGrnDetail; warning: string | null }> {
  const response = await apiClient.post<{ data: { grn: ProcurementGrnDetail; warning: string | null } }>(
    `/procurement-one/pos/${poId}/grns`,
    payload,
  );
  return response.data.data;
}

export async function updateProcurementGrn(id: string, payload: ProcurementGrnPayload): Promise<ProcurementGrnDetail> {
  const response = await apiClient.patch<{ data: ProcurementGrnDetail }>(`/procurement-one/grns/${id}`, payload);
  return response.data.data;
}

export async function postProcurementGrn(id: string): Promise<{ grn: ProcurementGrnDetail; warning: string | null }> {
  const response = await apiClient.post<{ data: { grn: ProcurementGrnDetail; warning: string | null } }>(
    `/procurement-one/grns/${id}/post`,
  );
  return response.data.data;
}

export async function downloadProcurementGrnAttachment(grnId: string, attachmentId: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(
    `/procurement-one/grns/${grnId}/attachments/${attachmentId}/download`,
    { responseType: "blob" },
  );
  return response.data;
}

export async function uploadProcurementGrnAttachment(
  id: string,
  file: File,
  fieldName = "delivery_photo",
): Promise<{ id: string | null; file_name: string | null; field_name: string | null }> {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("field_name", fieldName);
  const response = await apiClient.post<{
    data: { id: string | null; file_name: string | null; field_name: string | null };
  }>(`/procurement-one/grns/${id}/attachments`, formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return response.data.data;
}

export async function fetchProcurementInventoryLocations(params?: { location_kind?: string }): Promise<ProcurementInventoryLocation[]> {
  const response = await apiClient.get<{ data: { locations: ProcurementInventoryLocation[] } }>(
    "/procurement-one/inventory/locations",
    { params },
  );
  return response.data.data.locations;
}

export async function createProcurementInventoryLocation(
  payload: Partial<ProcurementInventoryLocation>,
): Promise<ProcurementInventoryLocation> {
  const response = await apiClient.post<{ data: ProcurementInventoryLocation }>(
    "/procurement-one/inventory/locations",
    payload,
  );
  return response.data.data;
}

export async function fetchProcurementInventoryStockBalances(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  location_id?: string;
}): Promise<{
  data: ProcurementInventoryStockBalance[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementInventoryStockBalance[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/inventory/stock-balances", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementInventoryMovements(params?: {
  page?: number;
  per_page?: number;
  location_id?: string;
  grn_id?: string;
  movement_type?: string;
}): Promise<{
  data: ProcurementInventoryStockMovement[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementInventoryStockMovement[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/inventory/movements", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function transferProcurementInventoryStock(payload: {
  from_location_id: string;
  to_location_id: string;
  po_line_id: string;
  quantity: number;
  notes?: string;
}): Promise<{ transfer_batch_id: string; movements: ProcurementInventoryStockMovement[] }> {
  const response = await apiClient.post<{
    data: { transfer_batch_id: string; movements: ProcurementInventoryStockMovement[] };
  }>("/procurement-one/inventory/transfers", payload);
  return response.data.data;
}

export async function deployProcurementInventoryStock(payload: {
  from_location_id: string;
  to_location_id: string;
  po_line_id: string;
  quantity: number;
  create_asset?: boolean;
  notes?: string;
  asset?: { name?: string; category?: string };
}): Promise<{ movement: ProcurementInventoryStockMovement; asset: Record<string, unknown> | null }> {
  const response = await apiClient.post<{
    data: { movement: ProcurementInventoryStockMovement; asset: Record<string, unknown> | null };
  }>("/procurement-one/inventory/deployments", payload);
  return response.data.data;
}

export async function fetchProcurementCostCenters(): Promise<ProcurementCostCenter[]> {
  const response = await apiClient.get<{ data: { cost_centers: ProcurementCostCenter[] } }>(
    "/procurement-one/cost-centers",
  );
  return response.data.data.cost_centers;
}

export async function createProcurementCostCenter(
  payload: Partial<ProcurementCostCenter>,
): Promise<ProcurementCostCenter> {
  const response = await apiClient.post<{ data: ProcurementCostCenter }>("/procurement-one/cost-centers", payload);
  return response.data.data;
}

export async function fetchProcurementBudgetLines(params?: {
  rollout_id?: string;
  project_id?: string;
}): Promise<ProcurementBudgetLine[]> {
  const response = await apiClient.get<{ data: { budget_lines: ProcurementBudgetLine[] } }>(
    "/procurement-one/budget-lines",
    { params },
  );
  return response.data.data.budget_lines;
}

export async function createProcurementBudgetLine(
  payload: Partial<ProcurementBudgetLine>,
): Promise<ProcurementBudgetLine> {
  const response = await apiClient.post<{ data: ProcurementBudgetLine }>("/procurement-one/budget-lines", payload);
  return response.data.data;
}

export async function fetchProcurementBudgetUtilization(params: {
  rollout_id?: string;
  project_id?: string;
}): Promise<ProcurementBudgetUtilization> {
  const response = await apiClient.get<{ data: ProcurementBudgetUtilization }>(
    "/procurement-one/budget-utilization",
    { params },
  );
  return response.data.data;
}

export async function fetchProcurementApInvoices(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  po_id?: string;
  sort?: string;
}): Promise<{
  data: ProcurementApInvoiceListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementApInvoiceListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/ap-invoices", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementApInvoice(id: string): Promise<ProcurementApInvoiceDetail> {
  const response = await apiClient.get<{ data: ProcurementApInvoiceDetail }>(`/procurement-one/ap-invoices/${id}`);
  return response.data.data;
}

export async function createProcurementApInvoiceFromPo(
  poId: string,
  payload: Record<string, unknown>,
): Promise<{ invoice: ProcurementApInvoiceDetail; warning: string | null }> {
  const response = await apiClient.post<{ data: { invoice: ProcurementApInvoiceDetail; warning: string | null } }>(
    `/procurement-one/pos/${poId}/ap-invoices`,
    payload,
  );
  return response.data.data;
}

export async function submitProcurementApInvoice(
  id: string,
): Promise<{ invoice: ProcurementApInvoiceDetail; warning: string | null }> {
  const response = await apiClient.post<{ data: { invoice: ProcurementApInvoiceDetail; warning: string | null } }>(
    `/procurement-one/ap-invoices/${id}/submit`,
  );
  return response.data.data;
}

export async function fetchProcurementApAging(): Promise<ProcurementApAgingSnapshot> {
  const response = await apiClient.get<{ data: ProcurementApAgingSnapshot }>("/procurement-one/ap-invoices/aging");
  return response.data.data;
}

export async function createProcurementCreditNote(
  payload: Partial<ProcurementCreditNote> & { po_id: string; amount: number },
): Promise<ProcurementCreditNote> {
  const response = await apiClient.post<{ data: ProcurementCreditNote }>("/procurement-one/credit-notes", payload);
  return response.data.data;
}

export async function approveProcurementCreditNote(id: string): Promise<ProcurementCreditNote> {
  const response = await apiClient.post<{ data: ProcurementCreditNote }>(
    `/procurement-one/credit-notes/${id}/approve`,
  );
  return response.data.data;
}

export function procurementApGlExportUrl(params?: { from?: string; to?: string }): string {
  const search = new URLSearchParams();
  if (params?.from) search.set("from", params.from);
  if (params?.to) search.set("to", params.to);
  const qs = search.toString();
  return `/api/v1/procurement-one/ap-invoices/export${qs ? `?${qs}` : ""}`;
}

export async function fetchProcurementPaymentRequests(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  vendor_code?: string;
  ap_invoice_id?: string;
  sort?: string;
}): Promise<{
  data: ProcurementPaymentRequestListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementPaymentRequestListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/payment-requests", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementPaymentRequest(id: string): Promise<ProcurementPaymentRequestDetail> {
  const response = await apiClient.get<{ data: { payment_request: ProcurementPaymentRequestDetail } }>(
    `/procurement-one/payment-requests/${id}`,
  );
  return response.data.data.payment_request;
}

export async function createProcurementPaymentRequestFromApInvoice(
  apInvoiceId: string,
  payload?: { amount?: number; notes?: string },
): Promise<ProcurementPaymentRequestDetail> {
  const response = await apiClient.post<{ data: { payment_request: ProcurementPaymentRequestDetail } }>(
    `/procurement-one/ap-invoices/${apInvoiceId}/payment-requests`,
    payload ?? {},
  );
  return response.data.data.payment_request;
}

export async function submitProcurementPaymentRequest(id: string): Promise<ProcurementPaymentRequestDetail> {
  const response = await apiClient.post<{ data: { payment_request: ProcurementPaymentRequestDetail } }>(
    `/procurement-one/payment-requests/${id}/submit`,
  );
  return response.data.data.payment_request;
}

export async function approveProcurementPaymentRequest(id: string): Promise<ProcurementPaymentRequestDetail> {
  const response = await apiClient.post<{ data: { payment_request: ProcurementPaymentRequestDetail } }>(
    `/procurement-one/payment-requests/${id}/approve`,
  );
  return response.data.data.payment_request;
}

export async function scheduleProcurementPaymentRequest(
  id: string,
  payload?: { scheduled_date?: string },
): Promise<ProcurementPaymentRequestDetail> {
  const response = await apiClient.post<{ data: { payment_request: ProcurementPaymentRequestDetail } }>(
    `/procurement-one/payment-requests/${id}/schedule`,
    payload ?? {},
  );
  return response.data.data.payment_request;
}

export async function markProcurementPaymentRequestPaid(
  id: string,
  payload?: { payment_reference?: string },
): Promise<ProcurementPaymentRequestDetail> {
  const response = await apiClient.post<{ data: { payment_request: ProcurementPaymentRequestDetail } }>(
    `/procurement-one/payment-requests/${id}/mark-paid`,
    payload ?? {},
  );
  return response.data.data.payment_request;
}

export async function markProcurementPaymentRequestReconciled(id: string): Promise<ProcurementPaymentRequestDetail> {
  const response = await apiClient.post<{ data: { payment_request: ProcurementPaymentRequestDetail } }>(
    `/procurement-one/payment-requests/${id}/mark-reconciled`,
  );
  return response.data.data.payment_request;
}

export async function fetchProcurementPaymentBatches(params?: {
  page?: number;
  per_page?: number;
  status?: string;
  sort?: string;
}): Promise<{
  data: ProcurementPaymentBatchListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementPaymentBatchListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/payment-batches", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementPaymentBatch(id: string): Promise<ProcurementPaymentBatchDetail> {
  const response = await apiClient.get<{ data: { payment_batch: ProcurementPaymentBatchDetail } }>(
    `/procurement-one/payment-batches/${id}`,
  );
  return response.data.data.payment_batch;
}

export async function createProcurementPaymentBatch(payload: {
  payment_request_ids: string[];
  scheduled_date?: string;
  notes?: string;
}): Promise<ProcurementPaymentBatchDetail> {
  const response = await apiClient.post<{ data: { payment_batch: ProcurementPaymentBatchDetail } }>(
    "/procurement-one/payment-batches",
    payload,
  );
  return response.data.data.payment_batch;
}

export async function markProcurementPaymentBatchExported(id: string): Promise<ProcurementPaymentBatchDetail> {
  const response = await apiClient.post<{ data: { payment_batch: ProcurementPaymentBatchDetail } }>(
    `/procurement-one/payment-batches/${id}/mark-exported`,
  );
  return response.data.data.payment_batch;
}

export async function markProcurementPaymentBatchReconciled(id: string): Promise<ProcurementPaymentBatchDetail> {
  const response = await apiClient.post<{ data: { payment_batch: ProcurementPaymentBatchDetail } }>(
    `/procurement-one/payment-batches/${id}/mark-reconciled`,
  );
  return response.data.data.payment_batch;
}

export function procurementPaymentBatchExportUrl(batchId: string): string {
  return `/api/v1/procurement-one/payment-batches/${batchId}/export`;
}

export async function fetchProcurementRfqs(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  pr_id?: string;
  sort?: string;
}): Promise<{
  data: ProcurementRfqListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementRfqListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/rfqs", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementRfq(id: string): Promise<ProcurementRfqDetail> {
  const response = await apiClient.get<{ data: { rfq: ProcurementRfqDetail } }>(`/procurement-one/rfqs/${id}`);
  return response.data.data.rfq;
}

export async function createProcurementRfqFromPr(
  prId: string,
  payload?: { title?: string; vendor_ids?: string[]; notes?: string },
): Promise<ProcurementRfqDetail> {
  const response = await apiClient.post<{ data: { rfq: ProcurementRfqDetail } }>(
    `/procurement-one/prs/${prId}/rfqs`,
    payload ?? {},
  );
  return response.data.data.rfq;
}

export async function publishProcurementRfq(id: string, payload?: { bidding_closes_at?: string }): Promise<ProcurementRfqDetail> {
  const response = await apiClient.post<{ data: { rfq: ProcurementRfqDetail } }>(
    `/procurement-one/rfqs/${id}/publish`,
    payload ?? {},
  );
  return response.data.data.rfq;
}

export async function inviteProcurementRfqVendors(id: string, vendorIds: string[]): Promise<ProcurementRfqDetail> {
  const response = await apiClient.post<{ data: { rfq: ProcurementRfqDetail } }>(`/procurement-one/rfqs/${id}/vendors`, {
    vendor_ids: vendorIds,
  });
  return response.data.data.rfq;
}

export async function resendProcurementRfqVendorInvitation(rfqId: string, vendorId: string): Promise<ProcurementRfqDetail> {
  const response = await apiClient.post<{ data: { rfq: ProcurementRfqDetail } }>(
    `/procurement-one/rfqs/${rfqId}/vendors/${vendorId}/resend-invitation`,
  );
  return response.data.data.rfq;
}

export async function fetchProcurementRfqBidVersions(rfqId: string, bidId: string): Promise<ProcurementRfqBidVersion[]> {
  const response = await apiClient.get<{ data: { versions: ProcurementRfqBidVersion[] } }>(
    `/procurement-one/rfqs/${rfqId}/bids/${bidId}/versions`,
  );
  return response.data.data.versions;
}

export async function captureProcurementRfqBid(
  rfqId: string,
  payload: {
    vendor_id: string;
    lines: Array<{
      rfq_line_id: string;
      quantity: number;
      unit_price?: number;
      monthly_unit_price?: number;
      yearly_unit_price?: number;
      lead_time_days?: number;
      notes?: string;
    }>;
    notes?: string;
  },
): Promise<ProcurementRfqDetail> {
  const response = await apiClient.post<{ data: { rfq: ProcurementRfqDetail | null } }>(
    `/procurement-one/rfqs/${rfqId}/bids`,
    payload,
  );
  return response.data.data.rfq!;
}

export async function closeProcurementRfqBidding(id: string): Promise<ProcurementRfqDetail> {
  const response = await apiClient.post<{ data: { rfq: ProcurementRfqDetail } }>(`/procurement-one/rfqs/${id}/close-bidding`);
  return response.data.data.rfq;
}

export async function awardProcurementRfq(id: string, bidId: string, awardNotes?: string): Promise<ProcurementRfqDetail> {
  const response = await apiClient.post<{ data: { rfq: ProcurementRfqDetail } }>(`/procurement-one/rfqs/${id}/award`, {
    bid_id: bidId,
    award_notes: awardNotes,
  });
  return response.data.data.rfq;
}

export async function createProcurementPoFromRfq(id: string): Promise<{ purchase_order: ProcurementPoDetail; rfq: ProcurementRfqDetail }> {
  const response = await apiClient.post<{ data: { purchase_order: ProcurementPoDetail; rfq: ProcurementRfqDetail } }>(
    `/procurement-one/rfqs/${id}/pos`,
  );
  return response.data.data;
}

export async function fetchProcurementContracts(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  vendor_id?: string;
  site_id?: string;
  sort?: string;
}): Promise<{
  data: ProcurementContractListRow[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}> {
  const response = await apiClient.get<{
    data: ProcurementContractListRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
  }>("/procurement-one/contracts", { params });
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchProcurementContract(id: string): Promise<ProcurementContractDetail> {
  const response = await apiClient.get<{ data: { contract: ProcurementContractDetail } }>(`/procurement-one/contracts/${id}`);
  return response.data.data.contract;
}

export async function createProcurementContract(payload: {
  title: string;
  vendor_id: string;
  description?: string;
  site_id?: string | null;
  primary_document_id?: string | null;
  spend_ceiling?: number | null;
  currency_code?: string;
  effective_from?: string | null;
  end_date?: string | null;
}): Promise<ProcurementContractDetail> {
  const response = await apiClient.post<{ data: { contract: ProcurementContractDetail } }>(
    "/procurement-one/contracts",
    payload,
  );
  return response.data.data.contract;
}

export async function updateProcurementContract(
  id: string,
  payload: Partial<{
    title: string;
    description: string | null;
    vendor_id: string;
    site_id: string | null;
    primary_document_id: string | null;
    spend_ceiling: number | null;
    currency_code: string;
    effective_from: string | null;
    end_date: string | null;
  }>,
): Promise<ProcurementContractDetail> {
  const response = await apiClient.put<{ data: { contract: ProcurementContractDetail } }>(
    `/procurement-one/contracts/${id}`,
    payload,
  );
  return response.data.data.contract;
}

export async function activateProcurementContract(id: string): Promise<ProcurementContractDetail> {
  const response = await apiClient.post<{ data: { contract: ProcurementContractDetail } }>(
    `/procurement-one/contracts/${id}/activate`,
  );
  return response.data.data.contract;
}

export async function terminateProcurementContract(id: string, reason: string): Promise<ProcurementContractDetail> {
  const response = await apiClient.post<{ data: { contract: ProcurementContractDetail } }>(
    `/procurement-one/contracts/${id}/terminate`,
    { reason },
  );
  return response.data.data.contract;
}

export async function fetchProcurementVendorContracts(vendorId: string): Promise<ProcurementContractListRow[]> {
  const response = await apiClient.get<{ data: ProcurementContractListRow[] }>(
    `/procurement-one/vendors/${vendorId}/contracts`,
  );
  return response.data.data;
}

export async function fetchProcurementExpiringContracts(params?: {
  within_days?: number;
  summary_only?: boolean;
}): Promise<{ rows: ProcurementContractListRow[]; summary: ProcurementContractExpiringSummary }> {
  const response = await apiClient.get<{
    data: ProcurementContractListRow[] | { rows: ProcurementContractListRow[]; summary: ProcurementContractExpiringSummary };
    meta?: { summary: ProcurementContractExpiringSummary; within_days: number };
  }>("/procurement-one/contracts/expiring", {
    params: { ...params, summary_only: params?.summary_only ? 1 : undefined },
  });

  if (params?.summary_only) {
    const payload = response.data.data as { rows: ProcurementContractListRow[]; summary: ProcurementContractExpiringSummary };
    return { rows: payload.rows, summary: payload.summary };
  }

  return {
    rows: response.data.data as ProcurementContractListRow[],
    summary: response.data.meta?.summary ?? { within_30: 0, within_60: 0, within_90: 0 },
  };
}

export function procurementExcelPackExportUrl(params?: {
  from?: string;
  to?: string;
  period?: "current_month" | "previous_month";
}): string {
  const search = new URLSearchParams();
  if (params?.from) search.set("from", params.from);
  if (params?.to) search.set("to", params.to);
  if (params?.period) search.set("period", params.period);
  const query = search.toString();

  return `/api/v1/procurement-one/reports/excel-pack${query ? `?${query}` : ""}`;
}

export function procurementEntityCsvExportUrl(
  entity: "vendors" | "prs" | "pr_lines" | "pos" | "po_lines",
  params?: { from?: string; to?: string; period?: "current_month" | "previous_month" },
): string {
  const search = new URLSearchParams();
  if (params?.from) search.set("from", params.from);
  if (params?.to) search.set("to", params.to);
  if (params?.period) search.set("period", params.period);
  const query = search.toString();

  return `/api/v1/procurement-one/reports/${entity}/export${query ? `?${query}` : ""}`;
}

export async function fetchProcurementVendorSpendDashboard(params?: {
  from?: string;
  to?: string;
  period?: "current_month" | "previous_month";
}): Promise<import("@/modules/procurement-one/types").ProcurementVendorSpendDashboard> {
  const response = await apiClient.get<{ data: import("@/modules/procurement-one/types").ProcurementVendorSpendDashboard }>(
    "/procurement-one/reports/vendor-spend",
    { params },
  );
  return response.data.data;
}

export async function fetchProcurementP2pDashboard(): Promise<import("@/modules/procurement-one/types").ProcurementP2pDashboard> {
  const response = await apiClient.get<{ data: import("@/modules/procurement-one/types").ProcurementP2pDashboard }>(
    "/procurement-one/reports/p2p-dashboard",
  );
  return response.data.data;
}
