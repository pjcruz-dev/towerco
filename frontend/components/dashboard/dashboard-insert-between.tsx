"use client";

import { Plus } from "lucide-react";
import { useState } from "react";

import { DashboardAddWidgetPicker } from "@/components/dashboard/dashboard-add-widget-picker";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import type { DashboardCatalogEntry } from "@/lib/ui/dashboard-widget-catalog";
import { cn } from "@/lib/utils";

type Props = {
  entries: DashboardCatalogEntry[];
  onSelect: (entry: DashboardCatalogEntry) => void;
  label?: string;
  className?: string;
};

/** Insert rail shown above/below the widget grid (never between cards — that breaks packing). */
export function DashboardInsertBetween({ entries, onSelect, label = "Add widget", className }: Props) {
  const [open, setOpen] = useState(false);

  if (entries.length === 0) {
    return null;
  }

  return (
    <div className={cn("flex w-full items-center py-0.5", className)}>
      <div className="h-px flex-1 bg-border/70" />
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger
          render={
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="mx-2 h-7 gap-1 rounded-full px-2.5 text-xs shadow-sm"
              aria-label={label}
            >
              <Plus className="size-3.5" aria-hidden />
              {label}
            </Button>
          }
        />
        <PopoverContent className="w-[22rem] p-2" align="center">
          <p className="mb-1 px-1 text-xs font-medium text-muted-foreground">Insert widget here</p>
          <DashboardAddWidgetPicker
            entries={entries}
            onSelect={(entry) => {
              onSelect(entry);
              setOpen(false);
            }}
          />
        </PopoverContent>
      </Popover>
      <div className="h-px flex-1 bg-border/70" />
    </div>
  );
}
