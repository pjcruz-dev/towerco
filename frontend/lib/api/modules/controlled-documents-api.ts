import { apiClient } from "@/lib/api/client";
import type { ControlledDocumentRegisterAccessPayload } from "@/modules/documents/controlled-document-register-access";

export type ControlledDocumentRow = {
  id: string;
  document_code: string;
  dcf_number: string | null;
  e_approval_form_id: string | null;
  revision_form_id: string | null;
  title: string;
  document_type: string | null;
  department: string | null;
  current_revision: number;
  status: string;
  effective_date: string | null;
  next_review_date: string | null;
  published_at: string | null;
  created_by_name: string | null;
  has_file?: boolean;
  incoming_revision?: number | null;
  incoming_submission_id?: string | null;
  incoming_document_no?: string | null;
  aging?: Array<{
    name: string;
    role: string;
    requestor_department: string | null;
    age_days: number;
    submitted_at: string | null;
    submission_id: string;
    document_no: string;
  }>;
};

export type ControlledDocumentRevision = {
  id: string;
  revision_number: number;
  dcf_number: string | null;
  status: string;
  change_summary: string | null;
  effective_date: string | null;
  approved_at: string | null;
  approved_by_name: string | null;
  has_file: boolean;
  original_filename: string | null;
  e_approval_submission_id: string | null;
  e_approval_document_no: string | null;
};

export type ControlledDocumentDetail = ControlledDocumentRow & {
  revisions: ControlledDocumentRevision[];
};

