import { apiClient } from "@/lib/api/client";

export type DocumentSiteNode = {
  id: string;
  parent_id: string | null;
  node_key: string;
  label: string;
  node_type: string;
  sort_order: number;
  lessor_name: string | null;
  lessor_contact: string | null;
  document_count: number;
};

export type DocumentFileRow = {
  id: string;
  site_id: string;
  site_node_id: string;
  title: string;
  original_filename: string;
  mime_type: string;
  size_bytes: number;
  status: string;
  version: number;
  expires_at: string | null;
  sort_order: number;
  uploaded_by: { id: string; name: string } | null;
  uploaded_at: string | null;
  last_touched_by: { id: string; name: string } | null;
  last_touched_at: string | null;
  approval_status?: string;
  e_approval_submission_id?: string | null;
  e_approval_submission?: {
    id: string;
    document_no: string;
    status: string;
    form_name?: string | null;
    href: string;
  } | null;
};

export type DocumentDetail = DocumentFileRow & {
  download_url: string;
  activities: {
    id: string;
    event: string;
    at: string | null;
    actor: { id: string; name: string } | null;
    metadata: Record<string, unknown> | null;
  }[];
  versions: {
    version: number;
    original_filename: string;
    size_bytes: number;
    uploaded_at: string | null;
    uploaded_by: { id: string; name: string } | null;
  }[];
};

export type DocumentWorkspacePayload = {
  workspace: {
    id: string;
    site_id: string;
    rollout_program_id: string | null;
  };
  nodes: DocumentSiteNode[];
  last_activity: {
    document_id: string;
    title: string;
    at: string | null;
    by: { id: string; name: string } | null;
  } | null;
};

export type DocumentExpiringPayload = {
  summary: { within_30: number; within_60: number; within_90: number };
  items: {
    id: string;
    title: string;
    expires_at: string | null;
    status: string;
    site: { id: string; site_code: string; name: string } | null;
    last_touched_by: { id: string; name: string } | null;
    last_touched_at: string | null;
  }[];
};

export async function fetchDocumentDetail(documentId: string): Promise<DocumentDetail> {
  const response = await apiClient.get<{ data: DocumentDetail }>(
    `/documents/files/${documentId}`,
  );
  return response.data.data;
}

