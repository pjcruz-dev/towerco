import { apiClient } from "@/lib/api/client";
import type {
  AssetDashboardCard,
  AssetListMeta,
  AssetPerson,
  AssetRecord,
  AssetSchemaField,
  OffboardingCase,
} from "@/modules/assets/types";

export type AssetListParams = {
  q?: string;
  status?: string;
  asset_type?: string;
  lifecycle?: string;
  attention?: string;
  repair?: string;
  page?: number;
  per_page?: number;
  sort?: string;
};

export async function fetchAssetDashboard(): Promise<{ updated_at: string; cards: AssetDashboardCard[] }> {
  const response = await apiClient.get<{ data: { updated_at: string; cards: AssetDashboardCard[] } }>(
    "/assets/dashboard",
  );
  return response.data.data;
}

export async function fetchAssets(
  params: AssetListParams,
): Promise<{ rows: AssetRecord[]; meta: AssetListMeta }> {
  const response = await apiClient.get<{ data: AssetRecord[]; meta: AssetListMeta }>("/assets", { params });
  return { rows: response.data.data, meta: response.data.meta };
}

export async function fetchAsset(tagOrId: string): Promise<AssetRecord> {
  const response = await apiClient.get<{ data: AssetRecord }>(`/assets/${encodeURIComponent(tagOrId)}`);
  return response.data.data;
}

export async function createAsset(body: Record<string, unknown>): Promise<AssetRecord> {
  const response = await apiClient.post<{ data: AssetRecord }>("/assets", body);
  return response.data.data;
}

export async function updateAsset(tagOrId: string, body: Record<string, unknown>): Promise<AssetRecord> {
  const response = await apiClient.put<{ data: AssetRecord }>(`/assets/${encodeURIComponent(tagOrId)}`, body);
  return response.data.data;
}

export async function searchAssetPeople(q: string): Promise<AssetPerson[]> {
  const term = q.trim();
  const response = await apiClient.get<{ data: AssetPerson[] }>("/assets/directory", {
    params: term === "" ? undefined : { q: term },
  });
  return response.data.data;
}

export async function assignAsset(
  assetId: string,
  body: { employee_id: string; notes?: string; extra_labels?: string[] },
): Promise<{ id: string; status: string }> {
  const response = await apiClient.post<{ data: { id: string; status: string } }>(`/assets/${assetId}/assign`, body);
  return response.data.data;
}

export async function returnAsset(
  assetId: string,
  body: { status: string; condition: string; expected_tag?: string; notes?: string },
): Promise<AssetRecord> {
  const response = await apiClient.post<{ data: AssetRecord }>(`/assets/${assetId}/return`, body);
  return response.data.data;
}

export async function transferAssets(body: {
  asset_ids: string[];
  employee_id: string;
  confirm: boolean;
}): Promise<void> {
  await apiClient.post("/assets/transfers", body);
}

export async function openRepair(assetId: string, body: Record<string, unknown>): Promise<{ id: string }> {
  const response = await apiClient.post<{ data: { id: string } }>(`/assets/${assetId}/repairs`, body);
  return response.data.data;
}

export async function updateRepair(repairId: string, body: Record<string, unknown>): Promise<void> {
  await apiClient.put(`/assets/repairs/${repairId}`, body);
}

export async function disposeAsset(assetId: string, body: Record<string, unknown>): Promise<void> {
  await apiClient.post(`/assets/${assetId}/disposals`, body);
}

export async function signAccountability(id: string, signature: string): Promise<void> {
  await apiClient.post(`/assets/accountability/${id}/sign`, { signature });
}

export async function sendAccountability(id: string): Promise<void> {
  await apiClient.post(`/assets/accountability/${id}/send`, { confirm: true });
}

export async function fetchAssetSchema(): Promise<{ fields: AssetSchemaField[]; manage_fields_href?: string }> {
  const response = await apiClient.get<{ data: { fields: AssetSchemaField[]; manage_fields_href?: string } }>(
    "/assets/schema",
  );
  return response.data.data;
}

