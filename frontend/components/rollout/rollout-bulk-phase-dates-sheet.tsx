"use client";

import { useState } from "react";

import { RolloutPhaseDatesGridPanel } from "@/components/rollout/rollout-phase-dates-grid-panel";
import { RolloutPhaseDatesSameForAllPanel } from "@/components/rollout/rollout-phase-dates-same-for-all-panel";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import type { RolloutListRow } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedIds: string[];
  selectedRows: RolloutListRow[];
  onSuccess: () => void;
};

const tabs = [
  { key: "grid", label: "Grid per rollout" },
  { key: "same", label: "Same for all" },
] as const;

type TabKey = (typeof tabs)[number]["key"];

export function RolloutBulkPhaseDatesSheet({ open, onOpenChange, selectedIds, selectedRows, onSuccess }: Props) {
  const [tab, setTab] = useState<TabKey>("grid");

  function handleSuccess() {
    onSuccess();
    onOpenChange(false);
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="!w-[96vw] !max-w-[min(96vw,1680px)] flex h-full flex-col p-0">
        <SheetHeader className="border-b border-border px-6 py-4">
          <SheetTitle>Mass update timeline actual dates</SheetTitle>
          <SheetDescription>
            Administrative backfill for {selectedIds.length} rollout{selectedIds.length === 1 ? "" : "s"}. Use the{" "}
            <strong>grid</strong> when each rollout needs different dates (e.g. only Endorsement on EZVL and OLMD).
          </SheetDescription>
          <div className="mt-3 flex flex-wrap gap-1">
            {tabs.map((item) => (
              <button
                key={item.key}
                type="button"
                onClick={() => setTab(item.key)}
                className={cn(
                  "rounded-md px-3 py-1.5 text-xs font-medium",
                  tab === item.key
                    ? "bg-primary text-primary-foreground"
                    : "bg-muted text-muted-foreground hover:text-foreground",
                )}
              >
                {item.label}
              </button>
            ))}
          </div>
        </SheetHeader>

        {tab === "grid" ? (
          <RolloutPhaseDatesGridPanel
            selectedRows={selectedRows}
            onSuccess={handleSuccess}
            onCancel={() => onOpenChange(false)}
          />
        ) : (
          <RolloutPhaseDatesSameForAllPanel
            selectedIds={selectedIds}
            selectedRows={selectedRows}
            onSuccess={handleSuccess}
            onCancel={() => onOpenChange(false)}
          />
        )}
      </SheetContent>
    </Sheet>
  );
}