export async function uploadDocumentVersion(documentId: string, file: File): Promise<DocumentFileRow> {
  const form = new FormData();
  form.append("file", file);

  const response = await apiClient.post<{ data: DocumentFileRow }>(
    `/documents/files/${documentId}/versions`,
    form,
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}

export async function fetchSiteDocumentWorkspace(siteId: string): Promise<DocumentWorkspacePayload> {
  const response = await apiClient.get<{ data: DocumentWorkspacePayload }>(
    `/sites/${siteId}/documents/workspace`,
  );
  return response.data.data;
}

export async function fetchSiteDocumentFiles(siteId: string, nodeId: string): Promise<DocumentFileRow[]> {
  const response = await apiClient.get<{ data: { items: DocumentFileRow[] } }>(
    `/sites/${siteId}/documents/files`,
    { params: { node_id: nodeId } },
  );
  return response.data.data.items;
}

export type DocumentUploadCapabilities = {
  direct_upload_enabled: boolean;
  presigned_min_bytes: number;
  max_size_bytes: number;
  cad_extensions: string[];
  multipart_fallback: boolean;
};

export type DocumentPresignPayload = {
  upload_token: string;
  document_id: string;
  upload_url: string;
  upload_method: "PUT";
  upload_headers: Record<string, string>;
  stored_path: string;
  expires_at: string;
  cad_file: boolean;
};

function isCadFilename(filename: string, cadExtensions: string[]): boolean {
  const extension = filename.split(".").pop()?.toLowerCase() ?? "";
  return extension !== "" && cadExtensions.includes(extension);
}

export async function fetchDocumentUploadCapabilities(): Promise<DocumentUploadCapabilities> {
  const response = await apiClient.get<{ data: DocumentUploadCapabilities }>(
    "/documents/upload-capabilities",
  );
  return response.data.data;
}

export async function presignSiteDocumentUpload(
  siteId: string,
  payload: {
    site_node_id: string;
    filename: string;
    mime_type: string;
    size_bytes: number;
  },
): Promise<DocumentPresignPayload> {
  const response = await apiClient.post<{ data: DocumentPresignPayload }>(
    `/sites/${siteId}/documents/files/presign`,
    payload,
  );
  return response.data.data;
}

export async function completeSiteDocumentUpload(
  siteId: string,
  payload: { upload_token: string; title?: string; expires_at?: string },
): Promise<DocumentFileRow> {
  const response = await apiClient.post<{ data: DocumentFileRow }>(
    `/sites/${siteId}/documents/files/complete`,
    payload,
  );
  return response.data.data;
}

export async function uploadSiteDocument(
  siteId: string,
  payload: { site_node_id: string; file: File; title?: string; expires_at?: string },
): Promise<DocumentFileRow> {
  const form = new FormData();
  form.append("site_node_id", payload.site_node_id);
  form.append("file", payload.file);
  if (payload.title) form.append("title", payload.title);
  if (payload.expires_at) form.append("expires_at", payload.expires_at);

  const response = await apiClient.post<{ data: DocumentFileRow }>(
    `/sites/${siteId}/documents/files`,
    form,
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}

export async function uploadSiteDocumentSmart(
  siteId: string,
  payload: { site_node_id: string; file: File; title?: string; expires_at?: string },
  capabilities?: DocumentUploadCapabilities,
): Promise<DocumentFileRow> {
  const caps = capabilities ?? (await fetchDocumentUploadCapabilities());
  const mimeType = payload.file.type || "application/octet-stream";
  const usePresigned =
    caps.direct_upload_enabled &&
    (payload.file.size >= caps.presigned_min_bytes ||
      isCadFilename(payload.file.name, caps.cad_extensions));

  if (!usePresigned) {
    return uploadSiteDocument(siteId, payload);
  }

  const presign = await presignSiteDocumentUpload(siteId, {
    site_node_id: payload.site_node_id,
    filename: payload.file.name,
    mime_type: mimeType,
    size_bytes: payload.file.size,
  });

  const putResponse = await fetch(presign.upload_url, {
    method: presign.upload_method,
    headers: presign.upload_headers,
    body: payload.file,
  });

  if (!putResponse.ok) {
    throw new Error(`Direct upload failed (${putResponse.status})`);
  }

  return completeSiteDocumentUpload(siteId, {
    upload_token: presign.upload_token,
    title: payload.title,
    expires_at: payload.expires_at,
  });
}

export async function addSiteDocumentLessor(
  siteId: string,
  payload: { lessor_name: string; lessor_contact?: string },
): Promise<{ instance: { id: string; label: string; upload_node_id: string } }> {
  const response = await apiClient.post<{
    data: { instance: { id: string; label: string; upload_node_id: string } };
  }>(`/sites/${siteId}/documents/lessors`, payload);
  return response.data.data;
}

export async function updateSiteDocumentMetadata(
  documentId: string,
  payload: { title?: string; status?: string; expires_at?: string | null },
): Promise<DocumentFileRow> {
  const response = await apiClient.patch<{ data: DocumentFileRow }>(
    `/documents/files/${documentId}/metadata`,
    payload,
  );
  return response.data.data;
}

export async function fetchExpiringDocuments(days = 90): Promise<DocumentExpiringPayload> {
  const response = await apiClient.get<{ data: DocumentExpiringPayload }>("/documents/expiring", {
    params: { days },
  });
  return response.data.data;
}

export async function getDocumentDownloadUrl(documentId: string): Promise<string> {
  const response = await apiClient.get<{ data: { url: string } }>(
    `/documents/files/${documentId}/download`,
  );
  return response.data.data.url;
}

export async function updateSiteDocumentWorkspace(
  siteId: string,
  payload: { rollout_program_id: string | null },
): Promise<DocumentWorkspacePayload> {
  const response = await apiClient.patch<{ data: DocumentWorkspacePayload }>(
    `/sites/${siteId}/documents/workspace`,
    payload,
  );
  return response.data.data;
}

export type DocumentGateChecklist = {
  site_id: string;
  rollout_program_id: string | null;
  summary: { required: number; met: number; complete: boolean };
  items: {
    node_key: string;
    label: string;
    required: boolean;
    met: boolean;
    final_document_count: number;
  }[];
};

export async function fetchSiteDocumentGateChecklist(siteId: string): Promise<DocumentGateChecklist> {
  const response = await apiClient.get<{ data: DocumentGateChecklist }>(
    `/sites/${siteId}/documents/gate-checklist`,
  );
  return response.data.data;
}

export type DocumentBinderTemplate = {
  source: string;
  editable: boolean;
  tree: BinderTemplateNode[];
  note: string;
  updated_at?: string | null;
  updated_by?: { id: string; name: string } | null;
};

export type BinderTemplateNode = {
  key: string;
  label: string;
  type: string;
  children?: BinderTemplateNode[];
};

export async function fetchDocumentBinderTemplate(): Promise<DocumentBinderTemplate> {
  const response = await apiClient.get<{ data: DocumentBinderTemplate }>("/documents/binder-template");
  return response.data.data;
}

export async function updateDocumentBinderTemplate(tree: BinderTemplateNode[]): Promise<DocumentBinderTemplate> {
  const response = await apiClient.put<{ data: DocumentBinderTemplate }>("/documents/binder-template", { tree });
  return response.data.data;
}

export async function resetDocumentBinderTemplate(): Promise<DocumentBinderTemplate> {
  const response = await apiClient.post<{ data: DocumentBinderTemplate }>("/documents/binder-template/reset");
  return response.data.data;
}

export async function requestDocumentApproval(
  documentId: string,
  payload: { form_id: string; values?: Record<string, unknown> },
): Promise<{
  document: DocumentFileRow;
  submission: { id: string; document_no: string; status: string; href: string };
}> {
  const response = await apiClient.post<{
    data: {
      document: DocumentFileRow;
      submission: { id: string; document_no: string; status: string; href: string };
    };
  }>(`/documents/files/${documentId}/request-approval`, payload);
  return response.data.data;
}

export type RolloutProgramOption = {
  id: string;
  rollout_ref: string;
  status: string;
  site_match?: boolean;
};

export async function fetchRolloutProgramOptions(
  siteId: string,
  search = "",
): Promise<RolloutProgramOption[]> {
  const response = await apiClient.get<{ data: RolloutProgramOption[] }>(
    `/sites/${siteId}/documents/rollout-options`,
    { params: { search: search || undefined } },
  );
  return response.data.data;
}

export type EApprovalFormOption = { id: string; name: string; status: string };

export async function migrateRolloutLeasePackage(
  rolloutId: string,
  payload?: { candidate_id?: string },
): Promise<{ migrated: number; skipped: number; errors: string[]; documents: string[] }> {
  const response = await apiClient.post<{
    data: { migrated: number; skipped: number; errors: string[]; documents: string[] };
  }>(`/project-one/rollouts/${rolloutId}/documents/migrate-lease-package`, payload ?? {});
  return response.data.data;
}

export async function fetchPublishedEApprovalForms(): Promise<EApprovalFormOption[]> {
  const response = await apiClient.get<{
    data: { id: string; name: string; status: string }[];
  }>("/e-approval/forms", {
    params: { per_page: 50, status: "published" },
  });
  return response.data.data;
}
