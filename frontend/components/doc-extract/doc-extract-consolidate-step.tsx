"use client";

import { useMemo, useState } from "react";
import { Combine, Layers2, Scissors, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import type {
  DocExtractConsolidateRecord,
  DocExtractPreviewFile,
  DocExtractPreviewPage,
} from "@/modules/doc-extract/types";

const MAX_RECORDS = 25;

function newId(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID();
  }
  return `rec-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function formatPageList(pages: number[]): string {
  if (pages.length === 0) return "";
  const sorted = [...pages].sort((a, b) => a - b);
  const parts: string[] = [];
  let start = sorted[0];
  let prev = sorted[0];
  for (let i = 1; i < sorted.length; i++) {
    const current = sorted[i];
    if (current === prev + 1) {
      prev = current;
      continue;
    }
    parts.push(start === prev ? `${start}` : `${start}–${prev}`);
    start = current;
    prev = current;
  }
  parts.push(start === prev ? `${start}` : `${start}–${prev}`);
  return parts.join(", ");
}

function defaultLabel(filename: string, pages: number[]): string {
  if (pages.length === 1) return `${filename} (page ${pages[0]})`;
  if (pages.length > 1) return `${filename} (pages ${formatPageList(pages)})`;
  return filename;
}

export function buildDefaultConsolidateRecords(
  previews: DocExtractPreviewFile[],
): DocExtractConsolidateRecord[] {
  const records: DocExtractConsolidateRecord[] = [];
  for (const file of previews) {
    // Accuracy default: multi-page PDF → one record per page; single page/image → one record.
    if (file.page_count > 1) {
      for (const page of file.pages) {
        records.push({
          id: newId(),
          fileIndex: file.index,
          pages: [page.page],
          label: defaultLabel(file.filename, [page.page]),
        });
      }
    } else {
      records.push({
        id: newId(),
        fileIndex: file.index,
        pages: [1],
        label: file.filename,
      });
    }
  }
  return records;
}

function pageMeta(
  previews: DocExtractPreviewFile[],
  fileIndex: number,
  page: number,
): DocExtractPreviewPage | null {
  const file = previews.find((item) => item.index === fileIndex);
  return file?.pages.find((item) => item.page === page) ?? null;
}

type Props = {
  previews: DocExtractPreviewFile[];
  records: DocExtractConsolidateRecord[];
  onChange: (records: DocExtractConsolidateRecord[]) => void;
  disabled?: boolean;
};

export function DocExtractConsolidateStep({ previews, records, onChange, disabled }: Props) {
  const [selected, setSelected] = useState<Record<string, boolean>>({});

  const fileByIndex = useMemo(() => {
    const map = new Map<number, DocExtractPreviewFile>();
    for (const file of previews) map.set(file.index, file);
    return map;
  }, [previews]);

  const assignedKeys = useMemo(() => {
    const set = new Set<string>();
    for (const record of records) {
      for (const page of record.pages) {
        set.add(`${record.fileIndex}:${page}`);
      }
    }
    return set;
  }, [records]);

  const discarded = useMemo(() => {
    const items: { fileIndex: number; page: number; filename: string; meta: DocExtractPreviewPage | null }[] =
      [];
    for (const file of previews) {
      for (const page of file.pages) {
        const key = `${file.index}:${page.page}`;
        if (!assignedKeys.has(key)) {
          items.push({
            fileIndex: file.index,
            page: page.page,
            filename: file.filename,
            meta: page,
          });
        }
      }
    }
    return items;
  }, [previews, assignedKeys]);

  const blankWarnings = useMemo(
    () =>
      records.filter((record) =>
        record.pages.some((page) => pageMeta(previews, record.fileIndex, page)?.likely_blank),
      ).length,
    [records, previews],
  );

  const selectedEntries = useMemo(() => {
    return Object.entries(selected)
      .filter(([, on]) => on)
      .map(([key]) => {
        const [fileIndex, page] = key.split(":").map(Number);
        return { key, fileIndex, page };
      });
  }, [selected]);

  const canGroup =
    selectedEntries.length >= 2 &&
    selectedEntries.every((item) => item.fileIndex === selectedEntries[0]?.fileIndex);

  const togglePage = (fileIndex: number, page: number) => {
    const key = `${fileIndex}:${page}`;
    setSelected((current) => ({ ...current, [key]: !current[key] }));
  };

  const updateRecord = (id: string, patch: Partial<DocExtractConsolidateRecord>) => {
    onChange(records.map((record) => (record.id === id ? { ...record, ...patch } : record)));
  };

  const removeRecord = (id: string) => {
    onChange(records.filter((record) => record.id !== id));
    setSelected({});
  };

  const splitRecord = (id: string) => {
    const target = records.find((record) => record.id === id);
    if (!target || target.pages.length < 2) return;
    const file = fileByIndex.get(target.fileIndex);
    const filename = file?.filename ?? "document";
    const next = records.flatMap((record) => {
      if (record.id !== id) return [record];
      return record.pages.map((page) => ({
        id: newId(),
        fileIndex: record.fileIndex,
        pages: [page],
        label: defaultLabel(filename, [page]),
      }));
    });
    onChange(next);
    setSelected({});
  };

  const groupSelected = () => {
    if (!canGroup) return;
    const fileIndex = selectedEntries[0].fileIndex;
    const pages = selectedEntries.map((item) => item.page).sort((a, b) => a - b);
    const file = fileByIndex.get(fileIndex);
    const filename = file?.filename ?? "document";

    // Remove selected pages from existing records, drop empty records, then add grouped record.
    const next: DocExtractConsolidateRecord[] = [];
    for (const record of records) {
      if (record.fileIndex !== fileIndex) {
        next.push(record);
        continue;
      }
      const remaining = record.pages.filter((page) => !pages.includes(page));
      if (remaining.length === 0) continue;
      next.push({
        ...record,
        pages: remaining,
        label:
          remaining.length === record.pages.length
            ? record.label
            : defaultLabel(filename, remaining),
      });
    }
    next.push({
      id: newId(),
      fileIndex,
      pages,
      label: defaultLabel(filename, pages),
    });
    onChange(next);
    setSelected({});
  };

  const restoreDiscarded = (fileIndex: number, page: number) => {
    if (records.length >= MAX_RECORDS) return;
    const file = fileByIndex.get(fileIndex);
    onChange([
      ...records,
      {
        id: newId(),
        fileIndex,
        pages: [page],
        label: defaultLabel(file?.filename ?? "document", [page]),
      },
    ]);
  };

  const splitAllPages = () => {
    onChange(buildDefaultConsolidateRecords(previews));
    setSelected({});
  };

  const keepFilesWhole = () => {
    onChange(
      previews.map((file) => ({
        id: newId(),
        fileIndex: file.index,
        pages: file.pages.map((page) => page.page),
        label: file.filename,
      })),
    );
    setSelected({});
  };

  return (
    <div className="space-y-4" data-help="dx-consolidate-step">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted-foreground">
            Arrange pages into records before extraction. Each record becomes one results row. A page can belong to
            only one record.
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            {records.length} record{records.length === 1 ? "" : "s"}
            {discarded.length > 0 ? ` · ${discarded.length} discarded` : ""}
            {blankWarnings > 0 ? ` · ${blankWarnings} with sparse/blank page warning` : ""}
            {records.length > MAX_RECORDS ? ` · over limit of ${MAX_RECORDS}` : ""}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={splitAllPages}>
            <Layers2 className="size-4" />
            One page = one record
          </Button>
          <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={keepFilesWhole}>
            <Combine className="size-4" />
            Keep each file whole
          </Button>
          <Button type="button" size="sm" disabled={disabled || !canGroup} onClick={groupSelected}>
            Group selected pages
          </Button>
        </div>
      </div>

      {records.length > MAX_RECORDS ? (
        <p className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
          Too many records ({records.length}). Max is {MAX_RECORDS}. Group pages or discard extras before extracting.
        </p>
      ) : null}

      <div className="space-y-3">
        {records.map((record, index) => {
          const file = fileByIndex.get(record.fileIndex);
          return (
            <div key={record.id} className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-medium text-muted-foreground">Record {index + 1}</span>
                <Input
                  className="h-9 max-w-md flex-1"
                  value={record.label}
                  disabled={disabled}
                  onChange={(event) => updateRecord(record.id, { label: event.target.value })}
                  aria-label={`Label for record ${index + 1}`}
                />
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={disabled || record.pages.length < 2}
                  onClick={() => splitRecord(record.id)}
                >
                  <Scissors className="size-4" />
                  Split pages
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  disabled={disabled}
                  aria-label={`Discard record ${index + 1}`}
                  onClick={() => removeRecord(record.id)}
                >
                  <Trash2 className="size-4" />
                </Button>
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                {file?.filename ?? "File"} · pages {formatPageList(record.pages)}
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                {record.pages.map((page) => {
                  const meta = pageMeta(previews, record.fileIndex, page);
                  const key = `${record.fileIndex}:${page}`;
                  const isSelected = Boolean(selected[key]);
                  return (
                    <label
                      key={key}
                      className={cn(
                        "relative w-[7.5rem] cursor-pointer overflow-hidden rounded-lg border bg-muted/20",
                        isSelected ? "border-primary ring-1 ring-primary/30" : "border-border",
                        meta?.likely_blank ? "border-amber-300" : null,
                      )}
                    >
                      <span className="absolute left-1.5 top-1.5 z-10">
                        <Checkbox
                          checked={isSelected}
                          disabled={disabled}
                          onCheckedChange={() => togglePage(record.fileIndex, page)}
                        />
                      </span>
                      {meta?.thumbnail ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={meta.thumbnail} alt={`Page ${page}`} className="h-28 w-full object-cover object-top" />
                      ) : (
                        <div className="flex h-28 items-center justify-center text-xs text-muted-foreground">
                          Page {page}
                        </div>
                      )}
                      <span className="block border-t border-border px-2 py-1 text-[11px] text-muted-foreground">
                        p.{page}
                        {meta?.likely_blank ? " · sparse" : ""}
                      </span>
                    </label>
                  );
                })}
              </div>
            </div>
          );
        })}
      </div>

      {discarded.length > 0 ? (
        <div className="rounded-xl border border-dashed border-border bg-muted/20 p-4">
          <h3 className="text-sm font-medium text-foreground">Discarded pages</h3>
          <p className="mt-1 text-xs text-muted-foreground">
            These pages will not be extracted. Restore one to create a new record.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            {discarded.map((item) => (
              <button
                key={`${item.fileIndex}:${item.page}`}
                type="button"
                disabled={disabled || records.length >= MAX_RECORDS}
                onClick={() => restoreDiscarded(item.fileIndex, item.page)}
                className="w-[7.5rem] overflow-hidden rounded-lg border border-border bg-card text-left hover:border-primary/40"
              >
                {item.meta?.thumbnail ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={item.meta.thumbnail}
                    alt={`${item.filename} page ${item.page}`}
                    className="h-28 w-full object-cover object-top opacity-80"
                  />
                ) : (
                  <div className="flex h-28 items-center justify-center text-xs text-muted-foreground">
                    Page {item.page}
                  </div>
                )}
                <span className="block truncate border-t border-border px-2 py-1 text-[11px] text-muted-foreground">
                  Restore p.{item.page}
                </span>
              </button>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}