export async function createAssetField(body: { label: string; type?: string; show_in_table?: boolean }): Promise<void> {
  await apiClient.post("/assets/schema/fields", body);
}

export async function deleteAssetField(fieldId: string): Promise<void> {
  await apiClient.delete(`/assets/schema/fields/${fieldId}`);
}

export async function startOffboarding(employeeId: string, lastWorkingDay?: string): Promise<OffboardingCase> {
  const response = await apiClient.post<{ data: OffboardingCase }>("/assets/offboarding", {
    employee_id: employeeId,
    last_working_day: lastWorkingDay || null,
  });
  return response.data.data;
}

export async function fetchOffboarding(id: string): Promise<OffboardingCase> {
  const response = await apiClient.get<{ data: OffboardingCase }>(`/assets/offboarding/${id}`);
  return response.data.data;
}

export async function recordOffboardingItem(
  offboardingId: string,
  itemId: string,
  body: { scanned_tag: string; result: string; condition?: string },
): Promise<void> {
  await apiClient.post(`/assets/offboarding/${offboardingId}/items/${itemId}`, body);
}

export async function completeOffboarding(id: string): Promise<void> {
  await apiClient.post(`/assets/offboarding/${id}/complete`);
}

export async function submitInventory(body: Record<string, unknown>): Promise<AssetRecord> {
  const response = await apiClient.post<{ data: { asset: AssetRecord } }>("/assets/inventory", body);
  return response.data.data.asset;
}

export async function downloadAssetImportTemplate(): Promise<Blob> {
  const response = await apiClient.get<Blob>("/assets/import/template", {
    params: { format: "csv" },
    responseType: "blob",
  });
  return response.data;
}

export async function analyzeAssetImport(file: File): Promise<{
  headers: string[];
  suggested_map: Record<string, string>;
  fields: Array<{
    name: string;
    label: string;
    type: string;
    is_required: boolean;
    options?: string[];
    target_entity_id?: string | null;
  }>;
  sample_rows: Array<Record<string, string>>;
  row_count: number;
}> {
  const body = new FormData();
  body.append("file", file);
  const response = await apiClient.post<{
    data: {
      headers: string[];
      suggested_map: Record<string, string>;
      fields: Array<{
        name: string;
        label: string;
        type: string;
        is_required: boolean;
        options?: string[];
        target_entity_id?: string | null;
      }>;
      sample_rows: Array<Record<string, string>>;
      row_count: number;
    };
  }>("/assets/import/analyze", body);
  return response.data.data;
}

export async function runAssetImport(
  file: File,
  columnMap: Record<string, string>,
  upsertField?: string | null,
  options?: { dryRun?: boolean },
): Promise<{
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
}> {
  const body = new FormData();
  body.append("file", file);
  body.append("column_map", JSON.stringify(columnMap));
  if (upsertField) body.append("upsert_field", upsertField);
  if (options?.dryRun) body.append("dry_run", "1");
  const response = await apiClient.post<{
    data: {
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
  }>("/assets/import/run", body);
  return response.data.data;
}

export async function previewAssetImport(file: File): Promise<{ total: number; valid: number; warnings: number; errors: number }> {
  const body = new FormData();
  body.append("file", file);
  const response = await apiClient.post<{ data: { total: number; valid: number; warnings: number; errors: number } }>(
    "/assets/import/preview",
    body,
  );
  return response.data.data;
}

export async function downloadAssetExport(
  format: "csv" | "xlsx",
  params: { ids?: string; status?: string; asset_type?: string } = {},
): Promise<void> {
  const response = await apiClient.get<Blob>("/assets/export", {
    params: { format, ...params },
    responseType: "blob",
  });
  const url = URL.createObjectURL(response.data);
  const link = document.createElement("a");
  link.href = url;
  link.download = `assets.${format}`;
  link.click();
  URL.revokeObjectURL(url);
}
