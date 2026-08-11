"use client";

import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

type Props = {
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
};

export function PlatformBillingFormSection({ title, description, children, className }: Props) {
  return (
    <section
      className={cn(
        "space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm",
        className,
      )}
    >
      <div className="space-y-1">
        <h3 className="text-sm font-medium text-foreground">{title}</h3>
        {description ? <p className="text-xs leading-relaxed text-muted-foreground">{description}</p> : null}
      </div>
      {children}
    </section>
  );
}
