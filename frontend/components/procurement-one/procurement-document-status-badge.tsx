import { cn } from "@/lib/utils";

const statusStyles: Record<string, string> = {
  draft: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
  pending_approval: "bg-amber-500/10 text-amber-800 dark:text-amber-300",
  approved: "bg-green-500/10 text-green-700 dark:text-green-300",
  sent: "bg-blue-500/10 text-blue-700 dark:text-blue-300",
  partially_received: "bg-indigo-500/10 text-indigo-700 dark:text-indigo-300",
  received: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
  converted: "bg-violet-500/10 text-violet-700 dark:text-violet-300",
  closed: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
  cancelled: "bg-red-500/10 text-red-700 dark:text-red-300",
  voided: "bg-red-500/10 text-red-700 dark:text-red-300",
  posted: "bg-green-500/10 text-green-700 dark:text-green-300",
};

type Props = {
  status: string;
  label?: string;
};

export function ProcurementDocumentStatusBadge({ status, label }: Props) {
  return (
    <span
      className={cn(
        "inline-flex rounded-md px-2 py-0.5 text-xs font-medium",
        statusStyles[status] ?? "bg-muted text-muted-foreground",
      )}
    >
      {label ?? status.replaceAll("_", " ")}
    </span>
  );
}

export function ProcurementPrStatusBadge({ status, label }: Props) {
  return <ProcurementDocumentStatusBadge status={status} label={label} />;
}

export function ProcurementPoStatusBadge({ status, label }: Props) {
  return <ProcurementDocumentStatusBadge status={status} label={label} />;
}

export function ProcurementGrnStatusBadge({ status, label }: Props) {
  return <ProcurementDocumentStatusBadge status={status} label={label} />;
}
