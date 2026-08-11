import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";

/** Soft-refresh indicator — spinner only (no "Updating…" copy). */
function RefreshingHint({
  className,
  label = "Updating",
}: {
  className?: string;
  label?: string;
}) {
  return (
    <div
      className={cn("inline-flex items-center text-muted-foreground", className)}
      role="status"
      aria-live="polite"
    >
      <Spinner className="size-3.5" aria-label={label} />
    </div>
  );
}

export { RefreshingHint };
