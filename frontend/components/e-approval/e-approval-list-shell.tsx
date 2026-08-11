import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

type Props = {
  toolbar: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  error?: ReactNode;
  className?: string;
};

/** Shared bordered list container for E-Approval table and gallery views. */
export function EApprovalListShell({ toolbar, children, footer, error, className }: Props) {
  return (
    <div className={cn("overflow-hidden rounded-xl border border-border bg-card shadow-sm", className)}>
      <div className="border-b border-border px-4 py-3">{toolbar}</div>
      {error}
      {children}
      {footer}
    </div>
  );
}
