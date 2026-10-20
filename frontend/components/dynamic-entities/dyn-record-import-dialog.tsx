"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { ArrowRight, Check, Download, FileSpreadsheet, Info, ShieldCheck, Upload } from "lucide-react";

import { JobProgressCard } from "@/components/dynamic-entities/job-progress-card";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Select } from "@/components/ui/select";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import {
  analyzeDynImport,
  downloadDynImportTemplate,
  runDynImport,
  type DynImportAnalyzeResult,
  type DynImportResult,
} from "@/lib/api/modules/dynamic-entities-api";
import { getErrorMessage, isCanceledRequestError } from "@/lib/api/error";
import {
  csvFileFromBlocks,
  csvFileFromRows,
  IMPORT_BATCH_SIZE,
  importBlocksFromCsv,
  parentRowsFromCsv,
} from "@/lib/dynamic-entities/dyn-import-csv";
import { relationsForExport, type DynExportRelation } from "@/lib/dynamic-entities/dyn-record-export";
import { cn } from "@/lib/utils";

const IGNORE = "__ignore__";

export type DynRecordImportClient = {
  downloadTemplate: (include?: string[]) => Promise<Blob>;
  analyze: (file: File, signal?: AbortSignal) => Promise<DynImportAnalyzeResult>;
  run: (
    file: File,
    columnMap: Record<string, string>,
    upsertField: string | null,
    options?: { dryRun?: boolean; signal?: AbortSignal },
  ) => Promise<DynImportResult>;
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  entitySlug: string;
  entityName: string;
  onImported: () => void;
  relatedTabs?: Array<Record<string, unknown>>;
  client?: DynRecordImportClient;
};

type Step = "upload" | "map" | "preview" | "done";

type ImportProgress = {
  title: string;
  done: number;
  total: number;
  label: string;
};

