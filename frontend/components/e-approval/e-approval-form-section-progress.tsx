"use client";

import type { EApprovalFormSectionProgress } from "@/modules/e-approval/form-section-progress";
import { cn } from "@/lib/utils";

type Props = {
  sections: EApprovalFormSectionProgress[];
  className?: string;
};

export function EApprovalFormSectionProgressNav({ sections, className }: Props) {
  if (sections.length === 0) {
    return null;
  }

  const overallCompleted = sections.reduce((sum, s) => sum + s.completed, 0);
  const overallTotal = sections.reduce((sum, s) => sum + s.total, 0);
  const overallPct = overallTotal > 0 ? Math.round((overallCompleted / overallTotal) * 100) : 0;

  return (
    <nav
      className={cn(
        "rounded-xl border border-border bg-card p-3 shadow-sm",
        className,
      )}
      aria-label="Form section progress"
    >
      <div className="mb-3 flex items-center justify-between gap-2">
        <p className="text-xs font-medium text-foreground">Progress</p>
        <p className="text-xs text-muted-foreground">
          {overallCompleted}/{overallTotal} fields · {overallPct}%
        </p>
      </div>
      <div className="mb-2 h-1.5 overflow-hidden rounded-full bg-muted">
        <div
          className="h-full rounded-full bg-primary transition-all"
          style={{ width: `${overallPct}%` }}
        />
      </div>
      <ul className="flex flex-wrap gap-1.5">
        {sections.map((section) => {
          const pct = section.total > 0 ? Math.round((section.completed / section.total) * 100) : 0;
          const done = section.total > 0 && section.completed >= section.total;

          return (
            <li key={section.id}>
              <button
                type="button"
                className={cn(
                  "rounded-md border px-2 py-1 text-left text-[11px] transition-colors",
                  done
                    ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300"
                    : "border-border bg-muted/30 text-muted-foreground hover:border-primary/30 hover:text-foreground",
                )}
                onClick={() => {
                  document.getElementById(section.id)?.scrollIntoView({ behavior: "smooth", block: "start" });
                }}
              >
                <span className="font-medium text-foreground">{section.label}</span>
                <span className="ml-1 tabular-nums">
                  {section.completed}/{section.total}
                  {pct > 0 && pct < 100 ? ` (${pct}%)` : done ? " ✓" : ""}
                </span>
              </button>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
