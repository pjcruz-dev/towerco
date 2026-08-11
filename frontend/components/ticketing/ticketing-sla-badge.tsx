import { Badge } from "@/components/ui/badge";

type Props = {
  status?: string | null;
};

export function TicketingSlaBadge({ status }: Props) {
  if (!status) {
    return null;
  }

  const label = status === "on_track" ? "SLA on track" : status === "at_risk" ? "SLA at risk" : "SLA breached";
  const className =
    status === "on_track"
      ? "border-emerald-200 text-emerald-700 dark:border-emerald-900 dark:text-emerald-400"
      : status === "at_risk"
        ? "border-amber-200 text-amber-800 dark:border-amber-900 dark:text-amber-300"
        : "border-destructive/30 text-destructive";

  return (
    <Badge variant="outline" className={className}>
      {label}
    </Badge>
  );
}