export function DynRecordImportDialog({
  open,
  onOpenChange,
  entitySlug,
  entityName,
  onImported,
  relatedTabs,
  client,
}: Props) {
  const relations = useMemo(() => relationsForExport(relatedTabs), [relatedTabs]);
  const api: DynRecordImportClient = client ?? {
    downloadTemplate: (include) => downloadDynImportTemplate(entitySlug, include),
    analyze: (next, signal) => analyzeDynImport(entitySlug, next, signal),
    run: (next, columnMap, upsertField, options) =>
      runDynImport(entitySlug, next, columnMap, upsertField, options),
  };
  const fileInputId = "dyn-import-file";
  const [step, setStep] = useState<Step>("upload");
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [analyze, setAnalyze] = useState<DynImportAnalyzeResult | null>(null);
  const [columnMap, setColumnMap] = useState<Record<string, string>>({});
  const [upsertField, setUpsertField] = useState("");
  const [preview, setPreview] = useState<DynImportResult | null>(null);
  const [result, setResult] = useState<DynImportResult | null>(null);
  const [templatePromptOpen, setTemplatePromptOpen] = useState(false);
  const [templateInclude, setTemplateInclude] = useState<Set<string>>(new Set());
  const [progress, setProgress] = useState<ImportProgress | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const [prevOpen, setPrevOpen] = useState(open);
  if (open !== prevOpen) {
    setPrevOpen(open);
    if (open) {
      setStep("upload");
      setFile(null);
      setBusy(false);
      setError(null);
      setAnalyze(null);
      setColumnMap({});
      setUpsertField("");
      setPreview(null);
      setResult(null);
      setTemplatePromptOpen(false);
      setTemplateInclude(new Set());
      setProgress(null);
    } else {
      setProgress(null);
      setBusy(false);
    }
  }

  useEffect(() => {
    if (open) return;
    abortRef.current?.abort();
    abortRef.current = null;
  }, [open]);

  const mappedTargets = useMemo(() => {
    return new Set(Object.values(columnMap).filter((v) => v && v !== IGNORE));
  }, [columnMap]);

  const upsertOptions = useMemo(() => {
    if (!analyze) return [];
    return analyze.headers
      .map((h) => ({ header: h, field: columnMap[h] }))
      .filter((x) => x.field && x.field !== IGNORE) as Array<{ header: string; field: string }>;
  }, [analyze, columnMap]);

  const previewHasErrors = (preview?.error_rows ?? preview?.errors.length ?? 0) > 0;
  const previewValid = preview?.valid_rows ?? 0;

  function toggleTemplateInclude(slug: string, checked: boolean) {
    setTemplateInclude((current) => {
      const next = new Set(current);
      if (checked) next.add(slug);
      else next.delete(slug);
      return next;
    });
  }

  async function onDownloadTemplate() {
    setTemplatePromptOpen(false);
    setError(null);
    try {
      const blob = await api.downloadTemplate([...templateInclude]);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${entitySlug}-import-template.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError("Unable to download template.");
    }
  }

  function startProgress(title: string): AbortController {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setBusy(true);
    setError(null);
    setProgress({ title, done: 0, total: 0, label: "Preparing import…" });
    return controller;
  }

  function cancelProgress() {
    abortRef.current?.abort();
    abortRef.current = null;
    setProgress(null);
    setBusy(false);
  }

  async function onAnalyze() {
    if (!file) {
      setError("Select a CSV file first.");
      return;
    }
    const controller = startProgress("Analyzing data");
    try {
      const text = await file.text();
      if (controller.signal.aborted) return;
      const parsed = parentRowsFromCsv(text);
      const total = parsed.rows.length;
      setProgress({
        title: "Analyzing data",
        done: 0,
        total,
        label: `Processing 0 of ${total.toLocaleString()} records...`,
      });
      const sample = csvFileFromRows(parsed.headers, parsed.rows.slice(0, 6), file.name);
      const data = await api.analyze(sample, controller.signal);
      if (controller.signal.aborted) return;
      setAnalyze({ ...data, row_count: total });
      setColumnMap({ ...data.suggested_map });
      setUpsertField("");
      setPreview(null);
      setStep("map");
      setProgress({
        title: "Analyzing data",
        done: total,
        total,
        label: `Processing ${total.toLocaleString()} of ${total.toLocaleString()} records...`,
      });
      await new Promise((resolve) => window.setTimeout(resolve, 800));
    } catch (err) {
      if (controller.signal.aborted || isCanceledRequestError(err)) return;
      setError(getErrorMessage(err));
    } finally {
      if (abortRef.current === controller) {
        abortRef.current = null;
        setProgress(null);
        setBusy(false);
      }
    }
  }

  async function onValidate() {
    if (!file || !analyze) return;
    const controller = startProgress("Validating data");
    try {
      const data = await runInBatches(file, true, controller);
      if (controller.signal.aborted || !data) return;
      setPreview(data);
      setStep("preview");
      await new Promise((resolve) => window.setTimeout(resolve, 800));
    } catch (err) {
      if (controller.signal.aborted || isCanceledRequestError(err)) return;
      setError(getErrorMessage(err) || "Validation failed. Review mappings and required fields, then try again.");
    } finally {
      if (abortRef.current === controller) {
        abortRef.current = null;
        setProgress(null);
        setBusy(false);
      }
    }
  }

  async function onStartImport(skipInvalid: boolean) {
    if (!file || !analyze) return;
    if (!skipInvalid && previewHasErrors) {
      setError("Fix validation errors before importing, or choose “Import valid rows only”.");
      return;
    }
    const controller = startProgress("Importing data");
    try {
      const data = await runInBatches(file, false, controller);
      if (!data) return;
      if (controller.signal.aborted && data.created === 0 && data.updated === 0) return;
      setResult(data);
      setStep("done");
      if (data.created > 0 || data.updated > 0) onImported();
      await new Promise((resolve) => window.setTimeout(resolve, 800));
    } catch (err) {
      if (controller.signal.aborted || isCanceledRequestError(err)) return;
      setError(getErrorMessage(err) || "Import failed. Review mappings and required fields, then try again.");
    } finally {
      if (abortRef.current === controller) {
        abortRef.current = null;
        setProgress(null);
        setBusy(false);
      }
    }
  }

  async function runInBatches(
    source: File,
    dryRun: boolean,
    controller: AbortController,
  ): Promise<DynImportResult | null> {
    const parsed = importBlocksFromCsv(await source.text());
    if (controller.signal.aborted) return null;
    const total = parsed.blocks.length;
    const title = dryRun ? "Validating data" : "Importing data";
    const totals = emptyImportResult(dryRun);
    if (total === 0) {
      setProgress({
        title,
        done: 0,
        total: 0,
        label: "Processing 0 of 0 records...",
      });
      return totals;
    }
    for (let start = 0; start < total; start += IMPORT_BATCH_SIZE) {
      if (controller.signal.aborted) return dryRun ? null : totals;
      const slice = parsed.blocks.slice(start, start + IMPORT_BATCH_SIZE);
      const chunk = csvFileFromBlocks(parsed.headers, slice, source.name);
      const part = await api.run(chunk, columnMap, upsertField || null, {
        dryRun,
        signal: controller.signal,
      });
      if (controller.signal.aborted) return dryRun ? null : totals;
      addImportResult(totals, part, start);
      const done = Math.min(start + slice.length, total);
      setProgress({
        title,
        done,
        total,
        label: `Processing ${done.toLocaleString()} of ${total.toLocaleString()} records...`,
      });
    }
    return totals;
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="w-[min(calc(100vw-2rem),640px)]" showCloseButton>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Upload className="size-4 text-muted-foreground" />
              {step === "upload" && "Bulk Upload to Entity"}
              {step === "map" && "Step 2: Review & Confirm Mappings"}
              {step === "preview" && "Step 3: Validation preview"}
              {step === "done" && "Import complete"}
            </DialogTitle>
            <DialogDescription>
              {step === "upload"
                ? `Import CSV rows into ${entityName}.`
                : step === "map"
                  ? "Review suggested field mappings, then validate before writing records."
                  : step === "preview"
                    ? "Dry-run results — no records were written yet."
                    : "Summary of created and updated records."}
            </DialogDescription>
          </DialogHeader>

          <DialogBody className="space-y-4">
            {step === "upload" ? (
              <>
                <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2.5 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                  <p className="font-medium">Step 1: Select a CSV file to begin.</p>
                  <p className="mt-1">
                    The AI will analyze the headers and suggest field mappings for your review.
                  </p>
                </div>
                <div className="space-y-1.5">
                  <label className="text-xs font-medium text-muted-foreground" htmlFor={fileInputId}>
                    CSV File
                  </label>
                  <Input
                    id={fileInputId}
                    type="file"
                    accept=".csv,text/csv"
                    className="h-10 cursor-pointer"
                    onChange={(e) => {
                      setFile(e.target.files?.[0] ?? null);
                      setError(null);
                    }}
                  />
                  {file ? (
                    <p className="text-xs text-muted-foreground">
                      Selected: <span className="font-medium text-foreground">{file.name}</span>
                    </p>
                  ) : null}
                </div>
                <div className="flex flex-col items-center gap-2 pt-2">
                  <p className="text-sm text-muted-foreground">Don&apos;t have a template?</p>
                  <Button
                    type="button"
                    variant="outline"
                    className="gap-1.5"
                    disabled={busy}
                    onClick={() => {
                      setTemplateInclude(new Set());
                      setTemplatePromptOpen(true);
                    }}
                  >
                    <Download className="size-3.5" />
                    Download CSV Data Template
                  </Button>
                </div>
              </>
            ) : null}

            {step === "map" && analyze ? (
              <>
                <div className="rounded-lg border border-border bg-muted/40 px-3 py-2.5 text-sm text-muted-foreground">
                  Suggested mappings are below. Review and correct them as needed. For columns you
                  don&apos;t want to import, select &quot;Ignore this column&quot;. Validation checks
                  required fields, types, select options, and related records before any write.
                </div>

                <div className="overflow-hidden rounded-xl border border-border">
                  <div className="grid grid-cols-[1fr_auto_1fr] gap-2 border-b border-border bg-muted/50 px-3 py-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    <span>CSV header</span>
                    <span />
                    <span>Entity field</span>
                  </div>
                  <ul className="divide-y divide-border">
                    {analyze.headers.map((header, index) => (
                      <li
                        key={`${index}:${header}`}
                        className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 px-3 py-2.5"
                      >
                        <span className="truncate text-sm font-medium">{header}</span>
                        <ArrowRight className="size-3.5 text-muted-foreground" />
                        <Select
                          className="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
                          value={columnMap[header] ?? IGNORE}
                          onChange={(e) =>
                            setColumnMap((prev) => ({ ...prev, [header]: e.target.value }))
                          }
                        >
                          <option value={IGNORE}>Ignore this column</option>
                          {client ? null : (
                            <>
                              <option value="title">Title (title)</option>
                              <option value="status">Status (status)</option>
                            </>
                          )}
                          {analyze.fields.map((f) => {
                            const taken =
                              mappedTargets.has(f.name) && columnMap[header] !== f.name;
                            return (
                              <option key={f.name} value={f.name} disabled={taken}>
                                {f.label} ({f.name})
                                {f.is_required ? " *" : ""}
                              </option>
                            );
                          })}
                        </Select>
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="space-y-1.5">
                  <p className="text-sm font-medium">Identifier for Updates &amp; Duplication Check</p>
                  <Select
                    className="h-9 w-full"
                    value={upsertField}
                    onChange={(e) => setUpsertField(e.target.value)}
                  >
                    <option value="">— Automatic Duplication Check / Insert Only —</option>
                    {upsertOptions.map((opt, index) => (
                      <option key={`${index}:${opt.header}:${opt.field}`} value={opt.field}>
                        {opt.header}
                      </option>
                    ))}
                  </Select>
                  <p className="text-xs text-muted-foreground">
                    {entitySlug === "it_asset"
                      ? "A serial number is one asset. If that laptop was already issued and shows up again, the row updates it and keeps the previous holder in the history. "
                      : "Select a unique column to update matches. Otherwise new records are created. "}
                    About {analyze.row_count.toLocaleString()} data row
                    {analyze.row_count === 1 ? "" : "s"} detected.
                  </p>
                </div>
              </>
            ) : null}

            {step === "preview" && preview ? (
              <div className="space-y-3">
                <div
                  className={cn(
                    "rounded-lg border px-3 py-2.5 text-sm",
                    previewHasErrors
                      ? "border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                      : "border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100",
                  )}
                >
                  {previewHasErrors
                    ? `${preview.error_rows ?? preview.errors.length} row(s) failed validation. Fix the CSV or import valid rows only.`
                    : "All mapped rows passed validation. Ready to import."}
                </div>
                <div className="grid gap-2 sm:grid-cols-3">
                  <Stat label="Would create" value={preview.would_create ?? 0} tone="success" />
                  <Stat label="Would update" value={preview.would_update ?? 0} tone="info" />
                  <Stat label="Invalid / skip" value={preview.would_skip ?? 0} tone="muted" />
                </div>
                {(preview.child_would_create ?? 0) > 0 ? (
                  <p className="text-sm text-muted-foreground">
                    Child lists: {(preview.child_would_create ?? 0).toLocaleString()} records ready
                    under the matching sites.
                  </p>
                ) : null}
                {preview.errors.length > 0 ? (
                  <div className="max-h-48 overflow-y-auto rounded-lg border border-border">
                    <ul className="divide-y divide-border text-sm">
                      {preview.errors.map((err) => (
                        <li key={`${err.row}-${err.message}`} className="px-3 py-2">
                          <span className="font-medium text-destructive">Row {err.row}:</span>{" "}
                          <span className="text-muted-foreground">{err.message}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">
                    {previewValid.toLocaleString()} valid row{previewValid === 1 ? "" : "s"} ready to
                    write.
                  </p>
                )}
              </div>
            ) : null}

            {step === "done" && result ? (
              <div className="space-y-3">
                <div className="grid gap-2 sm:grid-cols-3">
                  <Stat label="Created" value={result.created} tone="success" />
                  <Stat label="Updated" value={result.updated} tone="info" />
                  <Stat label="Skipped" value={result.skipped} tone="muted" />
                </div>
                {result.errors.length > 0 ? (
                  <div className="max-h-40 overflow-y-auto rounded-lg border border-border">
                    <ul className="divide-y divide-border text-sm">
                      {result.errors.map((err) => (
                        <li key={`${err.row}-${err.message}`} className="px-3 py-2">
                          <span className="font-medium">Row {err.row}:</span>{" "}
                          <span className="text-muted-foreground">{err.message}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">No row errors reported.</p>
                )}
              </div>
            ) : null}

            {error ? <p className="text-sm text-destructive">{error}</p> : null}
          </DialogBody>

          <DialogFooter>
            {step === "upload" ? (
              <>
                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                  Cancel
                </Button>
                <Button
                  type="button"
                  disabled={busy || !file}
                  className="gap-1.5"
                  onClick={() => void onAnalyze()}
                >
                  {busy ? "Analyzing…" : "Analyze and Map Columns"}
                  <ArrowRight className="size-3.5" />
                </Button>
              </>
            ) : null}
            {step === "map" ? (
              <>
                <Button type="button" variant="outline" disabled={busy} onClick={() => setStep("upload")}>
                  Back
                </Button>
                <Button
                  type="button"
                  disabled={busy || mappedTargets.size === 0}
                  className="gap-1.5"
                  onClick={() => void onValidate()}
                >
                  <ShieldCheck className="size-3.5" />
                  {busy ? "Validating…" : "Validate import"}
                </Button>
              </>
            ) : null}
            {step === "preview" ? (
              <>
                <Button
                  type="button"
                  variant="outline"
                  disabled={busy}
                  onClick={() => {
                    setPreview(null);
                    setStep("map");
                  }}
                >
                  Back
                </Button>
                {previewHasErrors && previewValid > 0 ? (
                  <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    className="gap-1.5"
                    onClick={() => void onStartImport(true)}
                  >
                    {busy ? "Importing…" : "Import valid rows only"}
                  </Button>
                ) : null}
                <Button
                  type="button"
                  disabled={busy || previewHasErrors || previewValid === 0}
                  className="gap-1.5"
                  onClick={() => void onStartImport(false)}
                >
                  <Check className="size-3.5" />
                  {busy ? "Importing…" : "Start Import"}
                </Button>
              </>
            ) : null}
            {step === "done" ? (
              <Button type="button" onClick={() => onOpenChange(false)}>
                Close
              </Button>
            ) : null}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={templatePromptOpen} onOpenChange={setTemplatePromptOpen}>
        <DialogContent className="w-[min(calc(100vw-2rem),480px)]" showCloseButton>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FileSpreadsheet className="size-4 text-muted-foreground" />
              Import Template Options
            </DialogTitle>
            <DialogDescription>
              {relations.length > 0
                ? "Select which related items you want to include in your import template:"
                : "No related lists available for this entity. Proceed with the standard template?"}
            </DialogDescription>
          </DialogHeader>
          <DialogBody className="space-y-3">
            {relations.length > 0 ? (
              <ul className="space-y-2">
                {relations.map((relation: DynExportRelation) => {
                  const checkboxId = `template-include-${relation.entity_slug}`;
                  return (
                    <li key={relation.entity_slug} className="flex items-center gap-2">
                      <Checkbox
                        id={checkboxId}
                        checked={templateInclude.has(relation.entity_slug)}
                        onCheckedChange={(value) => toggleTemplateInclude(relation.entity_slug, value === true)}
                      />
                      <label htmlFor={checkboxId} className="text-sm">
                        Include {relation.label}
                      </label>
                    </li>
                  );
                })}
              </ul>
            ) : null}
            <div className="flex gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2.5 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
              <Info className="mt-0.5 size-4 shrink-0" />
              <p>The template will include parent headers and child headers separated by markers.</p>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setTemplatePromptOpen(false)}>
              Cancel
            </Button>
            <Button type="button" className="gap-1.5" disabled={busy} onClick={() => void onDownloadTemplate()}>
              <Download className="size-3.5" />
              Download Template
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {progress ? (
        <JobProgressCard
          title={progress.title}
          label={progress.label}
          done={progress.done}
          total={progress.total}
          onCancel={cancelProgress}
        />
      ) : null}
    </>
  );
}

function emptyImportResult(dryRun: boolean): DynImportResult {
  return {
    dry_run: dryRun,
    created: 0,
    updated: 0,
    skipped: 0,
    would_create: 0,
    would_update: 0,
    would_skip: 0,
    valid_rows: 0,
    error_rows: 0,
    errors: [],
    child_would_create: 0,
    child_created: 0,
  };
}

function addImportResult(totals: DynImportResult, part: DynImportResult, batchStart: number) {
  totals.created += part.created ?? 0;
  totals.updated += part.updated ?? 0;
  totals.skipped += part.skipped ?? 0;
  totals.would_create = (totals.would_create ?? 0) + (part.would_create ?? 0);
  totals.would_update = (totals.would_update ?? 0) + (part.would_update ?? 0);
  totals.would_skip = (totals.would_skip ?? 0) + (part.would_skip ?? 0);
  totals.valid_rows = (totals.valid_rows ?? 0) + (part.valid_rows ?? 0);
  totals.error_rows = (totals.error_rows ?? 0) + (part.error_rows ?? part.errors.length);
  totals.child_would_create = (totals.child_would_create ?? 0) + (part.child_would_create ?? 0);
  totals.child_created = (totals.child_created ?? 0) + (part.child_created ?? 0);
  for (const err of part.errors) {
    totals.errors.push({ row: batchStart + err.row, message: err.message });
  }
}

function Stat({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone: "success" | "info" | "muted";
}) {
  return (
    <div
      className={cn(
        "rounded-xl border px-3 py-2",
        tone === "success" && "border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30",
        tone === "info" && "border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/30",
        tone === "muted" && "border-border bg-muted/40",
      )}
    >
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-lg font-medium tabular-nums">{value.toLocaleString()}</p>
    </div>
  );
}
