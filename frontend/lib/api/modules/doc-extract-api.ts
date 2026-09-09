import type { PaginatedMeta } from "@/lib/api/paginated";
import type {
  DocExtractBatchDetail,
  DocExtractBatchListRow,
  DocExtractDocument,
  DocExtractField,
  DocExtractTemplate,
} from "@/modules/doc-extract/types";
import { apiClient } from "@/lib/api/client";

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
}): Promise<{ data: DocExtractBatchListRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: DocExtractBatchListRow[]; meta: PaginatedMeta }>(
    "/doc-extract/batches",
    { params },
  );
  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchDocExtractBatch(id: string): Promise<DocExtractBatchDetail> {
  const response = await apiClient.get<{ data: DocExtractBatchDetail }>(`/doc-extract/batches/${id}`);
  return response.data.data;
}

export async function createDocExtractBatch(input: {
  templateId?: string | null;
  files: File[];
}): Promise<DocExtractBatchDetail> {
  const form = new FormData();
  if (input.templateId) {
    form.append("template_id", input.templateId);
  }
  for (const file of input.files) {
    form.append("files[]", file);
  }
  const response = await apiClient.post<{ data: DocExtractBatchDetail }>("/doc-extract/batches", form);
  return response.data.data;
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
