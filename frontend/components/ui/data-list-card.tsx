import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

type DataListCardProps = {
  children: ReactNode;
  className?: string;
};

/** Standard bordered card wrapper for registry / list tables. */
export function DataListCard({ children, className }: DataListCardProps) {
  return (
    <div className={cn("overflow-hidden rounded-xl border border-border bg-card shadow-sm", className)}>
      {children}
    </div>
  );
}
