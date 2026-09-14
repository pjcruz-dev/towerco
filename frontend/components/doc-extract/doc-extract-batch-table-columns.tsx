"use client";

import Link from "next/link";
import { useState } from "react";
import type { ColumnDef } from "@tanstack/react-table";
import { Download, FileSpreadsheet, FileText, RotateCcw } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Spinner } from "@/components/ui/spinner";
import {
  createActionsColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { DocExtractBatchListRow } from "@/modules/doc-extract/types";
import { cn } from "@/lib/utils";

function statusTone(status: string): string {
  if (status === "ready") {
    return "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-400";
  }
  if (status === "failed") {
    return "border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-400";
  }
  if (status === "processing" || status === "pending") {
    return "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-400";
  }
  return "border-border bg-muted text-muted-foreground";
}

function statusLabel(status: string): string {
  if (status === "pending") return "Queued";
  if (status === "processing") return "Scanning";
  if (status === "ready") return "Ready";
  if (status === "failed") return "Failed";
  return status;
}

function formatWhen(value?: string | null): { absolute: string; relative: string } {
  if (!value) return { absolute: "—", relative: "—" };
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return { absolute: "—", relative: "—" };

  const absolute = date.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });

  const deltaMs = Date.now() - date.getTime();
  const minutes = Math.floor(deltaMs / 60_000);
  if (minutes < 1) return { absolute, relative: "Just now" };
  if (minutes < 60) return { absolute, relative: `${minutes}m ago` };
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return { absolute, relative: `${hours}h ago` };
  const days = Math.floor(hours / 24);
  if (days < 7) return { absolute, relative: `${days}d ago` };
  return { absolute, relative: absolute };
}

function BatchProgress({ batch }: { batch: DocExtractBatchListRow }) {
  const total = Math.max(0, batch.document_count);
  const ready = Math.max(0, batch.ready_count);
  const failed = Math.max(0, batch.failed_count);
  const done = Math.min(total, ready + failed);
  const readyPct = total > 0 ? (ready / total) * 100 : 0;
  const failedPct = total > 0 ? (failed / total) * 100 : 0;
  const pending = Math.max(0, total - done);

  return (
    <div className="min-w-[10rem] space-y-1.5">
      <div className="flex items-baseline justify-between gap-2 text-[12px]">
        <span className="tabular-nums text-foreground">
          {ready}/{total} ready
        </span>
        {failed > 0 ? (
          <span className="tabular-nums text-red-600 dark:text-red-400">{failed} failed</span>
        ) : pending > 0 ? (
          <span className="tabular-nums text-muted-foreground">{pending} left</span>
        ) : null}
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted">
        <div className="flex h-full w-full">
          <div className="h-full bg-emerald-500 transition-[width]" style={{ width: `${readyPct}%` }} />
          <div className="h-full bg-red-500 transition-[width]" style={{ width: `${failedPct}%` }} />
        </div>
      </div>
    </div>
  );
}

function ReadyBatchActions({
  batchId,
  canExport,
  downloadPending,
  onDownload,
}: {
  batchId: string;
  canExport: boolean;
  downloadPending: boolean;
  onDownload: (batchId: string, format: "csv" | "xlsx") => void;
}) {
  const [open, setOpen] = useState(false);
  const href = `/doc-extract/batches/${batchId}`;

  return (
    <div className="flex items-center justify-end gap-2">
      <Link href={href} className="text-sm font-medium text-primary hover:underline">
        View results
      </Link>
      {canExport ? (
        <DropdownMenu open={open} onOpenChange={setOpen}>
          <DropdownMenuTrigger
            render={
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-7 gap-1 px-2"
                disabled={downloadPending}
              >
                {downloadPending ? <Spinner className="size-3.5" /> : <Download className="size-3.5" />}
                Download
              </Button>
            }
          />
          <DropdownMenuContent align="end" className="min-w-[9.5rem]">
            <DropdownMenuItem
              disabled={downloadPending}
              onClick={() => {
                onDownload(batchId, "xlsx");
                setOpen(false);
              }}
            >
              <FileSpreadsheet className="size-3.5" aria-hidden />
              Excel (XLSX)
            </DropdownMenuItem>
            <DropdownMenuItem
              disabled={downloadPending}
              onClick={() => {
                onDownload(batchId, "csv");
                setOpen(false);
              }}
            >
              <FileText className="size-3.5" aria-hidden />
              CSV
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ) : null}
    </div>
  );
}

