"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { ArrowRight, Download, Upload } from "lucide-react";

import { JobProgressCard } from "@/components/dynamic-entities/job-progress-card";
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
import { bulkImportEApprovalMasterDataRows } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage, isCanceledRequestError } from "@/lib/api/error";
import {
  MASTER_DATA_IMPORT_IGNORE,
  masterDataImportTargets,
  masterDataTemplateCsv,
  parseMasterDataCsv,
  planMasterDataImport,
  suggestMasterDataColumnMap,
  type MasterDataCsvColumn,
  type MasterDataExistingRow,
  type MasterDataPlannedRow,
} from "@/modules/e-approval/master-data-csv";

const BATCH_SIZE = 50;

type Step = "upload" | "map" | "preview" | "done";

type ImportProgress = {
  title: string;
  done: number;
  total: number;
  label: string;
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  setId: string;
  setKey: string;
  setName: string;
  columns: MasterDataCsvColumn[];
  existing: MasterDataExistingRow[];
  onImported: () => void;
};

function downloadText(filename: string, text: string) {
  const blob = new Blob([text], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

export function EApprovalMasterDataImportDialog({
  open,
  onOpenChange,
  setId,
  setKey,
  setName,
  columns,
  existing,
  onImported,
}: Props) {
  const fileInputId = "ea-master-data-import-file";
  const [step, setStep] = useState<Step>("upload");
  const [file, setFile] = useState<File | null>(null);
  const [matrix, setMatrix] = useState<string[][]>([]);
  const [columnMap, setColumnMap] = useState<Record<string, string>>({});
  const [planned, setPlanned] = useState<MasterDataPlannedRow[]>([]);
  const [created, setCreated] = useState(0);
  const [serverErrors, setServerErrors] = useState<{ row: number; message: string }[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [progress, setProgress] = useState<ImportProgress | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const [prevOpen, setPrevOpen] = useState(open);

  if (open !== prevOpen) {
    setPrevOpen(open);
    if (open) {
      setStep("upload");
      setFile(null);
      setMatrix([]);
      setColumnMap({});
      setPlanned([]);
      setCreated(0);
      setServerErrors([]);
      setBusy(false);
      setError(null);
      setProgress(null);
    }
  }

  useEffect(() => {
    if (open) {
      return;
    }
    abortRef.current?.abort();
    abortRef.current = null;
  }, [open]);

  const headers = matrix[0] ?? [];
  const targets = useMemo(() => masterDataImportTargets(columns), [columns]);
  const mappedTargets = useMemo(
    () => new Set(Object.values(columnMap).filter((value) => value && value !== MASTER_DATA_IMPORT_IGNORE)),
    [columnMap],
  );
  const clientErrors = planned.filter((row) => row.error);
  const validRows = planned.filter((row) => row.error === null);
  const labelMapped = Object.values(columnMap).includes("label");

  function startProgress(title: string): AbortController {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setBusy(true);
    setError(null);
    setProgress({ title, done: 0, total: 0, label: "Preparing import…" });
    return controller;
  }

  function finishProgress(controller: AbortController) {
    if (abortRef.current === controller) {
      abortRef.current = null;
      setProgress(null);
      setBusy(false);
    }
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
      const parsed = parseMasterDataCsv(await file.text());
      if (controller.signal.aborted) {
        return;
      }
      const headerRow = parsed[0] ?? [];
      if (headerRow.length === 0 || headerRow.every((cell) => cell.trim() === "")) {
        setError("The file has no header row.");
        return;
      }
      const total = Math.max(parsed.length - 1, 0);
      setProgress({
        title: "Analyzing data",
        done: total,
        total,
        label: `Processing ${total.toLocaleString()} of ${total.toLocaleString()} records...`,
      });
      setMatrix(parsed);
      setColumnMap(suggestMasterDataColumnMap(headerRow, columns));
      setPlanned([]);
      setStep("map");
      await new Promise((resolve) => window.setTimeout(resolve, 800));
    } catch (err) {
      if (!controller.signal.aborted) {
        setError(getErrorMessage(err));
      }
    } finally {
      finishProgress(controller);
    }
  }

  async function onValidate() {
    if (!labelMapped) {
      setError("Map one column to Label.");
      return;
    }
    const controller = startProgress("Validating data");
    try {
      const next = planMasterDataImport(matrix, columnMap, columns, existing);
      if (controller.signal.aborted) {
        return;
      }
      const ready = next.filter((row) => row.error === null);
      const total = next.length;
      const collected: { row: number; message: string }[] = [];
      for (let start = 0; start < ready.length; start += BATCH_SIZE) {
        if (controller.signal.aborted) {
          return;
        }
        const slice = ready.slice(start, start + BATCH_SIZE);
        const part = await bulkImportEApprovalMasterDataRows(
          setId,
          slice.map((row) => ({
            row_number: row.rowNumber,
            label: row.label,
            code: row.code || null,
            is_active: row.isActive,
            data: row.data,
          })),
          { dryRun: true, signal: controller.signal },
        );
        collected.push(...part.errors);
        const done = Math.min(start + slice.length + (total - ready.length), total);
        setProgress({
          title: "Validating data",
          done,
          total,
          label: `Processing ${done.toLocaleString()} of ${total.toLocaleString()} records...`,
        });
      }
      if (ready.length === 0) {
        setProgress({
          title: "Validating data",
          done: total,
          total,
          label: `Processing ${total.toLocaleString()} of ${total.toLocaleString()} records...`,
        });
      }
      const serverByRow = new Map(collected.map((item) => [item.row, item.message]));
      setPlanned(
        next.map((row) =>
          row.error || !serverByRow.has(row.rowNumber)
            ? row
            : { ...row, error: serverByRow.get(row.rowNumber) ?? row.error },
        ),
      );
      setServerErrors([]);
      setStep("preview");
      await new Promise((resolve) => window.setTimeout(resolve, 800));
    } catch (err) {
      if (controller.signal.aborted || isCanceledRequestError(err)) {
        return;
      }
      setError(getErrorMessage(err) || "Validation failed.");
    } finally {
      finishProgress(controller);
    }
  }

  async function onStartImport() {
    const ready = planned.filter((row) => row.error === null);
    if (ready.length === 0) {
      setError("No valid rows to import.");
      return;
    }
    const controller = startProgress("Importing data");
    try {
      let imported = 0;
      const failures: { row: number; message: string }[] = [];
      const total = ready.length;
      for (let start = 0; start < ready.length; start += BATCH_SIZE) {
        if (controller.signal.aborted) {
          break;
        }
        const slice = ready.slice(start, start + BATCH_SIZE);
        const part = await bulkImportEApprovalMasterDataRows(
          setId,
          slice.map((row, index) => ({
            row_number: row.rowNumber,
            label: row.label,
            code: row.code || null,
            is_active: row.isActive,
            data: row.data,
            sort_order: existing.length + start + index,
          })),
          { dryRun: false, signal: controller.signal },
        );
        imported += part.created;
        failures.push(...part.errors);
        const done = Math.min(start + slice.length, total);
        setProgress({
          title: "Importing data",
          done,
          total,
          label: `Processing ${done.toLocaleString()} of ${total.toLocaleString()} records...`,
        });
      }
      setCreated(imported);
      setServerErrors(failures);
      setStep("done");
      if (imported > 0) {
        onImported();
      }
      await new Promise((resolve) => window.setTimeout(resolve, 800));
    } catch (err) {
      if (controller.signal.aborted || isCanceledRequestError(err)) {
        return;
      }
      setError(getErrorMessage(err) || "Import failed.");
    } finally {
      finishProgress(controller);
    }
  }

  const previewErrors = planned.filter((row) => row.error);

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="w-[min(calc(100vw-2rem),640px)]" showCloseButton>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Upload className="size-4 text-muted-foreground" />
              {step === "upload" && "Import rows"}
              {step === "map" && "Review column mapping"}
              {step === "preview" && "Validation preview"}
              {step === "done" && "Import complete"}
            </DialogTitle>
            <DialogDescription>
              {step === "upload"
                ? `Import CSV rows into ${setName}. Duplicate labels and codes are rejected.`
                : step === "map"
                  ? "Match each CSV column to a row field. Columns you do not need can be ignored."
                  : step === "preview"
                    ? "Nothing has been written yet."
                    : "Rows that already exist were left unchanged."}
            </DialogDescription>
          </DialogHeader>
          <DialogBody className="space-y-4">
            {error ? (
              <p className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                {error}
              </p>
            ) : null}
            {step === "upload" ? (
              <>
                <div className="rounded-lg border border-border bg-muted/40 px-3 py-2.5 text-sm text-muted-foreground">
                  <p className="font-medium text-foreground">Select a CSV file to begin.</p>
                  <p className="mt-1">Label is required. A label or code that already exists in this set is not imported.</p>
                </div>
                <div className="space-y-1.5">
                  <label className="text-xs font-medium text-muted-foreground" htmlFor={fileInputId}>
                    CSV file
                  </label>
                  <Input
                    id={fileInputId}
                    type="file"
                    accept=".csv,text/csv"
                    className="h-10 cursor-pointer"
                    onChange={(event) => {
                      setFile(event.target.files?.[0] ?? null);
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
                  <p className="text-sm text-muted-foreground">Need the column headers?</p>
                  <Button
                    type="button"
                    variant="outline"
                    className="gap-1.5"
                    onClick={() => downloadText(`${setKey}-import-template.csv`, masterDataTemplateCsv(columns))}
                  >
                    <Download className="size-3.5" />
                    Download CSV template
                  </Button>
                </div>
              </>
            ) : null}
            {step === "map" ? (
              <div className="overflow-hidden rounded-xl border border-border">
                <div className="grid grid-cols-[1fr_auto_1fr] gap-2 border-b border-border bg-muted/50 px-3 py-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                  <span>CSV header</span>
                  <span />
                  <span>Row field</span>
                </div>
                <ul className="divide-y divide-border">
                  {headers.map((header, index) => (
                    <li key={`${index}:${header}`} className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 px-3 py-2.5">
                      <span className="truncate text-sm font-medium">{header || "(blank)"}</span>
                      <ArrowRight className="size-3.5 text-muted-foreground" />
                      <Select
                        className="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
                        value={columnMap[header] ?? MASTER_DATA_IMPORT_IGNORE}
                        onChange={(event) =>
                          setColumnMap((current) => ({ ...current, [header]: event.target.value }))
                        }
                      >
                        <option value={MASTER_DATA_IMPORT_IGNORE}>Ignore this column</option>
                        {targets.map((target) => (
                          <option
                            key={target.value}
                            value={target.value}
                            disabled={mappedTargets.has(target.value) && columnMap[header] !== target.value}
                          >
                            {target.label}
                            {target.value === "label" ? " *" : ""}
                          </option>
                        ))}
                      </Select>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {step === "preview" ? (
              <div className="space-y-3">
                <p className="text-sm text-muted-foreground">
                  {validRows.length.toLocaleString()} ready. {clientErrors.length.toLocaleString()} blocked.
                </p>
                {previewErrors.length > 0 ? (
                  <ul className="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/30 p-3 text-sm text-destructive">
                    {previewErrors.slice(0, 20).map((row) => (
                      <li key={row.rowNumber}>{row.error}</li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-sm text-foreground">No duplicate or missing labels.</p>
                )}
              </div>
            ) : null}
            {step === "done" ? (
              <div className="space-y-2 text-sm">
                <p className="text-foreground">{created.toLocaleString()} row(s) added.</p>
                {serverErrors.length > 0 ? (
                  <ul className="max-h-48 space-y-1 overflow-y-auto text-destructive">
                    {serverErrors.map((item) => (
                      <li key={`${item.row}-${item.message}`}>{item.message}</li>
                    ))}
                  </ul>
                ) : null}
              </div>
            ) : null}
          </DialogBody>
          <DialogFooter>
            {step === "upload" ? (
              <Button type="button" disabled={!file || busy} onClick={() => void onAnalyze()}>
                Analyze file
              </Button>
            ) : null}
            {step === "map" ? (
              <>
                <Button type="button" variant="outline" disabled={busy} onClick={() => setStep("upload")}>
                  Back
                </Button>
                <Button type="button" disabled={busy || !labelMapped} onClick={() => void onValidate()}>
                  Validate
                </Button>
              </>
            ) : null}
            {step === "preview" ? (
              <>
                <Button type="button" variant="outline" disabled={busy} onClick={() => setStep("map")}>
                  Back
                </Button>
                <Button type="button" disabled={busy || validRows.length === 0} onClick={() => void onStartImport()}>
                  {previewErrors.length > 0 ? "Import valid rows only" : "Import rows"}
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
