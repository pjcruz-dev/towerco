"use client";

import { Download, FileSpreadsheet, FileText, Printer } from "lucide-react";
import { useState, type ReactNode } from "react";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Spinner } from "@/components/ui/spinner";
import { MODULE_LIST_PRINT_ALL_CAP } from "@/lib/ui/module-list-print-all";
import { cn } from "@/lib/utils";

export type ModuleListExportFormat = "csv" | "xlsx";

type ModuleListToolbarProps = {
  className?: string;
  /** Extra controls (e.g. density) rendered before Columns/Export/Print. */
  start?: ReactNode;
  /** Column visibility control from RegistryDataTableView / DataTableViewOptions. */
  columnsControl?: ReactNode;
  onExport?: (format: ModuleListExportFormat) => void | Promise<void>;
  exportDisabled?: boolean;
  showExport?: boolean;
  /** When > 0, Export label becomes "Export selected (N)". */
  selectedCount?: number;
  /** Clear cross-page selection (shown when selectedCount > 0). */
  onClearSelection?: () => void;
  /** Optional export submenu items (e.g. Submissions vs Line items). */
  exportMenuExtra?: ReactNode;
  onPrint?: () => void | Promise<void>;
  /** When set, Print becomes a menu: this page vs all filtered (capped). */
  onPrintAll?: () => void | Promise<void>;
  printDisabled?: boolean;
  showPrint?: boolean;
  end?: ReactNode;
};

/**
 * Shared list actions for Ticketing / E-Forms / DocExtract tables:
 * Columns (slot) · Export csv|xlsx · Print.
 */
export function ModuleListToolbar({
  className,
  start,
  columnsControl,
  onExport,
  exportDisabled = false,
  showExport = true,
  selectedCount = 0,
  onClearSelection,
  exportMenuExtra,
  onPrint,
  onPrintAll,
  printDisabled = false,
  showPrint = true,
  end,
}: ModuleListToolbarProps) {
  const [exporting, setExporting] = useState<ModuleListExportFormat | null>(null);
  const [printing, setPrinting] = useState<"page" | "all" | null>(null);

  const runExport = async (format: ModuleListExportFormat) => {
    if (!onExport || exporting) return;
    setExporting(format);
    try {
      await onExport(format);
    } finally {
      setExporting(null);
    }
  };

  const runPrint = async (scope: "page" | "all") => {
    const handler = scope === "all" ? onPrintAll : onPrint;
    if (!handler || printing) return;
    setPrinting(scope);
    try {
      await handler();
    } finally {
      setPrinting(null);
    }
  };

  return (
    <div className={cn("flex flex-wrap items-center gap-2", className)}>
      {start}
      {columnsControl}
      {selectedCount > 0 ? (
        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <span className="tabular-nums">{selectedCount} selected</span>
          {onClearSelection ? (
            <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={onClearSelection}>
              Clear
            </Button>
          ) : null}
        </div>
      ) : null}
      {showExport && onExport ? (
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="gap-1.5"
                disabled={exportDisabled || exporting !== null}
                data-help="dx-list-export"
              >
                {exporting ? <Spinner className="size-3.5" /> : <Download className="size-3.5" aria-hidden />}
                {selectedCount > 0 ? `Export selected (${selectedCount})` : "Export"}
              </Button>
            }
          />
          <DropdownMenuContent align="end" className="min-w-[10rem]" data-help="module-list-export-menu">
            {exportMenuExtra}
            <DropdownMenuItem
              disabled={exportDisabled || exporting !== null}
              onClick={() => void runExport("csv")}
            >
              <FileText className="size-3.5" aria-hidden />
              CSV
            </DropdownMenuItem>
            <DropdownMenuItem
              disabled={exportDisabled || exporting !== null}
              onClick={() => void runExport("xlsx")}
            >
              <FileSpreadsheet className="size-3.5" aria-hidden />
              Excel (XLSX)
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ) : null}
      {showPrint && onPrint ? (
        onPrintAll ? (
          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-1.5"
                  disabled={printDisabled || printing !== null}
                >
                  {printing ? <Spinner className="size-3.5" /> : <Printer className="size-3.5" aria-hidden />}
                  Print
                </Button>
              }
            />
            <DropdownMenuContent align="end" className="min-w-[12rem]">
              <DropdownMenuItem
                disabled={printDisabled || printing !== null}
                onClick={() => void runPrint("page")}
              >
                This page
              </DropdownMenuItem>
              <DropdownMenuItem
                disabled={printDisabled || printing !== null}
                onClick={() => void runPrint("all")}
              >
                All filtered (≤{MODULE_LIST_PRINT_ALL_CAP.toLocaleString()}; larger sets queue printable HTML)
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        ) : (
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="gap-1.5"
            disabled={printDisabled || printing !== null}
            onClick={() => void runPrint("page")}
          >
            {printing ? <Spinner className="size-3.5" /> : <Printer className="size-3.5" aria-hidden />}
            Print
          </Button>
        )
      ) : null}
      {end}
    </div>
  );
}
