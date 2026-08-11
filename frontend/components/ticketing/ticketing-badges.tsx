import { cn } from "@/lib/utils";

const statusStyles: Record<string, string> = {
  open: "bg-blue-500/10 text-blue-700 dark:text-blue-300",
  in_progress: "bg-amber-500/10 text-amber-800 dark:text-amber-300",
  resolved: "bg-green-500/10 text-green-700 dark:text-green-300",
  closed: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
};

const priorityStyles: Record<string, string> = {
  low: "bg-slate-500/10 text-slate-600 dark:text-slate-300",
  normal: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
  high: "bg-orange-500/10 text-orange-800 dark:text-orange-300",
  urgent: "bg-red-500/10 text-red-700 dark:text-red-300",
};

function labelize(value: string): string {
  return value.replace(/_/g, " ");
}

export function TicketingStatusBadge({ status }: { status: string }) {
  return (
    <span
      className={cn(
        "inline-flex rounded-md px-2 py-0.5 text-xs font-medium capitalize",
        statusStyles[status] ?? "bg-muted text-muted-foreground",
      )}
    >
      {labelize(status)}
    </span>
  );
}

export function TicketingPriorityBadge({ priority }: { priority: string }) {
  return (
    <span
      className={cn(
        "inline-flex rounded-md px-2 py-0.5 text-xs font-medium capitalize",
        priorityStyles[priority] ?? "bg-muted text-muted-foreground",
      )}
    >
      {labelize(priority)}
    </span>
  );
}
