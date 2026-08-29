import { apiClient } from "@/lib/api/client";

export type TenantBackupRow = {
  id: string;
  tenant_id: string;
  status: string;
  name: string;
  storage_path: string | null;
  byte_size: number | null;
  triggered_by: string;
  finished_at: string | null;
  created_at: string | null;
};

export type TenantBackupListResponse = {
  data: TenantBackupRow[];
  meta: {
    total: number;
    completed: number;
    storage_bytes: number;
    latest_at: string | null;
    retention_days: number;
  };
};

export async function fetchTenantBackups(): Promise<TenantBackupListResponse> {
  const response = await apiClient.get<TenantBackupListResponse>("/admin/backups");
  return {
    data: response.data.data ?? [],
    meta: response.data.meta ?? {
      total: 0,
      completed: 0,
      storage_bytes: 0,
      latest_at: null,
      retention_days: 15,
    },
  };
}

export async function downloadTenantBackup(backupId: string, fileName?: string): Promise<void> {
  const response = await apiClient.get<Blob>(`/admin/backups/${backupId}/download`, {
    responseType: "blob",
  });
  const disposition = String(response.headers["content-disposition"] ?? "");
  const utfMatch = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  const plainMatch = /filename="?([^";]+)"?/i.exec(disposition);
  const resolvedName =
    fileName?.replace(/\.sql\.gz$/i, ".sql").replace(/\.gz$/i, ".sql") ||
    (utfMatch?.[1] ? decodeURIComponent(utfMatch[1]) : null) ||
    plainMatch?.[1] ||
    `${backupId}.sql`;

  const objectUrl = URL.createObjectURL(response.data);
  const anchor = document.createElement("a");
  anchor.href = objectUrl;
  anchor.download = resolvedName;
  anchor.click();
  URL.revokeObjectURL(objectUrl);
}
