"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { Download, Info } from "lucide-react";

import { JobProgressCard } from "@/components/dynamic-entities/job-progress-card";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { getErrorMessage, isCanceledRequestError } from "@/lib/api/error";
import {
  downloadDynExportWorkbook,
  fetchDynEntity,
  fetchDynRecords,
  type DynRecordListRow,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  assembleExportRows,
  exportColumnsFromFields,
  relationsForExport,
  rowsToCsv,
  type DynExportColumn,
  type DynExportRelation,
} from "@/lib/dynamic-entities/dyn-record-export";
import { notify } from "@/lib/ui/notify";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  format: "csv" | "xlsx";
  entitySlug: string;
  entityName: string;
  relatedTabs?: Array<Record<string, unknown>>;
  columns: DynExportColumn[];
  search?: string;
  sort?: string;
  ids?: string[] | null;
};

export function DynRecordExportDialog({
  open,
  onOpenChange,
  format,
  entitySlug,
  entityName,
  relatedTabs,
  columns,
  search,
  sort,
  ids,
}: Props) {
  const relations = useMemo(() => relationsForExport(relatedTabs), [relatedTabs]);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [progress, setProgress] = useState<{
    done: number;
    total: number;
    label: string;
  } | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    if (!open) return;
    setSelected(new Set());
  }, [open, entitySlug]);

  function toggle(slug: string, checked: boolean) {
    setSelected((current) => {
      const next = new Set(current);
      if (checked) next.add(slug);
      else next.delete(slug);
      return next;
    });
  }

  function cancelExport() {
    abortRef.current?.abort();
    abortRef.current = null;
    setProgress(null);
  }

  async function startExport() {
    const controller = new AbortController();
    abortRef.current = controller;
    onOpenChange(false);
    const chosen = relations.filter((relation) => selected.has(relation.entity_slug));
    setProgress({ done: 0, total: 0, label: "Preparing export…" });
    try {
      const parents = await loadParents({
        entitySlug,
        search,
        sort,
        ids,
        signal: controller.signal,
        onProgress: (done, total) => {
          setProgress({
            done,
            total,
            label: `Processing ${done.toLocaleString()} of ${total.toLocaleString()} records...`,
          });
        },
      });
      const childrenByRelation: Record<string, DynRecordListRow[]> = {};
      const childColumns: Record<string, DynExportColumn[]> = {};
      for (const relation of chosen) {
        if (controller.signal.aborted) return;
        setProgress((current) => ({
          done: current?.done ?? parents.length,
          total: current?.total ?? parents.length,
          label: `Loading ${relation.label}...`,
        }));
        const detail = await fetchDynEntity(relation.entity_slug, controller.signal);
        childColumns[relation.entity_slug] = exportColumnsFromFields(detail.fields);
        childrenByRelation[relation.entity_slug] = await loadAllRecords(
          relation.entity_slug,
          controller.signal,
        );
      }
      const table = assembleExportRows({
        parents,
        parentColumns: columns.length > 0 ? columns : [{ id: "title", label: "Title" }],
        relations: chosen,
        childrenByRelation,
        childColumns,
      });
      const stamp = new Date().toISOString().slice(0, 10);
      const base = `${entitySlug}_export_${stamp}`;
      if (format === "xlsx") {
        const blob = await downloadDynExportWorkbook(entitySlug, table, `${base}.xlsx`, controller.signal);
        saveBlob(blob, `${base}.xlsx`);
      } else {
        saveBlob(new Blob([rowsToCsv(table)], { type: "text/csv;charset=utf-8" }), `${base}.csv`);
      }
      setProgress({
        done: parents.length,
        total: Math.max(parents.length, 1),
        label: `Processing ${parents.length.toLocaleString()} of ${parents.length.toLocaleString()} records...`,
      });
      await new Promise((resolve) => window.setTimeout(resolve, 800));
      notify.success("Export ready", `${parents.length.toLocaleString()} ${entityName} records downloaded.`);
    } catch (error) {
      if (controller.signal.aborted || isCanceledRequestError(error)) return;
      notify.error("Export failed", getErrorMessage(error));
    } finally {
      if (abortRef.current === controller) {
        abortRef.current = null;
        setProgress(null);
      }
    }
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="w-[min(calc(100vw-2rem),480px)]" showCloseButton>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Download className="size-4 text-muted-foreground" />
              Export Options
            </DialogTitle>
            <DialogDescription>Select additional data to include in your export.</DialogDescription>
          </DialogHeader>
          <DialogBody className="space-y-3">
            {relations.length > 0 ? (
              <ul className="space-y-2">
                {relations.map((relation) => {
                  const checkboxId = `export-include-${relation.entity_slug}`;
                  return (
                    <li key={relation.entity_slug} className="flex items-center gap-2">
                      <Checkbox
                        id={checkboxId}
                        checked={selected.has(relation.entity_slug)}
                        onCheckedChange={(value) => toggle(relation.entity_slug, value === true)}
                      />
                      <label htmlFor={checkboxId} className="text-sm">
                        Include {relation.label}
                      </label>
                    </li>
                  );
                })}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">
                This entity has no related lists. The export will contain {entityName} rows only.
              </p>
            )}
            <div className="flex gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2.5 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
              <Info className="mt-0.5 size-4 shrink-0" />
              <p>Child records will be listed below each parent record in the export file.</p>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="button" className="gap-1.5" onClick={() => void startExport()}>
              <Download className="size-3.5" />
              Start Export
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {progress ? (
        <JobProgressCard
          title="Exporting data"
          label={progress.label}
          done={progress.done}
          total={progress.total}
          onCancel={cancelExport}
        />
      ) : null}
    </>
  );
}

async function loadParents(input: {
  entitySlug: string;
  search?: string;
  sort?: string;
  ids?: string[] | null;
  signal: AbortSignal;
  onProgress: (done: number, total: number) => void;
}): Promise<DynRecordListRow[]> {
  const wanted = input.ids && input.ids.length > 0 ? new Set(input.ids) : null;
  const collected: DynRecordListRow[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await fetchDynRecords(input.entitySlug, {
      page,
      per_page: 100,
      search: input.search || undefined,
      sort: input.sort,
      signal: input.signal,
    });
    const batch = wanted ? response.data.filter((row) => wanted.has(row.id)) : response.data;
    collected.push(...batch);
    lastPage = response.meta.last_page;
    const total = wanted ? wanted.size : response.meta.total;
    input.onProgress(Math.min(collected.length, total), total);
    if (wanted && collected.length >= wanted.size) break;
    page += 1;
  } while (page <= lastPage);
  return collected;
}

async function loadAllRecords(entity: string, signal: AbortSignal): Promise<DynRecordListRow[]> {
  const collected: DynRecordListRow[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await fetchDynRecords(entity, { page, per_page: 100, signal });
    collected.push(...response.data);
    lastPage = response.meta.last_page;
    page += 1;
  } while (page <= lastPage);
  return collected;
}

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}
