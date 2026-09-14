import { apiClient } from "@/lib/api/client";
import { saveBlob } from "@/lib/ui/module-list-download";

export type ModuleListExportRow = {
  id: string;
  module: string;
  filename: string;
  format: string;
  status: string;
  matched_rows: number;
  exported_rows: number;
  truncated: boolean;
  error_message?: string | null;
  expires_at?: string | null;
  created_at?: string | null;
  download?: { url: string; stream: boolean } | null;
};

export async function fetchModuleListExports(limit = 50): Promise<ModuleListExportRow[]> {
  const response = await apiClient.get<{ data: ModuleListExportRow[] }>("/module-list-exports", {
    params: { limit },
  });
  return response.data.data;
}

export async function downloadModuleListExportFile(row: ModuleListExportRow): Promise<void> {
  const info = row.download;
  if (!info?.url) {
    throw new Error("Download is not available for this export yet.");
  }

  const blob = await apiClient.get<Blob>(info.url, { responseType: "blob" }).then((r) => r.data);
  saveBlob(blob, row.filename || "export");
}
