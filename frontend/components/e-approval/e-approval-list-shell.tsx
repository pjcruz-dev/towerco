import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

type Props = {
  /** Optional; omit when filters live in a separate board slot. */
  toolbar?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  error?: ReactNode;
  className?: string;
};

/** Shared bordered list container for E-Forms table and gallery views. */
export function EApprovalListShell({ toolbar, children, footer, error, className }: Props) {
  return (
    <div className={cn("overflow-hidden rounded-xl border border-border bg-card shadow-sm", className)}>
      {toolbar != null ? <div className="border-b border-border px-4 py-3">{toolbar}</div> : null}
      {error}
      {children}
      {footer}
    </div>
  );
}
