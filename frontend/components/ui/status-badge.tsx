import type { ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import { statusToneClasses, type StatusTone } from "@/components/ui/status-tone";
import { cn } from "@/lib/utils";

/**
 * Status chip per DESIGN_SYSTEM.md 16.2 / 17.1: the outline Badge plus the tone classes.
 * Always pass a text label as children (colour alone is not enough).
 */
export function StatusBadge({
  tone,
  children,
  className,
}: {
  tone: StatusTone;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Badge variant="outline" className={cn(statusToneClasses[tone].badge, className)}>
      {children}
    </Badge>
  );
}
