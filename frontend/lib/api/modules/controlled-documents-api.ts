import { apiClient } from "@/lib/api/client";
import type { ControlledDocumentRegisterAccessPayload } from "@/modules/documents/controlled-document-register-access";

export type ControlledDocumentRow = {
  id: string;
  document_code: string;
  e_approval_form_id: string | null;
  title: string;
  document_type: string | null;
  department: string | null;
  current_revision: number;
  status: string;
  effective_date: string | null;
  next_review_date: string | null;
  published_at: string | null;
  created_by_name: string | null;
};

export type ControlledDocumentRevision = {
  id: string;
  revision_number: number;
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
    published: number;
    obsolete: number;
  };
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
  sort?: string;
}): Promise<ControlledDocumentIndexPayload> {
  const response = await apiClient.get<{ data: ControlledDocumentIndexPayload }>(
    "/documents/controlled",
    { params },
  );
  return response.data.data;
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
