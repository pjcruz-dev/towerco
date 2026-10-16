import { settlementVerdictFromValues } from "@/modules/e-approval/settlement-verdict";
import { cn } from "@/lib/utils";

type ValueRow = {
  field_name?: string | null;
  value?: string | null;
};

export function EApprovalSettlementVerdict({
  values,
  className,
}: {
  values: ValueRow[];
  className?: string;
}) {
  const verdict = settlementVerdictFromValues(values);
  if (!verdict) {
    return null;
  }

  return (
    <div
      className={cn(
        "rounded-lg border px-3 py-2.5",
        verdict.direction === "surrender" && "border-amber-300/80 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100",
        verdict.direction === "payment" && "border-primary/30 bg-primary/10 text-foreground",
        verdict.direction === "settled" && "border-border bg-muted/40 text-foreground",
        className,
      )}
    >
      <p className="text-sm font-medium">{verdict.title}</p>
      <p className="mt-0.5 text-xs text-current/80">{verdict.detail}</p>
    </div>
  );
}
