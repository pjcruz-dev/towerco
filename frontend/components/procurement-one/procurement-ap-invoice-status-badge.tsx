import { cn } from "@/lib/utils";

const STATUS_CLASS: Record<string, string> = {
  draft: "bg-muted text-muted-foreground",
  pending_approval: "bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200",
  approved: "bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200",
  cancelled: "bg-muted text-muted-foreground line-through",
  voided: "bg-destructive/10 text-destructive",
};

export function ProcurementApInvoiceStatusBadge({
  status,
  label,
  className,
}: {
  status: string;
  label?: string;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex rounded-md px-2 py-0.5 text-xs font-medium",
        STATUS_CLASS[status] ?? "bg-muted text-muted-foreground",
        className,
      )}
    >
      {label ?? status.replaceAll("_", " ")}
    </span>
  );
}
