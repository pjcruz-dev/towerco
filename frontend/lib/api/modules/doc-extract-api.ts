import type { PaginatedMeta } from "@/lib/api/paginated";
import type {
  DocExtractBatchDetail,
  DocExtractBatchListRow,
  DocExtractConsolidateRecord,
  DocExtractDocument,
  DocExtractField,
  DocExtractPreviewFile,
  DocExtractTemplate,
} from "@/modules/doc-extract/types";
import { apiClient } from "@/lib/api/client";
import { moduleListExportParamsSerializer } from "@/lib/api/module-list-export-params";
import { parseModuleListExportResponse, type ModuleListExportResult } from "@/lib/ui/module-list-export-response";

export async function fetchDocExtractTemplates(params?: {
  status?: "draft" | "published";
}): Promise<DocExtractTemplate[]> {
  const response = await apiClient.get<{ data: DocExtractTemplate[] }>("/doc-extract/templates", {
    params,
  });
  return response.data.data;
}

export async function createDocExtractTemplate(input: {
  name: string;
  description?: string | null;
  status?: "draft" | "published";
  fields: DocExtractField[];
}): Promise<DocExtractTemplate> {
  const response = await apiClient.post<{ data: DocExtractTemplate }>("/doc-extract/templates", input);
  return response.data.data;
}

export async function updateDocExtractTemplate(
  id: string,
  input: {
    name?: string;
    description?: string | null;
    status?: "draft" | "published";
    fields?: DocExtractField[];
  },
): Promise<DocExtractTemplate> {
  const response = await apiClient.put<{ data: DocExtractTemplate }>(`/doc-extract/templates/${id}`, input);
  return response.data.data;
}

export async function deleteDocExtractTemplate(id: string): Promise<void> {
  await apiClient.delete(`/doc-extract/templates/${id}`);
}

export async function fetchDocExtractBatches(params?: {
  page?: number;
  per_page?: number;
  status?: string;
  search?: string;
  sort?: string;
}): Promise<{ data: DocExtractBatchListRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: DocExtractBatchListRow[]; meta: PaginatedMeta }>(
    "/doc-extract/batches",
    { params },
  );
  return { data: response.data.data, meta: response.data.meta };
}

export async function downloadDocExtractBatchesExport(params?: {
  format?: "csv" | "xlsx" | "html";
  status?: string;
  search?: string;
  sort?: string;
  columns?: string[];
  ids?: string[];
  async?: boolean;
}): Promise<ModuleListExportResult> {
  const response = await apiClient.get<Blob | { data: Record<string, unknown> }>("/doc-extract/batches/export", {
    params: {
      format: params?.format ?? "csv",
      status: params?.status && params.status !== "all" ? params.status : undefined,
      search: params?.search?.trim() || undefined,
      sort: params?.sort || undefined,
      columns: params?.columns && params.columns.length > 0 ? params.columns : undefined,
      ids: params?.ids && params.ids.length > 0 ? params.ids : undefined,
      async: params?.async ? 1 : undefined,
    },
    paramsSerializer: moduleListExportParamsSerializer,
    responseType: "blob",
    validateStatus: (status) => (status >= 200 && status < 300) || status === 202,
  });
  return parseModuleListExportResponse(response);
}

export async function fetchDocExtractBatch(id: string): Promise<DocExtractBatchDetail> {
  const response = await apiClient.get<{ data: DocExtractBatchDetail }>(`/doc-extract/batches/${id}`);
  return response.data.data;
}

export async function requeueDocExtractBatch(
  batchId: string,
): Promise<DocExtractBatchListRow & { requeued: number }> {
  const response = await apiClient.post<{ data: DocExtractBatchListRow & { requeued: number } }>(
    `/doc-extract/batches/${batchId}/requeue`,
  );
  return response.data.data;
}

export async function previewDocExtractFiles(files: File[]): Promise<DocExtractPreviewFile[]> {
  const form = new FormData();
  for (const file of files) {
    form.append("files[]", file);
  }
  const response = await apiClient.post<{ data: { files: DocExtractPreviewFile[] } }>(
    "/doc-extract/preview",
    form,
    { timeout: 180_000 },
  );
  const filesPayload = response.data?.data?.files;
  if (!Array.isArray(filesPayload)) {
    throw new Error("Preview response was incomplete. Try again.");
  }
  return filesPayload;
}

export async function createDocExtractBatch(input: {
  templateId?: string | null;
  files: File[];
  splitPages?: boolean;
  records?: DocExtractConsolidateRecord[];
  filePageCounts?: Record<number, number>;
}): Promise<DocExtractBatchDetail | DocExtractBatchListRow> {
  const form = new FormData();
  if (input.templateId) {
    form.append("template_id", input.templateId);
  }
  if (input.splitPages && !input.records?.length) {
    form.append("split_pages", "1");
  }
  if (input.records?.length) {
    form.append(
      "records",
      JSON.stringify(
        input.records.map((record) => ({
          file_index: record.fileIndex,
          pages: record.pages,
          label: record.label,
        })),
      ),
    );
  }
  if (input.filePageCounts && Object.keys(input.filePageCounts).length > 0) {
    form.append("file_page_counts", JSON.stringify(input.filePageCounts));
  }
  for (const file of input.files) {
    form.append("files[]", file);
  }
  // Large PDFs need a long upload window; OCR runs after the response.
  // Do not set Content-Type manually — axios must include the multipart boundary.
  const response = await apiClient.post<{ data: DocExtractBatchDetail | DocExtractBatchListRow }>(
    "/doc-extract/batches",
    form,
    { timeout: 180_000 },
  );
  const batch = response.data?.data;
  if (!batch || typeof batch !== "object" || !("id" in batch) || !batch.id) {
    throw new Error(
      "Upload finished but the API returned an incomplete response. Open Batches — the extraction may already be there and processing.",
    );
  }
  return batch;
}

export async function saveDocExtractBatchAsTemplate(
  batchId: string,
  input: { name: string; description?: string | null },
): Promise<DocExtractTemplate> {
  const response = await apiClient.post<{ data: DocExtractTemplate }>(
    `/doc-extract/batches/${batchId}/save-template`,
    input,
  );
  return response.data.data;
}

export async function removeDocExtractBatchField(
  batchId: string,
  fieldKey: string,
): Promise<DocExtractBatchDetail> {
  const response = await apiClient.patch<{ data: DocExtractBatchDetail }>(
    `/doc-extract/batches/${batchId}/fields`,
    { remove_key: fieldKey },
  );
  return response.data.data;
}

export async function updateDocExtractBatchFields(
  batchId: string,
  fields: DocExtractField[],
): Promise<DocExtractBatchDetail> {
  const response = await apiClient.patch<{ data: DocExtractBatchDetail }>(
    `/doc-extract/batches/${batchId}/fields`,
    {
      fields: fields.map((field) => ({
        key: field.key,
        label: field.label,
        type: field.type,
        hint: field.hint ?? null,
        description: field.description ?? null,
        columns: field.type === "table" ? field.columns : undefined,
      })),
    },
  );
  return response.data.data;
}

export async function updateDocExtractDocumentFields(
  documentId: string,
  fieldValues: Record<string, string | null>,
): Promise<DocExtractDocument> {
  const response = await apiClient.patch<{ data: DocExtractDocument }>(
    `/doc-extract/documents/${documentId}`,
    { field_values: fieldValues },
  );
  return response.data.data;
}

export async function downloadDocExtractBatchExport(batchId: string, format: "csv" | "xlsx"): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/doc-extract/batches/${batchId}/export`, {
    params: { format },
    responseType: "blob",
  });
  return response.data;
}
