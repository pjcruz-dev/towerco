import type { ReactNode } from "react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

type DashboardWidgetProps = {
  title: string;
  children: ReactNode;
  className?: string;
  action?: ReactNode;
};

export function DashboardWidget({ title, children, className, action }: DashboardWidgetProps) {
  return (
    <Card className={cn("overflow-hidden rounded-xl border-border shadow-sm", className)}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b border-border bg-card p-4">
        <CardTitle className="text-sm font-medium leading-snug tracking-tight text-muted-foreground">
          {title}
        </CardTitle>
        {action}
      </CardHeader>
      <CardContent className="p-4">{children}</CardContent>
    </Card>
  );
}
