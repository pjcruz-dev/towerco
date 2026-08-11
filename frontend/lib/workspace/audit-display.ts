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

export function auditSeverityClassName(severity: string | null | undefined): string {
  switch (severity) {
    case "critical":
      return "border-red-300 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300";
    case "high":
      return "border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300";
    case "medium":
      return "border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300";
    case "low":
    default:
      return "border-border bg-muted/40 text-muted-foreground";
  }
}
