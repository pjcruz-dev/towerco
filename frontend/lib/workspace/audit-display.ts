import { statusToneClasses, type StatusTone } from "@/components/ui/status-tone";

export function auditCategoryLabel(category: string | null | undefined): string {
  switch (category) {
    case "security":
      return "Security";
    case "access":
      return "Access";
    case "data_change":
      return "Data change";
    case "lifecycle":
      return "Lifecycle";
    default:
      return category ? category.replace(/_/g, " ") : "—";
  }
}

export function auditSeverityLabel(severity: string | null | undefined): string {
  switch (severity) {
    case "critical":
      return "Critical";
    case "high":
      return "High";
    case "medium":
      return "Medium";
    case "low":
      return "Low";
    default:
      return severity ? severity.replace(/_/g, " ") : "—";
  }
}

export function auditSeverityTone(severity: string | null | undefined): StatusTone {
  switch (severity) {
    case "critical":
      return "danger";
    case "high":
      return "warning";
    case "medium":
      return "info";
    default:
      return "neutral";
  }
}

/** Badge class for callers that still spread a className. Prefer <StatusBadge>. */
export function auditSeverityClassName(severity: string | null | undefined): string {
  return statusToneClasses[auditSeverityTone(severity)].badge;
}
