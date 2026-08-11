export function ticketingStatusLabel(status: string): string {
  return status.replace(/_/g, " ");
}

export function formatTicketingDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" });
}

export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function slugifyTicketingCategory(label: string): string {
  const slug = label
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .slice(0, 64);
  return slug || "custom";
}

export function ticketingCategoryLabel(
  category: string | null | undefined,
  options?: Array<{ id: string; label: string }> | null,
): string {
  if (!category) return "—";
  const match = options?.find((item) => item.id === category);
  if (match?.label) return match.label;
  return category.replace(/_/g, " ");
}
