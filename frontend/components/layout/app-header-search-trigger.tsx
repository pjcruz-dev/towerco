"use client";

import { Search } from "lucide-react";

import { useGlobalCommandPalette } from "@/hooks/use-global-command-palette";
import { cn } from "@/lib/utils";

function shortcutLabel(): string {
  if (typeof navigator === "undefined") {
    return "Ctrl+K";
  }

  return /mac/i.test(navigator.platform) || /mac/i.test(navigator.userAgent) ? "⌘K" : "Ctrl+K";
}

export function AppHeaderSearchTrigger({ className }: { className?: string }) {
  const { setOpen } = useGlobalCommandPalette();

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className={cn(
          "inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground md:hidden",
          className,
        )}
        aria-label="Search TowerOS"
      >
        <Search className="h-4 w-4" />
      </button>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className={cn(
          "hidden h-9 min-w-[220px] items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 text-left text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground md:flex lg:min-w-[280px]",
          className,
        )}
        aria-label="Search TowerOS"
      >
        <Search className="h-4 w-4 shrink-0" />
        <span className="flex-1 truncate">Search TowerOS…</span>
        <kbd className="hidden rounded border border-border bg-background px-1.5 py-0.5 text-[10px] font-medium lg:inline">
          {shortcutLabel()}
        </kbd>
      </button>
    </>
  );
}
