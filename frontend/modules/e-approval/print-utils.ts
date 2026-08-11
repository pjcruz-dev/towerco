import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import { resolveEApprovalAssetUrl } from "@/lib/e-approval/resolve-asset-url";

export function resolvePrintAssetUrl(path: string | null | undefined): string | null {
  if (!path) {
    return null;
  }
  if (path.startsWith("data:")) {
    return path;
  }
  return resolveEApprovalAssetUrl(path) || null;
}

export function printFieldValueMap(payload: {
  fields: { key: string; value: string | null }[];
}): Record<string, string> {
  const map: Record<string, string> = {};
  for (const field of payload.fields) {
    map[field.key] = field.value ?? "";
  }
  return map;
}

export function formatPrintMoney(value: string | null | undefined, currency?: string): string {
  const raw = (value ?? "").trim();
  if (!raw) {
    return "—";
  }
  const numeric = Number(raw.replace(/,/g, ""));
  const formatted = Number.isFinite(numeric)
    ? numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : raw;
  const code = (currency ?? "").trim();
  return code ? `${code} ${formatted}` : formatted;
}

export function resolvePrintCurrency(
  values: Record<string, string>,
  payload: EApprovalPrintPayload,
): string {
  return values.currency_code?.trim() || values.currency?.trim() || "PHP";
}
