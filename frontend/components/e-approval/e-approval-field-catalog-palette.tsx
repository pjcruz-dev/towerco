"use client";

import { useDraggable } from "@dnd-kit/core";
import { GripVertical } from "lucide-react";

import {
  catalogPickDragId,
  catalogPickIcon,
  catalogPickLabel,
  E_APPROVAL_CATALOG_PICKS,
  E_APPROVAL_LAYOUT_ROW_PICKS,
  type EApprovalCatalogPick,
} from "@/components/e-approval/e-approval-field-catalog-shared";
import { cn } from "@/lib/utils";

function DraggableCatalogTile({ pick }: { pick: EApprovalCatalogPick }) {
  const id = catalogPickDragId(pick);
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id });
  const Icon = catalogPickIcon(pick);
  const label = catalogPickLabel(pick);

  return (
    <button
      ref={setNodeRef}
      type="button"
      className={cn(
        "flex w-full items-center gap-2 rounded-lg border border-border bg-card px-2 py-2 text-left transition-colors",
        "touch-none hover:border-primary/40 hover:bg-primary/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
        isDragging && "opacity-50",
      )}
      {...listeners}
      {...attributes}
    >
      <GripVertical className="h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden />
      <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
        <Icon className="h-3.5 w-3.5" />
      </span>
      <span className="min-w-0 flex-1 text-xs font-medium leading-tight text-foreground">{label}</span>
    </button>
  );
}

type Props = {
  className?: string;
  compact?: boolean;
};

export function EApprovalFieldCatalogPalette({ className, compact }: Props) {
  return (
    <aside
      className={cn(
        "flex min-h-0 flex-col rounded-xl border border-border bg-card shadow-sm",
        compact ? "p-2" : "p-3",
        className,
      )}
    >
      <div className="mb-2 shrink-0">
        <h2 className="text-sm font-medium text-foreground">Field catalog</h2>
        <p className="text-[11px] text-muted-foreground">
          Drag fields onto the canvas or row slots. Drag row layouts to add multi-column rows. Use finance shortcuts for
          auto-total forms.
        </p>
      </div>
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pr-0.5">
        <div className="space-y-1.5">
          <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Rows</p>
          <div className="space-y-1">
            {E_APPROVAL_LAYOUT_ROW_PICKS.map((row) => (
              <DraggableCatalogTile
                key={catalogPickDragId({ kind: "layout-row", columns: row.columns })}
                pick={{ kind: "layout-row", columns: row.columns }}
              />
            ))}
          </div>
        </div>
        {E_APPROVAL_CATALOG_PICKS.map((group) => (
          <div key={group.group} className="space-y-1.5">
            <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{group.group}</p>
            <div className="space-y-1">
              {group.picks.map((pick) => (
                <DraggableCatalogTile key={catalogPickDragId(pick)} pick={pick} />
              ))}
            </div>
          </div>
        ))}
      </div>
    </aside>
  );
}
