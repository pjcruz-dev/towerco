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
  dense = false,
}: DashboardWidgetProps) {
  return (
    <Card
      className={cn(
        "flex h-full flex-col overflow-hidden rounded-xl border-border bg-card shadow-sm",
        className,
      )}
    >
      <CardHeader
        className={cn(
          "flex flex-row items-start justify-between gap-3 space-y-0 border-b border-border/80 bg-muted/20",
          dense ? "px-3.5 py-2.5" : "px-4 py-3",
        )}
      >
        <div className="min-w-0 space-y-0.5">
          <CardTitle className="text-sm font-medium leading-snug text-foreground">{title}</CardTitle>
          {description ? (
            <p className="text-[11px] leading-snug text-muted-foreground">{description}</p>
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
