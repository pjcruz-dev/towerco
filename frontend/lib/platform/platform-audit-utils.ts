import type { PlatformTenantAuditRow } from "@/lib/api/modules/platform-api";

function formatValue(value: unknown): string {
  if (value === null || value === undefined) {
    return "—";
  }
  if (typeof value === "boolean") {
    return value ? "on" : "off";
  }
  if (Array.isArray(value)) {
    return value.join(", ") || "—";
  }
  if (typeof value === "object") {
    return JSON.stringify(value);
  }

  return String(value);
}

function formatFieldLabel(field: string): string {
  return field.replaceAll("_", " ");
}

export function formatAuditChangeSummary(
  entry: PlatformTenantAuditRow,
): string {
  if (entry.changes && Object.keys(entry.changes).length > 0) {
    return Object.entries(entry.changes)
      .map(([field, change]) => {
        const from = formatValue(change.from);
        const to = formatValue(change.to);
        return `${formatFieldLabel(field)}: ${from} → ${to}`;
      })
      .join(" · ");
  }

  if (entry.metadata && Object.keys(entry.metadata).length > 0) {
    const parts: string[] = [];
    if (typeof entry.metadata.target_email === "string") {
      parts.push(`as ${entry.metadata.target_email}`);
    }
    if (typeof entry.metadata.domain === "string") {
      parts.push(entry.metadata.domain);
    }
    if (typeof entry.metadata.environment === "string") {
      parts.push(entry.metadata.environment);
    }
    if (typeof entry.metadata.reason === "string") {
      parts.push(`reason: ${entry.metadata.reason}`);
    }
    if (parts.length > 0) {
      return parts.join(" · ");
    }
  }

  return entry.event_label;
}
