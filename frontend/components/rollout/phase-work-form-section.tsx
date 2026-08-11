"use client";

import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

type SectionProps = {
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
  /** Default: responsive 2-column field grid */
  columns?: "1" | "2";
};

/** Grouped block with heading for phase work / sheet forms. */
export function PhaseWorkFormSection({
  title,
  description,
  children,
  className,
  columns = "2",
}: SectionProps) {
  return (
    <section className={cn("space-y-3 border-b border-border pb-5 last:border-0 last:pb-0", className)}>
      <div>
        <h4 className="text-sm font-medium text-foreground">{title}</h4>
        {description ? <p className="mt-0.5 text-xs text-muted-foreground">{description}</p> : null}
      </div>
      <div
        className={cn(
          "grid gap-3",
          columns === "1" ? "grid-cols-1" : "grid-cols-1 sm:grid-cols-2",
        )}
      >
        {children}
      </div>
    </section>
  );
}

/** Span both columns in a PhaseWorkFormSection grid. */
export function PhaseWorkFormFieldSpan({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn("min-w-0 sm:col-span-2", className)}>{children}</div>;
}

export const phaseWorkSheetContentClass = "flex w-full flex-col sm:max-w-2xl";
export const phaseWorkSheetBodyClass = "flex-1 overflow-y-auto px-4 pb-4 sm:px-6";
export const phaseWorkSheetFooterClass =
  "shrink-0 border-t border-border bg-card px-4 py-3 sm:px-6";
