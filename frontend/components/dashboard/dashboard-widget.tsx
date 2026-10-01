import type { ReactNode } from "react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

type DashboardWidgetProps = {
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
  contentClassName?: string;
  action?: ReactNode;
  /** Large figure under the title, as on analytics boards. */
  stat?: string;
  /** Signed change next to the stat, for example +8.4%. */
  delta?: string | null;
  deltaTone?: "success" | "danger" | "neutral";
  /** Tighter header for dense operational boards */
  dense?: boolean;
};

/**
 * Shared operational widget chrome — Azure/ServiceNow calm: clear title, soft border, no visual noise.
 */
export function DashboardWidget({
  title,
  description,
  children,
  className,
  contentClassName,
  action,
  stat,
  delta,
  deltaTone = "neutral",
  dense = false,
}: DashboardWidgetProps) {
  const deltaClass =
    deltaTone === "success"
      ? "text-success"
      : deltaTone === "danger"
        ? "text-destructive"
        : "text-muted-foreground";

  return (
    <Card
      className={cn(
        "flex h-full flex-col overflow-hidden rounded-xl border-border bg-card shadow-sm",
        className,
      )}
    >
      <CardHeader
        className={cn(
          "flex flex-row items-start justify-between gap-3 space-y-0",
          dense ? "px-3.5 pb-0 pt-3" : "px-5 pb-0 pt-5",
        )}
      >
        <div className="min-w-0 space-y-1">
          <CardTitle className="text-sm font-medium leading-snug text-foreground">{title}</CardTitle>
          {description ? (
            <p className="text-[11px] leading-snug text-muted-foreground">{description}</p>
          ) : null}
          {stat ? (
            <p className="pt-1 text-2xl font-semibold tabular-nums tracking-tight text-foreground">{stat}</p>
          ) : null}
          {delta ? (
            <p className={cn("text-xs font-medium tabular-nums", deltaClass)}>
              {delta}
              <span className="ml-1 font-normal text-muted-foreground">over this range</span>
            </p>
          ) : null}
        </div>
        {action ? <div className="shrink-0">{action}</div> : null}
      </CardHeader>
      <CardContent className={cn("flex flex-1 flex-col p-4", dense && "p-3.5", contentClassName)}>
        {children}
      </CardContent>
    </Card>
  );
}

export function DashboardWidgetEmpty({
  message,
  className,
}: {
  message: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex min-h-[7rem] flex-1 items-center justify-center rounded-lg border border-dashed border-border bg-muted/15 px-4 py-6 text-center",
        className,
      )}
    >
      <p className="max-w-xs text-xs leading-relaxed text-muted-foreground">{message}</p>
    </div>
  );
}
