import { cn } from "@/lib/utils";

const statusStyles: Record<string, string> = {
  pending: "bg-amber-500/10 text-amber-800 dark:text-amber-300",
  accredited: "bg-green-500/10 text-green-700 dark:text-green-300",
  suspended: "bg-red-500/10 text-red-700 dark:text-red-300",
  expired: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
};

type Props = {
  status: string;
  label?: string;
};

export function ProcurementVendorAccreditationBadge({ status, label }: Props) {
  return (
    <span
      className={cn(
        "inline-flex rounded-md px-2 py-0.5 text-xs font-medium",
        statusStyles[status] ?? "bg-muted text-muted-foreground",
      )}
    >
      {label ?? status.replace(/_/g, " ")}
    </span>
  );
}
