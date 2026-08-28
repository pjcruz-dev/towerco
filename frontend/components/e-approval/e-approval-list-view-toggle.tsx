"use client";

import { LayoutGrid, List } from "lucide-react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export type EApprovalListViewMode = "table" | "gallery";

type Props = {
  value: EApprovalListViewMode;
  onChange: (mode: EApprovalListViewMode) => void;
  className?: string;
  ariaLabel?: string;
  dataHelp?: string;
};

export function EApprovalListViewToggle({
  value,
  onChange,
  className,
  ariaLabel = "List view",
  dataHelp,
}: Props) {
  return (
    <div
      data-help={dataHelp}
      className={cn("inline-flex shrink-0 rounded-lg border border-border bg-muted/40 p-0.5", className)}
      role="group"
      aria-label={ariaLabel}
    >
      <Button
        type="button"
        size="sm"
        variant={value === "table" ? "default" : "ghost"}
        className="h-8 gap-1.5 px-2.5"
        onClick={() => onChange("table")}
        aria-pressed={value === "table"}
      >
        <List className="h-3.5 w-3.5" />
        Table
      </Button>
      <Button
        type="button"
        size="sm"
        variant={value === "gallery" ? "default" : "ghost"}
        className="h-8 gap-1.5 px-2.5"
        onClick={() => onChange("gallery")}
        aria-pressed={value === "gallery"}
      >
        <LayoutGrid className="h-3.5 w-3.5" />
        Gallery
      </Button>
    </div>
  );
}
