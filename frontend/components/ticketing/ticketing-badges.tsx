import { StatusBadge } from "@/components/ui/status-badge";
import type { StatusTone } from "@/components/ui/status-tone";

const statusTone: Record<string, StatusTone> = {
  open: "info",
  in_progress: "warning",
  resolved: "success",
  closed: "neutral",
};

const priorityTone: Record<string, StatusTone> = {
  low: "neutral",
  normal: "neutral",
  high: "warning",
  urgent: "danger",
};

function labelize(value: string): string {
  return value.replace(/_/g, " ");
}

export function TicketingStatusBadge({ status }: { status: string }) {
  return (
    <StatusBadge tone={statusTone[status] ?? "neutral"} className="capitalize">
      {labelize(status)}
    </StatusBadge>
  );
}

export function TicketingPriorityBadge({ priority }: { priority: string }) {
  return (
    <StatusBadge tone={priorityTone[priority] ?? "neutral"} className="capitalize">
      {labelize(priority)}
    </StatusBadge>
  );
}
