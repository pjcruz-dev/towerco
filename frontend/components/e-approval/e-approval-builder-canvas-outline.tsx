"use client";

import { ChevronDown, ChevronsDownUp, ChevronsUpDown } from "lucide-react";

import { Button } from "@/components/ui/button";
import type { BuilderCanvasOutlineEntry } from "@/modules/e-approval/builder-canvas-outline";
import { cn } from "@/lib/utils";

type Props = {
  entries: BuilderCanvasOutlineEntry[];
  collapsedGroups: ReadonlySet<number>;
  activeGroupIndex: number | null;
  onJumpToGroup: (groupIndex: number) => void;
  onToggleGroup: (groupIndex: number) => void;
  onExpandAll: () => void;
  onCollapseAll: () => void;
  className?: string;
};

export function EApprovalBuilderCanvasOutline({
  entries,
  collapsedGroups,
  activeGroupIndex,
  onJumpToGroup,
  onToggleGroup,
  onExpandAll,
  onCollapseAll,
  className,
}: Props) {
  if (entries.length === 0) {
    return null;
  }

  const allCollapsed = entries.every((entry) => collapsedGroups.has(entry.groupIndex));
  const allExpanded = entries.every((entry) => !collapsedGroups.has(entry.groupIndex));

  return (
    <nav
      className={cn(
        "flex flex-col rounded-lg border border-border/80 bg-muted/20",
        className,
      )}
      aria-label="Form canvas outline"
    >
      <div className="flex items-center justify-between gap-2 border-b border-border/60 px-3 py-2">
        <p className="text-xs font-medium text-foreground">Outline</p>
        <div className="flex items-center gap-0.5">
          <Button
            type="button"
            size="icon"
            variant="ghost"
            className="h-7 w-7"
            onClick={onExpandAll}
            disabled={allExpanded}
            aria-label="Expand all sections"
            title="Expand all"
          >
            <ChevronsUpDown className="h-3.5 w-3.5" />
          </Button>
          <Button
            type="button"
            size="icon"
            variant="ghost"
            className="h-7 w-7"
            onClick={onCollapseAll}
            disabled={allCollapsed}
            aria-label="Collapse all sections"
            title="Collapse all"
          >
            <ChevronsDownUp className="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>

      <ul className="max-h-[min(520px,calc(100vh-14rem))] space-y-0.5 overflow-y-auto overscroll-y-contain p-2">
        {entries.map((entry) => {
          const collapsed = collapsedGroups.has(entry.groupIndex);
          const active = activeGroupIndex === entry.groupIndex;

          return (
            <li key={`outline-${entry.groupIndex}-${entry.anchorId}`}>
              <div
                className={cn(
                  "flex items-stretch overflow-hidden rounded-md border text-left transition-colors",
                  active
                    ? "border-primary/40 bg-primary/5"
                    : "border-transparent hover:border-border hover:bg-background",
                )}
              >
                <button
                  type="button"
                  className="shrink-0 px-1.5 py-2 text-muted-foreground hover:text-foreground"
                  onClick={() => onToggleGroup(entry.groupIndex)}
                  aria-expanded={!collapsed}
                  aria-label={collapsed ? `Expand ${entry.label}` : `Collapse ${entry.label}`}
                >
                  <ChevronDown
                    className={cn("h-3.5 w-3.5 transition-transform", collapsed && "-rotate-90")}
                  />
                </button>
                <button
                  type="button"
                  className="min-w-0 flex-1 px-1 py-2 text-left"
                  onClick={() => onJumpToGroup(entry.groupIndex)}
                >
                  <span className="block truncate text-xs font-medium text-foreground">{entry.label}</span>
                  <span className="mt-0.5 block text-[11px] text-muted-foreground tabular-nums">
                    {entry.fieldCount} field{entry.fieldCount === 1 ? "" : "s"}
                  </span>
                </button>
              </div>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