export type ControlledDocumentIndexPayload = {
  kpis: {
    total: number;
    pending: number;
    published: number;
    obsolete: number;
    incoming: number;
  };
  facets: {
    departments: string[];
    document_types: string[];
  };
  default_form_id: string | null;
  documents: {
    data: ControlledDocumentRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type ControlledDocumentImportResult = {
  processed: number;
  skipped: number;
  errors: { row: number; message: string }[];
};

export type ControlledDocumentImportField = {
  name: string;
  label: string;
  required: boolean;
};

export type ControlledDocumentImportAnalyzeResult = {
  headers: string[];
  suggested_map: Record<string, string>;
  fields: ControlledDocumentImportField[];
  row_count: number;
};

export type ControlledDocumentImportRow = {
  row: number;
  document_code: string;
  outcome: string;
  file_status: string;
  matched_path: string | null;
  message: string | null;
};

export type ControlledDocumentImportRunResult = {
  written: boolean;
  created: number;
  updated: number;
  skipped: number;
  files_attached: number;
  errors: { row: number; message: string }[];
  rows: ControlledDocumentImportRow[];
};

export type ControlledDocumentLookupResult = {
  exists: boolean;
  document_code: string | null;
  title?: string;
  document_type?: string | null;
  department?: string | null;
  current_revision?: number;
  next_revision: number;
  effective_date?: string | null;
  next_review_date?: string | null;
  status?: string;
};

export async function lookupControlledDocument(
  documentCode: string,
): Promise<ControlledDocumentLookupResult> {
  const response = await apiClient.get<{ data: ControlledDocumentLookupResult }>(
    "/documents/controlled/lookup",
    { params: { document_code: documentCode } },
  );
  return response.data.data;
}

export async function fetchControlledDocumentRegisterAccess(): Promise<ControlledDocumentRegisterAccessPayload> {
  const response = await apiClient.get<{ data: ControlledDocumentRegisterAccessPayload }>(
    "/documents/controlled/register-access",
  );
  return response.data.data;
}

export async function updateControlledDocumentRegisterAccess(payload: {
  viewer_roles: string[];
  full_access_roles: string[];
  role_department_map: Record<string, string[]>;
}): Promise<ControlledDocumentRegisterAccessPayload> {
  const response = await apiClient.put<{ data: ControlledDocumentRegisterAccessPayload }>(
    "/documents/controlled/register-access",
    payload,
  );
  return response.data.data;
}

export async function fetchControlledDocuments(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  department?: string;
  status?: string;
  document_type?: string;
  review?: string;
  file?: string;
  effective_from?: string;
  effective_to?: string;
  published_from?: string;
  published_to?: string;
  sort?: string;
}): Promise<ControlledDocumentIndexPayload> {
  const response = await apiClient.get<{ data: ControlledDocumentIndexPayload }>(
    "/documents/controlled",
    { params },
  );
  return response.data.data;
}

export type ControlledDocumentChartPoint = {
  key: string;
  label: string;
  value: number;
};

export type ControlledDocumentOverview = {
  as_of: string;
  published_from: string;
  counts: {
    total: number;
    pending: number;
    published: number;
    obsolete: number;
    published_last_30_days: number;
    overdue: number;
    due_30: number;
    due_90: number;
    no_review: number;
    incoming: number;
    versioned: number;
    ongoing: number;
  };
  by_department: ControlledDocumentChartPoint[];
  by_document_type: ControlledDocumentChartPoint[];
  facets: { departments: string[]; document_types: string[] };
  mix: { pending: number; approved: number; obsolete: number };
  aging_summary: { documents: number; people: number; oldest_days: number };
  departments: Array<{
    key: string;
    label: string;
    controlled: number;
    pending: number;
    approved: number;
    versioned: number;
    obsolete: number;
    obsolete_published: number;
    incoming: number;
    ongoing: number;
  }>;
  aging: Array<{
    document_code: string;
    name: string;
    role: string;
    requestor_department: string | null;
    age_days: number;
    submitted_at: string | null;
    submission_id: string;
    document_no: string;
  }>;
  published_over_time: ControlledDocumentChartPoint[];
  recent: Array<{
    id: string;
    document_code: string;
    title: string;
    department: string | null;
    document_type: string | null;
    status: string;
    current_revision: number;
    effective_date: string | null;
    published_at: string | null;
  }>;
};

export type ControlledDocumentObsoleteRow = {
  document_id: string;
  revision_id: string | null;
  document_code: string;
  title: string;
  department: string | null;
  document_type: string | null;
  revision_number: number | null;
  standing: "current" | "obsolete" | "pending";
  effective_date: string | null;
  approved_at: string | null;
  filename: string | null;
  has_file: boolean;
};

export type ControlledDocumentObsoleteBoard = {
  counts: { obsolete: number; approved: number; pending: number };
  obsolete: ControlledDocumentObsoleteRow[];
  approved: ControlledDocumentObsoleteRow[];
  pending: ControlledDocumentObsoleteRow[];
};

export type ControlledDocumentReport = {
  from: string;
  to: string;
  facets: { departments: string[]; document_types: string[] };
  counts: { total: number; published: number; obsolete: number };
  publications: ControlledDocumentChartPoint[];
  by_department: ControlledDocumentChartPoint[];
  by_document_type: ControlledDocumentChartPoint[];
  review: ControlledDocumentChartPoint[];
  mix: { pending: number; approved: number; obsolete: number };
  aging: ControlledDocumentOverview["aging"];
  aging_summary: ControlledDocumentOverview["aging_summary"];
};

export async function fetchControlledDocumentOverview(params?: {
  department?: string;
  document_type?: string;
  status?: string;
  file?: string;
}): Promise<ControlledDocumentOverview> {
  const response = await apiClient.get<{ data: ControlledDocumentOverview }>(
    "/documents/controlled/overview",
    { params },
  );
  return response.data.data;
}

export async function fetchControlledDocumentObsoleteBoard(): Promise<ControlledDocumentObsoleteBoard> {
  const response = await apiClient.get<{ data: ControlledDocumentObsoleteBoard }>(
    "/documents/controlled/obsolete",
  );
  return response.data.data;
}

export async function fetchControlledDocumentReport(params: {
  period?: number;
  department?: string;
  status?: string;
  document_type?: string;
  review?: string;
  file?: string;
}): Promise<ControlledDocumentReport> {
  const response = await apiClient.get<{ data: ControlledDocumentReport }>(
    "/documents/controlled/reports",
    { params },
  );
  return response.data.data;
}

export async function downloadControlledDocumentReport(params: {
  period?: number;
  department?: string;
  status?: string;
  document_type?: string;
  review?: string;
  file?: string;
}): Promise<Blob> {
  const response = await apiClient.get<Blob>("/documents/controlled/reports/export", {
    params,
    responseType: "blob",
  });
  return response.data;
}

export type ControlledDocumentSavedReportFilters = {
  period: number;
  department: string | null;
  document_type: string | null;
  status: string | null;
  review: string | null;
  file: string | null;
};

export type ControlledDocumentSavedReportSchedule = {
  enabled: boolean;
  frequency: "daily" | "weekly";
  hour: number;
  day_of_week: number;
  recipients: string[];
};

export type ControlledDocumentSavedReport = {
  id: string;
  name: string;
  filters: ControlledDocumentSavedReportFilters;
  schedule: ControlledDocumentSavedReportSchedule | null;
  last_run_at: string | null;
  has_file: boolean;
  filename: string | null;
};

export async function fetchControlledDocumentSavedReports(): Promise<ControlledDocumentSavedReport[]> {
  const response = await apiClient.get<{ data: ControlledDocumentSavedReport[] }>(
    "/documents/controlled/reports/saved",
  );
  return response.data.data;
}

export async function createControlledDocumentSavedReport(body: {
  name: string;
  filters: Partial<ControlledDocumentSavedReportFilters>;
  schedule?: Partial<ControlledDocumentSavedReportSchedule> | null;
}): Promise<ControlledDocumentSavedReport> {
  const response = await apiClient.post<{ data: ControlledDocumentSavedReport }>(
    "/documents/controlled/reports/saved",
    body,
  );
  return response.data.data;
}

export async function updateControlledDocumentSavedReport(
  id: string,
  body: {
    name?: string;
    filters?: Partial<ControlledDocumentSavedReportFilters>;
    schedule?: Partial<ControlledDocumentSavedReportSchedule> | null;
  },
): Promise<ControlledDocumentSavedReport> {
  const response = await apiClient.patch<{ data: ControlledDocumentSavedReport }>(
    `/documents/controlled/reports/saved/${id}`,
    body,
  );
  return response.data.data;
}

export async function deleteControlledDocumentSavedReport(id: string): Promise<void> {
  await apiClient.delete(`/documents/controlled/reports/saved/${id}`);
}

export async function downloadControlledDocumentSavedReport(id: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/documents/controlled/reports/saved/${id}/run`, {
    responseType: "blob",
  });
  return response.data;
}

export async function downloadControlledDocumentSavedReportFile(id: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/documents/controlled/reports/saved/${id}/file`, {
    responseType: "blob",
  });
  return response.data;
}

