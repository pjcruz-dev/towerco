import { StatusBadge } from "@/components/ui/status-badge";
import { ticketingSlaTone } from "@/components/ui/status-tone";

type Props = {
  status?: string | null;
};

export function TicketingSlaBadge({ status }: Props) {
  if (!status) {
    return null;
  }

  const label = status === "on_track" ? "SLA on track" : status === "at_risk" ? "SLA at risk" : "SLA breached";

  return <StatusBadge tone={ticketingSlaTone(status)}>{label}</StatusBadge>;
}
