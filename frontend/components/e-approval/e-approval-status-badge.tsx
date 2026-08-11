import { Badge } from "@/components/ui/badge";
import {
  eApprovalStatusBadgeClass,
  formatEApprovalStatusLabel,
  type EApprovalStatusKind,
} from "@/modules/e-approval/status-display";
import { cn } from "@/lib/utils";

type Props = {
  status: string;
  kind: EApprovalStatusKind;
  className?: string;
};

export function EApprovalStatusBadge({ status, kind, className }: Props) {
  const normalized = status.trim();
  if (!normalized) {
    return <span className="text-muted-foreground">—</span>;
  }

  return (
    <Badge variant="outline" className={cn("font-medium", eApprovalStatusBadgeClass(normalized, kind), className)}>
      {formatEApprovalStatusLabel(normalized)}
    </Badge>
  );
}
