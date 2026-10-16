import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

/** Sits under the table scroll area so the pager clears the horizontal scrollbar. */
export function TablePageFooter({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn("flex items-center justify-end border-t border-border bg-card px-3 py-1.5", className)}>
      {children}
    </div>
  );
}
