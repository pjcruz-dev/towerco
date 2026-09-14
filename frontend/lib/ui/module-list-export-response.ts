/**
 * Parse sync blob / async JSON export responses used by module list toolkits.
 */

export type ModuleListExportResult =
  | {
      mode: "sync";
      blob: Blob;
      truncated: boolean;
      totalRows: number;
      maxRows: number;
    }
  | {
      mode: "async";
      matchedRows: number;
      maxRows: number;
      message: string;
      exportId?: string;
    };

export async function parseModuleListExportResponse(response: {
  status: number;
  data: Blob | { data?: Record<string, unknown> };
  headers: Record<string, unknown>;
}): Promise<ModuleListExportResult> {
  const toNumber = (value: unknown): number => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  };

  if (response.status === 202) {
    let payload: Record<string, unknown> | null = null;
    if (response.data instanceof Blob) {
      const text = await response.data.text();
      try {
        const parsed = JSON.parse(text) as { data?: Record<string, unknown> };
        payload = parsed.data ?? null;
      } catch {
        payload = null;
      }
    } else if (response.data && typeof response.data === "object" && "data" in response.data) {
      payload = (response.data.data as Record<string, unknown>) ?? null;
    }

    const exportRow = (payload?.export ?? null) as { id?: string } | null;

    return {
      mode: "async",
      matchedRows: toNumber(payload?.matched_rows),
      maxRows: toNumber(payload?.max_rows) || 50000,
      message:
        typeof payload?.message === "string"
          ? payload.message
          : "Export queued. You will be notified when the download is ready.",
      exportId: exportRow?.id ? String(exportRow.id) : undefined,
    };
  }

  if (!(response.data instanceof Blob)) {
    throw new Error("Unexpected export response.");
  }

  return {
    mode: "sync",
    blob: response.data,
    truncated: String(response.headers["x-export-truncated"] ?? "0") === "1",
    totalRows: toNumber(response.headers["x-export-total-rows"]),
    maxRows: toNumber(response.headers["x-export-max-rows"]) || 5000,
  };
}