export async function fetchControlledDocument(id: string): Promise<ControlledDocumentDetail> {
  const response = await apiClient.get<{ data: ControlledDocumentDetail }>(
    `/documents/controlled/${id}`,
  );
  return response.data.data;
}

export async function markControlledDocumentObsolete(id: string): Promise<void> {
  await apiClient.post(`/documents/controlled/${id}/obsolete`);
}

export async function downloadControlledDocumentImportTemplate(): Promise<Blob> {
  const response = await apiClient.get<Blob>("/documents/controlled/import/template", {
    responseType: "blob",
  });
  return response.data;
}

export async function analyzeControlledDocumentImport(
  file: File,
): Promise<ControlledDocumentImportAnalyzeResult> {
  const formData = new FormData();
  formData.append("file", file);
  const response = await apiClient.post<{ data: ControlledDocumentImportAnalyzeResult }>(
    "/documents/controlled/import/analyze",
    formData,
  );
  return response.data.data;
}

export async function previewControlledDocumentImport(input: {
  file: File;
  columnMap: Record<string, string>;
  availablePaths: string[];
  archive?: File | null;
}): Promise<ControlledDocumentImportRunResult> {
  const response = await apiClient.post<{ data: ControlledDocumentImportRunResult }>(
    "/documents/controlled/import/preview",
    controlledDocumentImportForm(input),
  );
  return response.data.data;
}

export async function runControlledDocumentImport(input: {
  file: File;
  columnMap: Record<string, string>;
  availablePaths: string[];
  archive?: File | null;
  files?: File[];
  filePaths?: string[];
}): Promise<ControlledDocumentImportRunResult> {
  const formData = controlledDocumentImportForm(input);
  input.files?.forEach((file) => formData.append("files[]", file));
  if (input.filePaths) {
    formData.append("file_paths", JSON.stringify(input.filePaths));
  }
  const response = await apiClient.post<{ data: ControlledDocumentImportRunResult }>(
    "/documents/controlled/import/run",
    formData,
  );
  return response.data.data;
}

function controlledDocumentImportForm(input: {
  file: File;
  columnMap: Record<string, string>;
  availablePaths: string[];
  archive?: File | null;
}): FormData {
  const formData = new FormData();
  formData.append("file", input.file);
  formData.append("column_map", JSON.stringify(input.columnMap));
  formData.append("available_paths", JSON.stringify(input.availablePaths));
  if (input.archive) {
    formData.append("archive", input.archive);
  }
  return formData;
}

export async function importControlledDocumentsCsv(file: File): Promise<ControlledDocumentImportResult> {
  const formData = new FormData();
  formData.append("file", file);
  const response = await apiClient.post<{ data: ControlledDocumentImportResult }>(
    "/documents/controlled/import",
    formData,
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}

export async function uploadControlledRevisionFile(
  documentId: string,
  revisionId: string,
  file: File,
): Promise<void> {
  const formData = new FormData();
  formData.append("file", file);
  await apiClient.post(`/documents/controlled/${documentId}/revisions/${revisionId}/file`, formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });
}

export async function getControlledRevisionDownloadInfo(
  documentId: string,
  revisionId: string,
): Promise<{ url: string; stream: boolean }> {
  const response = await apiClient.get<{ data: { url: string; stream: boolean } }>(
    `/documents/controlled/${documentId}/revisions/${revisionId}/download`,
  );
  return response.data.data;
}
