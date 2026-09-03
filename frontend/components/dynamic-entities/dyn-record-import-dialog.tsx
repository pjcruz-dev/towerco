"use client";

import { useEffect, useMemo, useState } from "react";
import { ArrowRight, Check, Download, FileSpreadsheet, ShieldCheck, Upload } from "lucide-react";

import { Button } from "@/components/ui/button";
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
import { cn } from "@/lib/utils";

const IGNORE = "__ignore__";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  entitySlug: string;
  entityName: string;
  onImported: () => void;
};

type Step = "upload" | "map" | "preview" | "done";

export function DynRecordImportDialog({
  open,
  onOpenChange,
  entitySlug,
  entityName,
  onImported,
}: Props) {
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

  useEffect(() => {
    if (!open) return;
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

  async function onDownloadTemplate() {
    setTemplatePromptOpen(false);
    setBusy(true);
    setError(null);
    try {
      const blob = await downloadDynImportTemplate(entitySlug);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${entitySlug}-import-template.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError("Unable to download template.");
    } finally {
      setBusy(false);
    }
  }

  async function onAnalyze() {
    if (!file) {
      setError("Select a CSV file first.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const data = await analyzeDynImport(entitySlug, file);
      setAnalyze(data);
      setColumnMap({ ...data.suggested_map });
      setUpsertField("");
      setPreview(null);
      setStep("map");
    } catch {
      setError("Unable to analyze CSV. Check the file format and try again.");
    } finally {
      setBusy(false);
    }
  }

  async function onValidate() {
    if (!file || !analyze) return;
    setBusy(true);
    setError(null);
    try {
      const data = await runDynImport(entitySlug, file, columnMap, upsertField || null, {
        dryRun: true,
      });
      setPreview(data);
      setStep("preview");
    } catch {
      setError("Validation failed. Review mappings and required fields, then try again.");
    } finally {
      setBusy(false);
    }
  }

  async function onStartImport(skipInvalid: boolean) {
    if (!file || !analyze) return;
    if (!skipInvalid && previewHasErrors) {
      setError("Fix validation errors before importing, or choose “Import valid rows only”.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const data = await runDynImport(entitySlug, file, columnMap, upsertField || null);
      setResult(data);
      setStep("done");
      onImported();
    } catch {
      setError("Import failed. Review mappings and required fields, then try again.");
    } finally {
      setBusy(false);
    }
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
                  Step 1: Select a CSV file to begin. Headers are analyzed and field mappings are
                  suggested for your review.
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
                <button
                  type="button"
                  className="inline-flex items-center gap-1.5 text-sm font-medium text-sky-700 hover:underline dark:text-sky-400"
                  disabled={busy}
                  onClick={() => setTemplatePromptOpen(true)}
                >
                  <Download className="size-3.5" />
                  Download CSV Data Template
                </button>
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
                          <option value="title">Title (title)</option>
                          <option value="status">Status (status)</option>
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
                    Select a unique column (code, name, etc.) to update matches; otherwise new
                    records are created. About {analyze.row_count.toLocaleString()} data row
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
        <DialogContent className="w-[min(calc(100vw-2rem),420px)]" showCloseButton>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FileSpreadsheet className="size-4 text-muted-foreground" />
              Import Template Options
            </DialogTitle>
            <DialogDescription>
              No related lists available for this entity. Proceed with standard template?
            </DialogDescription>
          </DialogHeader>
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
    </>
  );
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