export function buildDocExtractBatchTableColumns(options: {
  canRun: boolean;
  canExport: boolean;
  requeueBatchId: string | null;
  requeuePending: boolean;
  downloadBatchId: string | null;
  onRequeue: (batchId: string) => void;
  onDownload: (batchId: string, format: "csv" | "xlsx") => void;
}): ColumnDef<DocExtractBatchListRow, unknown>[] {
  const {
    canRun,
    canExport,
    requeueBatchId,
    requeuePending,
    downloadBatchId,
    onRequeue,
    onDownload,
  } = options;

  return [
    createTextColumn("created_at", "Created", (row) => {
      const when = formatWhen(row.created_at);
      return (
        <Link href={`/doc-extract/batches/${row.id}`} className="block min-w-[7rem]">
          <div className="font-medium text-foreground">{when.relative}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">{when.absolute}</div>
        </Link>
      );
    }, { enableSorting: true, sortValue: (row) => row.created_at ?? "" }),
    createTextColumn(
      "primary_filename",
      "File name",
      (row) => {
        const name = row.primary_filename?.trim();
        if (!name) return <span className="text-muted-foreground">—</span>;
        const multi = (row.file_count ?? 1) > 1;
        return (
          <span className="block max-w-[16rem] truncate font-medium text-foreground" title={multi ? `${name} +++` : name}>
            {name}
            {multi ? <span className="ml-1 font-normal text-muted-foreground">+++</span> : null}
          </span>
        );
      },
      {
        enableSorting: true,
        sortValue: (row) => row.primary_filename ?? "",
      },
    ),
    createTextColumn(
      "template_name",
      "Mode",
      (row) => (
        <div>
          <div className="font-medium text-foreground">{row.template_name ?? "Auto-detect"}</div>
          <div className="mt-0.5 text-[11px] capitalize text-muted-foreground">
            {(row.mode ?? (row.template_id ? "template" : "auto")).replace("_", " ")}
          </div>
        </div>
      ),
      {
        enableSorting: true,
        sortValue: (row) => row.template_name ?? row.mode ?? "",
      },
    ),
    createTextColumn(
      "status",
      "Status",
      (row) => {
        const isLive = row.status === "processing" || row.status === "pending";
        return (
          <span
            className={cn(
              "inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium",
              statusTone(row.status),
            )}
          >
            {isLive ? <Spinner className="size-3" /> : null}
            {statusLabel(row.status)}
          </span>
        );
      },
      { enableSorting: true, sortValue: (row) => row.status },
    ),
    createTextColumn("progress", "Progress", (row) => <BatchProgress batch={row} />, {
      enableSorting: false,
    }),
    createTextColumn(
      "message",
      "Notes",
      (row) => (
        <span className="line-clamp-2 max-w-[16rem] text-muted-foreground" title={row.message ?? undefined}>
          {row.message?.trim() || "—"}
        </span>
      ),
      { enableSorting: false },
    ),
    createActionsColumn("Actions", (row) => {
      const batch = row.original;
      const showRetry =
        canRun &&
        (batch.status === "processing" || batch.status === "pending") &&
        batch.ready_count + batch.failed_count < batch.document_count;

      if (batch.status === "ready") {
        return (
          <ReadyBatchActions
            batchId={batch.id}
            canExport={canExport}
            downloadPending={downloadBatchId === batch.id}
            onDownload={onDownload}
          />
        );
      }

      if (!showRetry) {
        return <span className="text-xs text-muted-foreground">—</span>;
      }

      return (
        <div className="flex items-center justify-end gap-1">
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={requeuePending}
            onClick={() => onRequeue(batch.id)}
            title="Re-queue pending/scanning documents if the worker lost jobs"
          >
            {requeueBatchId === batch.id ? (
              <Spinner className="size-3.5" />
            ) : (
              <RotateCcw className="size-3.5" />
            )}
            Retry
          </Button>
        </div>
      );
    }),
  ];
}

const DOC_EXTRACT_PRINT_COLUMNS: Array<{
  id: string;
  label: string;
  value: (batch: DocExtractBatchListRow) => string;
}> = [
  {
    id: "created_at",
    label: "Created",
    value: (batch) => formatWhen(batch.created_at).absolute,
  },
  {
    id: "primary_filename",
    label: "File name",
    value: (batch) => {
      const name = batch.primary_filename?.trim() || "—";
      return (batch.file_count ?? 1) > 1 ? `${name} +++` : name;
    },
  },
  {
    id: "template_name",
    label: "Template",
    value: (batch) => batch.template_name ?? "Auto-detect",
  },
  {
    id: "template_name",
    label: "Mode",
    value: (batch) =>
      (batch.mode ?? (batch.template_id ? "template" : "auto")).replace("_", " "),
  },
  {
    id: "status",
    label: "Status",
    value: (batch) => statusLabel(batch.status),
  },
  {
    id: "progress",
    label: "Ready",
    value: (batch) => String(batch.ready_count),
  },
  {
    id: "progress",
    label: "Failed",
    value: (batch) => String(batch.failed_count),
  },
  {
    id: "progress",
    label: "Documents",
    value: (batch) => String(batch.document_count),
  },
  {
    id: "message",
    label: "Notes",
    value: (batch) => batch.message?.trim() || "—",
  },
];

export function docExtractBatchPrintRows(
  batches: DocExtractBatchListRow[],
  visibleColumnIds?: string[],
): {
  columns: string[];
  rows: string[][];
} {
  const visible = visibleColumnIds ? new Set(visibleColumnIds) : null;
  const cols = DOC_EXTRACT_PRINT_COLUMNS.filter((col) => !visible || visible.has(col.id));
  const effective = cols.length > 0 ? cols : DOC_EXTRACT_PRINT_COLUMNS;

  return {
    columns: effective.map((col) => col.label),
    rows: batches.map((batch) => effective.map((col) => col.value(batch))),
  };
}
