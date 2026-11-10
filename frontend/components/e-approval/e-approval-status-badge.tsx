import { StatusBadge } from "@/components/ui/status-badge";
import {
  formatEApprovalStatusLabel,
  resolveEApprovalStatusTone,
  type EApprovalStatusKind,
} from "@/modules/e-approval/status-display";

type Props = {
  status: string;
  label?: string | null;
  kind: EApprovalStatusKind;
  className?: string;
};

export function EApprovalStatusBadge({ status, label, kind, className }: Props) {
  const normalized = status.trim();
  const text = label?.trim() || formatEApprovalStatusLabel(normalized);
  if (!normalized && !text) {
    return <span className="text-muted-foreground">—</span>;
  }
  const toneStatus = text === "Pending publication" ? "pending" : normalized || "pending";

  return (
    <StatusBadge tone={resolveEApprovalStatusTone(toneStatus, kind)} className={className}>
      {text}
    </StatusBadge>
  );
}
