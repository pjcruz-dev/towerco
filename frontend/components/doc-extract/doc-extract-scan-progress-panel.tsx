"use client";

import { useMemo, useState } from "react";
import { AlertCircle, CheckCircle2, Clock3, Loader2, Search } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import type { DocExtractDocument } from "@/modules/doc-extract/types";
import { cn } from "@/lib/utils";

type ScanFilter = "all" | "queued" | "scanning" | "ready" | "failed";

type DocExtractScanProgressPanelProps = {
  documents: DocExtractDocument[];
  batchStatus?: string | null;
  live?: boolean;
  canRequeue?: boolean;
  requeuePending?: boolean;
  onRequeue?: () => void;
};

function statusLabel(status: string): string {
  if (status === "pending") return "Queued";
  if (status === "processing") return "Scanning";
  if (status === "ready") return "Ready";
  if (status === "failed") return "Failed";
  return status;
}

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

function StatusIcon({ status }: { status: string }) {
  if (status === "ready") {
    return <CheckCircle2 className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />;
  }
  if (status === "failed") {
    return <AlertCircle className="size-3.5 shrink-0 text-red-600 dark:text-red-400" />;
  }
  if (status === "processing") {
    return <Loader2 className="size-3.5 shrink-0 animate-spin text-sky-600 dark:text-sky-400" />;
  }
  return <Clock3 className="size-3.5 shrink-0 text-muted-foreground" />;
}

function matchesFilter(status: string, filter: ScanFilter): boolean {
  if (filter === "all") return true;
  if (filter === "queued") return status === "pending";
  if (filter === "scanning") return status === "processing";
  if (filter === "ready") return status === "ready";
  if (filter === "failed") return status === "failed";
  return true;
}

function pageLabel(document: DocExtractDocument): string | null {
  if (document.source_pages?.length) {
    const pages = document.source_pages;
    return `page${pages.length === 1 ? "" : "s"} ${pages.join(", ")}`;
  }
  if (document.source_page) {
    return `page ${document.source_page}`;
  }
  if (document.page_count) {
    return `${document.page_count} page${document.page_count === 1 ? "" : "s"}`;
  }
  return null;
}

export function DocExtractScanProgressPanel({
  documents,
  batchStatus,
  live = false,
  canRequeue = false,
  requeuePending = false,
  onRequeue,
}: DocExtractScanProgressPanelProps) {
  const [filter, setFilter] = useState<ScanFilter>("all");
  const [query, setQuery] = useState("");

  const counts = useMemo(() => {
    let ready = 0;
    let failed = 0;
    let scanning = 0;
    let queued = 0;
    for (const document of documents) {
      if (document.status === "ready") ready += 1;
      else if (document.status === "failed") failed += 1;
      else if (document.status === "processing") scanning += 1;
      else queued += 1;
    }
    const total = documents.length;
    const done = ready + failed;
    const percent = total > 0 ? Math.round((done / total) * 100) : 0;
    return { ready, failed, scanning, queued, total, done, percent };
  }, [documents]);

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return documents.filter((document) => {
      if (!matchesFilter(document.status, filter)) return false;
      if (!needle) return true;
      return (
        document.original_filename.toLowerCase().includes(needle) ||
        (document.error_message ?? "").toLowerCase().includes(needle)
      );
    });
  }, [documents, filter, query]);

  const filters: Array<{ id: ScanFilter; label: string; count: number }> = [
    { id: "all", label: "All", count: counts.total },
    { id: "scanning", label: "Scanning", count: counts.scanning },
    { id: "queued", label: "Queued", count: counts.queued },
    { id: "ready", label: "Ready", count: counts.ready },
    { id: "failed", label: "Failed", count: counts.failed },
  ];

  const readyPct = counts.total > 0 ? (counts.ready / counts.total) * 100 : 0;
  const failedPct = counts.total > 0 ? (counts.failed / counts.total) * 100 : 0;
  const isBatchActive = batchStatus === "processing" || batchStatus === "pending" || live;

  return (
    <section
      id="dx-scan-progress"
      data-help="dx-scan-progress"
      className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-xl font-semibold text-foreground">Scan progress</h2>
            {isBatchActive ? (
              <span className="inline-flex items-center gap-1 rounded-md border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-400">
                <Spinner className="size-3" /> Live
              </span>
            ) : null}
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Trace each file as OCR finishes. Use Rescan values to remap ready rows after extractor fixes.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-xs tabular-nums text-muted-foreground">
            {counts.done}/{counts.total} done · {counts.percent}%
          </span>
          {canRequeue && onRequeue ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={requeuePending || counts.total === 0}
              onClick={onRequeue}
            >
              {requeuePending
                ? "Rescanning…"
                : counts.failed > 0
                  ? `Rescan values (${counts.failed} failed)`
                  : "Rescan values"}
            </Button>
          ) : null}
        </div>
      </div>

      <div className="space-y-2">
        <div className="flex items-baseline justify-between gap-2 text-xs">
          <span className="tabular-nums text-foreground">
            {counts.ready} ready
            {counts.scanning > 0 ? ` · ${counts.scanning} scanning` : ""}
            {counts.queued > 0 ? ` · ${counts.queued} queued` : ""}
          </span>
          {counts.failed > 0 ? (
            <span className="tabular-nums text-red-600 dark:text-red-400">{counts.failed} failed</span>
          ) : null}
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-muted">
          <div className="flex h-full w-full">
            <div
              className="h-full bg-emerald-500 transition-[width] duration-500"
              style={{ width: `${readyPct}%` }}
            />
            <div
              className="h-full bg-red-500 transition-[width] duration-500"
              style={{ width: `${failedPct}%` }}
            />
          </div>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {filters.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setFilter(item.id)}
            className={cn(
              "rounded-md border px-2.5 py-1 text-xs transition-colors",
              filter === item.id
                ? "border-primary/30 bg-primary/5 font-medium text-foreground"
                : "border-border bg-muted/40 text-muted-foreground hover:bg-muted",
            )}
          >
            {item.label}
            <span className="ml-1 tabular-nums opacity-70">{item.count}</span>
          </button>
        ))}
        <div className="relative ml-auto min-w-[12rem] flex-1 sm:max-w-xs">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Filter by filename…"
            className="h-8 pl-8 text-xs"
          />
        </div>
      </div>

      <ul className="max-h-[22rem] divide-y divide-border overflow-y-auto rounded-lg border border-border">
        {filtered.length === 0 ? (
          <li className="px-3 py-8 text-center text-sm text-muted-foreground">
            No files match this filter.
          </li>
        ) : (
          filtered.map((document, index) => {
            const pages = pageLabel(document);
            return (
              <li
                key={document.id}
                className="flex items-start gap-3 px-3 py-2.5 text-sm"
              >
                <span className="mt-0.5 w-6 shrink-0 text-right text-xs tabular-nums text-muted-foreground">
                  {index + 1}
                </span>
                <StatusIcon status={document.status} />
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="truncate font-medium text-foreground">{document.original_filename}</p>
                    <span
                      className={cn(
                        "rounded-md border px-1.5 py-0.5 text-[11px] font-medium capitalize",
                        statusTone(document.status),
                      )}
                    >
                      {statusLabel(document.status)}
                    </span>
                  </div>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {[pages, document.scan_engine].filter(Boolean).join(" · ") || "Waiting for scan…"}
                  </p>
                  {document.error_message ? (
                    <p className="mt-1 text-xs text-destructive">{document.error_message}</p>
                  ) : null}
                </div>
              </li>
            );
          })
        )}
      </ul>
    </section>
  );
}
