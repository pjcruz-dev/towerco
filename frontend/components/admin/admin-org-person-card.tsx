"use client";

import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { cn } from "@/lib/utils";
import { personInitials, type OrgChartNode } from "@/lib/admin/org-chart";

export function OrgPersonCard({
  person,
  emphasis = "default",
  compact = false,
  onSelect,
}: {
  person: OrgChartNode;
  emphasis?: "manager" | "focus" | "default";
  compact?: boolean;
  onSelect: (id: string) => void;
}) {
  const focused = emphasis === "focus";

  return (
    <button
      type="button"
      onClick={() => onSelect(person.id)}
      className={cn(
        "rounded-xl border bg-card text-left shadow-sm transition-colors",
        compact ? "w-[200px] px-3 py-2.5" : "w-full max-w-[280px] px-4 py-3",
        focused
          ? "border-foreground/30 ring-2 ring-foreground/15"
          : "border-border hover:border-foreground/25 hover:bg-muted/30",
      )}
    >
      <div className="flex items-start gap-3">
        <Avatar size={focused && !compact ? "lg" : compact ? "sm" : "default"} className="mt-0.5 shrink-0">
          <AvatarFallback>{personInitials(person.name)}</AvatarFallback>
        </Avatar>
        <div className="min-w-0 flex-1">
          <p className={cn("truncate text-sm text-foreground", focused ? "font-semibold" : "font-medium")}>
            {person.name}
          </p>
          {person.job_title ? (
            <p className="truncate text-xs text-muted-foreground">{person.job_title}</p>
          ) : null}
          {person.email ? <p className="truncate text-xs text-muted-foreground">{person.email}</p> : null}
          {person.external ? (
            <p className="mt-1 text-xs text-muted-foreground">In Microsoft Entra only</p>
          ) : person.direct_report_count > 0 ? (
            <p className="mt-1 text-xs text-muted-foreground">
              {person.direct_report_count} direct report{person.direct_report_count === 1 ? "" : "s"}
            </p>
          ) : null}
        </div>
      </div>
    </button>
  );
}
