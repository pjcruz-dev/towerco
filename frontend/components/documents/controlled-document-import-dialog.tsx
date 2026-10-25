"use client";

import { useMemo, useState } from "react";
import { ArrowRight, Check, Download, ShieldCheck, Upload } from "lucide-react";

import { Button } from "@/components/ui/button";
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
import { Select } from "@/components/ui/select";
import {
  analyzeControlledDocumentImport,
  downloadControlledDocumentImportTemplate,
  previewControlledDocumentImport,
  runControlledDocumentImport,
  type ControlledDocumentImportAnalyzeResult,
  type ControlledDocumentImportRunResult,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { cn } from "@/lib/utils";

const IGNORE = "__ignore__";
const BATCH_FILES = 20;
const BATCH_BYTES = 32 * 1024 * 1024;

type Step = "upload" | "map" | "preview" | "done";

type LibraryFile = {
  path: string;
  file: File;
};

type RunTotals = {
  created: number;
  updated: number;
  skipped: number;
  filesAttached: number;
  errors: { row: number; message: string }[];
};

export function ControlledDocumentImportDialog({
  open,
  onOpenChange,
  onImported,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onImported: () => void;
}) {
  const [step, setStep] = useState<Step>("upload");
  const [csvFile, setCsvFile] = useState<File | null>(null);
  const [libraryFiles, setLibraryFiles] = useState<LibraryFile[]>([]);
  const [archive, setArchive] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [progress, setProgress] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [analyze, setAnalyze] = useState<ControlledDocumentImportAnalyzeResult | null>(null);
  const [columnMap, setColumnMap] = useState<Record<string, string>>({});
  const [preview, setPreview] = useState<ControlledDocumentImportRunResult | null>(null);
  const [totals, setTotals] = useState<RunTotals | null>(null);
  const [wasOpen, setWasOpen] = useState(false);
  if (open !== wasOpen) {
    setWasOpen(open);
    if (open) {
      setStep("upload");
      setCsvFile(null);
      setLibraryFiles([]);
      setArchive(null);
      setBusy(false);
      setProgress(null);
      setError(null);
      setAnalyze(null);
      setColumnMap({});
      setPreview(null);
      setTotals(null);
    }
  }

  const mappedTargets = useMemo(
    () => new Set(Object.values(columnMap).filter((value) => value && value !== IGNORE)),
    [columnMap],
  );
  const previewErrors = preview?.errors.length ?? 0;
  const previewAttach = preview?.rows.filter((row) => row.outcome === "attached").length ?? 0;
  const previewValid = (preview?.created ?? 0) + (preview?.updated ?? 0) + previewAttach;

  async function onDownloadTemplate() {
    setBusy(true);
    setError(null);
    try {
      const blob = await downloadControlledDocumentImportTemplate();
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = "controlled-document-register-template.csv";
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (downloadError) {
      setError(getErrorMessage(downloadError));
    } finally {
      setBusy(false);
    }
  }

  async function onAnalyze() {
    if (!csvFile) {
      setError("Select a CSV file first.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const data = await analyzeControlledDocumentImport(csvFile);
      setAnalyze(data);
      setColumnMap({ ...data.suggested_map });
      setPreview(null);
      setStep("map");
    } catch (analyzeError) {
      setError(getErrorMessage(analyzeError));
    } finally {
      setBusy(false);
    }
  }

  async function onValidate() {
    if (!csvFile) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const data = await previewControlledDocumentImport({
        file: csvFile,
        columnMap,
        availablePaths: libraryFiles.map((item) => item.path),
        archive,
      });
      setPreview(data);
      setStep("preview");
    } catch (previewError) {
      setError(getErrorMessage(previewError));
    } finally {
      setBusy(false);
    }
  }

  async function onStartImport() {
    if (!csvFile || !preview || previewValid === 0) {
      return;
    }
    setBusy(true);
    setError(null);
    const availablePaths = libraryFiles.map((item) => item.path);
    const matched = matchedLibraryFiles(preview, libraryFiles);
    const groups = archive ? [] : chunkLibraryFiles(matched);
    const summary: RunTotals = { created: 0, updated: 0, skipped: 0, filesAttached: 0, errors: [] };

    try {
      if (archive || groups.length === 0) {
        setProgress(archive ? "Importing the zip" : "Importing rows");
        const result = await runControlledDocumentImport({
          file: csvFile,
          columnMap,
          availablePaths,
          archive,
        });
        absorbRun(summary, result, true);
      } else {
        for (let index = 0; index < groups.length; index += 1) {
          const group = groups[index] ?? [];
          setProgress(`Attaching files ${index + 1} of ${groups.length}`);
          const result = await runControlledDocumentImport({
            file: csvFile,
            columnMap,
            availablePaths,
            files: group.map((item) => item.file),
            filePaths: group.map((item) => item.path),
          });
          absorbRun(summary, result, index === 0);
        }
      }
      setTotals(summary);
      setStep("done");
      onImported();
    } catch (importError) {
      setError(getErrorMessage(importError));
    } finally {
      setBusy(false);
      setProgress(null);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="w-[min(calc(100vw-2rem),640px)]" showCloseButton>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Upload className="size-4 text-muted-foreground" />
            {step === "upload" && "Import controlled documents"}
            {step === "map" && "Step 2: Review and confirm mappings"}
            {step === "preview" && "Step 3: Validation preview"}
            {step === "done" && "Import complete"}
          </DialogTitle>
          <DialogDescription>
            {step === "upload"
              ? "Select a CSV or Excel file. Add the library folder or a zip when a column points at the file."
              : step === "map"
                ? "Document code and title are required. Folder path locates the file for that row."
                : step === "preview"
                  ? "Nothing has been written yet. Rows with a missing or rejected file are left out."
                  : "Summary of the register rows and files that were stored."}
          </DialogDescription>
        </DialogHeader>

        <DialogBody className="space-y-4">
          {step === "upload" ? (
            <>
              <div className="rounded-lg border border-border bg-muted/40 px-3 py-2.5 text-sm text-muted-foreground">
                Step 1: Select a CSV or Excel file. Headers are analyzed and field mappings are suggested
                for your review. A Folder Path cell is matched to a file inside the folder or zip you add.
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-muted-foreground" htmlFor="cdr-import-csv">
                  CSV or Excel file
                </label>
                <Input
                  id="cdr-import-csv"
                  type="file"
                  accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                  className="h-10 cursor-pointer"
                  onChange={(event) => {
                    setCsvFile(event.target.files?.[0] ?? null);
                    setError(null);
                  }}
                />
                {csvFile ? <p className="text-xs text-muted-foreground">Selected: {csvFile.name}</p> : null}
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-muted-foreground" htmlFor="cdr-import-folder">
                  Library folder
                </label>
                <Input
                  id="cdr-import-folder"
                  type="file"
                  multiple
                  className="h-10 cursor-pointer"
                  {...{ webkitdirectory: "", directory: "" }}
                  onChange={(event) => {
                    const files = Array.from(event.target.files ?? []);
                    setLibraryFiles(
                      files.map((file) => ({
                        path: file.webkitRelativePath || file.name,
                        file,
                      })),
                    );
                    setArchive(null);
                    setError(null);
                  }}
                />
                {libraryFiles.length > 0 ? (
                  <p className="text-xs text-muted-foreground">{libraryFiles.length} files in the folder</p>
                ) : null}
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-muted-foreground" htmlFor="cdr-import-zip">
                  Or a zip of that folder
                </label>
                <Input
                  id="cdr-import-zip"
                  type="file"
                  accept=".zip,application/zip"
                  className="h-10 cursor-pointer"
                  onChange={(event) => {
                    setArchive(event.target.files?.[0] ?? null);
                    setLibraryFiles([]);
                    setError(null);
                  }}
                />
                {archive ? <p className="text-xs text-muted-foreground">Selected: {archive.name}</p> : null}
              </div>
              <button
                type="button"
                className="inline-flex items-center gap-1.5 text-sm font-medium text-foreground underline-offset-2 hover:underline"
                disabled={busy}
                onClick={() => void onDownloadTemplate()}
              >
                <Download className="size-3.5" />
                Download CSV template
              </button>
            </>
          ) : null}

          {step === "map" && analyze ? (
            <>
              <div className="rounded-lg border border-border bg-muted/40 px-3 py-2.5 text-sm text-muted-foreground">
                Suggested mappings are below. Ignore a column you do not want. Document code plus
                revision number identify the row. {analyze.row_count.toLocaleString()} data row
                {analyze.row_count === 1 ? "" : "s"} detected.
              </div>
              <div className="overflow-hidden rounded-xl border border-border">
                <div className="grid grid-cols-[1fr_auto_1fr] gap-2 border-b border-border bg-muted/50 px-3 py-2 text-xs font-medium text-muted-foreground">
                  <span>CSV header</span>
                  <span />
                  <span>Register field</span>
                </div>
                <ul className="max-h-80 divide-y divide-border overflow-y-auto">
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
                        onChange={(event) =>
                          setColumnMap((current) => ({ ...current, [header]: event.target.value }))
                        }
                      >
                        <option value={IGNORE}>Ignore this column</option>
                        {analyze.fields.map((field) => {
                          const taken = mappedTargets.has(field.name) && columnMap[header] !== field.name;
                          return (
                            <option key={field.name} value={field.name} disabled={taken}>
                              {field.label}
                              {field.required ? " *" : ""}
                            </option>
                          );
                        })}
                      </Select>
                    </li>
                  ))}
                </ul>
              </div>
            </>
          ) : null}

          {step === "preview" && preview ? (
            <div className="space-y-3">
              <div
                className={cn(
                  "rounded-lg border px-3 py-2.5 text-sm",
                  previewErrors > 0
                    ? "border-border bg-muted/40 text-foreground"
                    : "border-border bg-muted/40 text-foreground",
                )}
              >
                {previewErrors > 0
                  ? `${previewErrors} row(s) will be left out. The other rows can be imported.`
                  : "Mapped rows are ready to import."}
              </div>
              <div className="grid gap-2 sm:grid-cols-4">
                <Stat label="Would create" value={preview.created} />
                <Stat label="Would update" value={preview.updated} />
                <Stat label="Would attach" value={previewAttach} />
                <Stat label="Need a file" value={previewErrors} />
              </div>
              {preview.skipped > 0 ? (
                <p className="text-sm text-muted-foreground">
                  {preview.skipped} row{preview.skipped === 1 ? "" : "s"} already on the register and will be left as they are.
                </p>
              ) : null}
              {preview.errors.length > 0 ? (
                <div className="max-h-48 overflow-y-auto rounded-lg border border-border">
                  <ul className="divide-y divide-border text-sm">
                    {preview.errors.slice(0, 40).map((err) => (
                      <li key={`${err.row}-${err.message}`} className="px-3 py-2">
                        <span className="font-medium text-destructive">Row {err.row}:</span>{" "}
                        <span className="text-muted-foreground">{err.message}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">
                  {preview.rows.filter((row) => row.file_status === "matched").length} file
                  {preview.rows.filter((row) => row.file_status === "matched").length === 1 ? "" : "s"}{" "}
                  matched.
                </p>
              )}
            </div>
          ) : null}

          {step === "done" && totals ? (
            <div className="grid gap-2 sm:grid-cols-4">
              <Stat label="Created" value={totals.created} />
              <Stat label="Updated" value={totals.updated} />
              <Stat label="Skipped" value={totals.skipped} />
              <Stat label="Files attached" value={totals.filesAttached} />
              {totals.errors.length > 0 ? (
                <div className="max-h-40 overflow-y-auto rounded-lg border border-border sm:col-span-4">
                  <ul className="divide-y divide-border text-sm">
                    {totals.errors.slice(0, 40).map((err) => (
                      <li key={`${err.row}-${err.message}`} className="px-3 py-2">
                        <span className="font-medium">Row {err.row}:</span>{" "}
                        <span className="text-muted-foreground">{err.message}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          ) : null}

          {progress ? <p className="text-sm text-muted-foreground">{progress}</p> : null}
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
                disabled={busy || !csvFile}
                className="gap-1.5"
                onClick={() => void onAnalyze()}
              >
                {busy ? "Analyzing…" : "Analyze and map columns"}
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
                disabled={busy || !mappedTargets.has("document_code") || !mappedTargets.has("title")}
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
              <Button
                type="button"
                disabled={busy || previewValid === 0}
                className="gap-1.5"
                onClick={() => void onStartImport()}
              >
                <Check className="size-3.5" />
                {busy ? "Importing…" : previewErrors > 0 ? "Import valid rows" : "Start import"}
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
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-border bg-card px-3 py-2">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-lg font-semibold tabular-nums text-foreground">{value}</p>
    </div>
  );
}

function absorbRun(summary: RunTotals, result: ControlledDocumentImportRunResult, recordSkips: boolean) {
  summary.created += result.created;
  summary.updated += result.updated;
  summary.filesAttached += result.files_attached;
  if (recordSkips) {
    summary.skipped = result.skipped;
    summary.errors = result.errors;
  }
}

function matchedLibraryFiles(
  preview: ControlledDocumentImportRunResult,
  libraryFiles: LibraryFile[],
): LibraryFile[] {
  const byPath = new Map(libraryFiles.map((item) => [normalizePath(item.path), item]));
  const chosen: LibraryFile[] = [];
  const seen = new Set<string>();
  for (const row of preview.rows) {
    if (row.file_status !== "matched" || !row.matched_path || row.outcome === "error") {
      continue;
    }
    const item = byPath.get(normalizePath(row.matched_path));
    if (!item || seen.has(item.path)) {
      continue;
    }
    seen.add(item.path);
    chosen.push(item);
  }
  return chosen;
}

function chunkLibraryFiles(files: LibraryFile[]): LibraryFile[][] {
  const groups: LibraryFile[][] = [];
  let current: LibraryFile[] = [];
  let bytes = 0;
  for (const item of files) {
    const nextBytes = bytes + item.file.size;
    if (current.length > 0 && (current.length >= BATCH_FILES || nextBytes > BATCH_BYTES)) {
      groups.push(current);
      current = [];
      bytes = 0;
    }
    current.push(item);
    bytes += item.file.size;
  }
  if (current.length > 0) {
    groups.push(current);
  }
  return groups;
}

function normalizePath(value: string): string {
  return value.replace(/\\/g, "/").replace(/^\/+/, "").replace(/\/+$/, "").toLowerCase();
}
