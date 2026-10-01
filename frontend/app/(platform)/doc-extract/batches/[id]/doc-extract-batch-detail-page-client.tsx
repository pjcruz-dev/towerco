"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  Check,
  ChevronDown,
  Download,
  FileSpreadsheet,
  Pencil,
  X,
} from "lucide-react";

import { DocExtractColumnsDefinitionEditor } from "@/components/doc-extract/doc-extract-columns-definition-editor";
import { DocExtractScanProgressPanel } from "@/components/doc-extract/doc-extract-scan-progress-panel";
import { DocExtractTableViewerDialog } from "@/components/doc-extract/doc-extract-table-viewer-dialog";
import { PermissionGate } from "@/components/layout/permission-gate";
import { DocExtractHelpEntryActions } from "@/components/help/doc-extract-help-entry-actions";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import {
  downloadDocExtractBatchExport,
  fetchDocExtractBatch,
  requeueDocExtractBatch,
  saveDocExtractBatchAsTemplate,
  updateDocExtractBatchFields,
  updateDocExtractDocumentFields,
} from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";
import {
  docExtractHtmlInputType,
  docExtractInputMode,
  formatDocExtractFieldTypeShort,
} from "@/modules/doc-extract/field-types";
import {
  isTableLikeField,
  parseDocExtractTableValue,
  type DocExtractTableData,
} from "@/modules/doc-extract/table-values";
import type {
  DocExtractDocument,
  DocExtractField,
  DocExtractFieldType,
  DocExtractTableColumn,
} from "@/modules/doc-extract/types";

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

function fieldValueToText(value: unknown): string {
  if (value == null) return "";
  if (typeof value === "string") return value;
  try {
    const encoded = JSON.stringify(value);
    return typeof encoded === "string" ? encoded : "";
  } catch {
    return String(value);
  }
}

function buildDraftRow(document: DocExtractDocument, fields: DocExtractField[]): Record<string, string> {
  const row: Record<string, string> = {};
  for (const field of fields) {
    row[field.key] = fieldValueToText(document.field_values?.[field.key]);
  }
  return row;
}

function tableColumnsChanged(
  previous: DocExtractTableColumn[] | undefined,
  next: DocExtractTableColumn[],
): boolean {
  const left = previous ?? [];
  if (left.length !== next.length) return true;
  return left.some((column, index) => {
    const other = next[index];
    return !other || column.key !== other.key || column.label !== other.label || column.type !== other.type;
  });
}

function displayScalar(value: string | null | undefined): string {
  const trimmed = (value ?? "").trim();
  if (!trimmed) return "—";
  if (trimmed.startsWith("{") || trimmed.startsWith("[")) {
    try {
      const parsed = JSON.parse(trimmed) as unknown;
      if (parsed && typeof parsed === "object") {
        return "—";
      }
    } catch {
      // keep raw
    }
  }
  return trimmed;
}

type TableViewerState = {
  open: boolean;
  documentId: string;
  filename: string;
  field: DocExtractField;
  data: DocExtractTableData;
};

export function DocExtractBatchDetailPageClient() {
  const params = useParams<{ id: string }>();
  const batchId = params.id;
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const scopedUser = user ? { ...user, permissions: effectivePermissions() } : null;
  const canExport = hasPermission(scopedUser, [permissions.docExtractExport]);
  const canRun = hasPermission(scopedUser, [permissions.docExtractRun]);
  const canManageTemplates = hasPermission(scopedUser, [permissions.docExtractTemplatesManage]);
  const [drafts, setDrafts] = useState<Record<string, Record<string, string>>>({});
  const [templateName, setTemplateName] = useState("");
  const [editingCell, setEditingCell] = useState<{ documentId: string; key: string } | null>(null);
  const [downloadOpen, setDownloadOpen] = useState(false);
  const [tableViewer, setTableViewer] = useState<TableViewerState | null>(null);
  const [activeStep, setActiveStep] = useState<1 | 2 | 3 | 4>(3);
  const hydratedRef = useRef<Record<string, string>>({});
  const customizeFocusedRef = useRef(false);

  const batchQuery = useQuery({
    queryKey: ["doc-extract", "batch", batchId],
    queryFn: () => fetchDocExtractBatch(batchId),
    enabled: Boolean(batchId),
    refetchInterval: (state) => {
      const status = state.state.data?.status;
      return status === "processing" || status === "pending" ? 3000 : false;
    },
  });

  const fields = batchQuery.data?.effective_fields ?? batchQuery.data?.template?.fields;
  const documents = batchQuery.data?.documents;
  const isAuto = (batchQuery.data?.mode ?? "auto") === "auto" && !batchQuery.data?.template_id;
  const visibleFields = fields ?? [];
  const canCurate = canRun;

  const fieldsKey = useMemo(
    () =>
      (fields ?? [])
        .map((field) => `${field.key}:${field.type}:${field.label}:${field.description ?? ""}`)
        .join("|"),
    [fields],
  );

  const documentsKey = useMemo(
    () =>
      (documents ?? [])
        .map((document) => `${document.id}:${document.status}:${JSON.stringify(document.field_values ?? {})}`)
        .join("|"),
    [documents],
  );

  useEffect(() => {
    if (customizeFocusedRef.current) return;
    if (visibleFields.length > 0) {
      setActiveStep(3);
      customizeFocusedRef.current = true;
    } else if ((documents?.length ?? 0) > 0) {
      setActiveStep(2);
    }
  }, [visibleFields.length, documents?.length]);

  useEffect(() => {
    const nextFields = fields ?? [];
    const nextDocuments = documents ?? [];
    if (nextDocuments.length === 0) return;

    setDrafts((current) => {
      let changed = false;
      const next = { ...current };

      for (const document of nextDocuments) {
        const hydrateToken = `${document.status}:${fieldsKey}:${JSON.stringify(document.field_values ?? {})}`;
        const existing = next[document.id];
        const missingFieldKeys =
          Boolean(existing) && nextFields.some((field) => !(field.key in (existing ?? {})));

        if (!existing) {
          next[document.id] = buildDraftRow(document, nextFields);
          hydratedRef.current[document.id] = hydrateToken;
          changed = true;
          continue;
        }

        if (hydratedRef.current[document.id] !== hydrateToken || missingFieldKeys) {
          const row = { ...existing };
          let rowChanged = false;
          for (const field of nextFields) {
            const server = fieldValueToText(document.field_values?.[field.key]);
            const draft = fieldValueToText(row[field.key]);
            if (!(field.key in row)) {
              row[field.key] = server;
              rowChanged = true;
            } else if (draft.trim() === "" && server.trim() !== "") {
              row[field.key] = server;
              rowChanged = true;
            }
          }
          hydratedRef.current[document.id] = hydrateToken;
          if (rowChanged) {
            next[document.id] = row;
            changed = true;
          }
        }
      }

      return changed ? next : current;
    });
  }, [documentsKey, fieldsKey, documents, fields]);

  const scrollToStep = (step: 1 | 2 | 3 | 4) => {
    setActiveStep(step);
    const id =
      step === 1
        ? "dx-step-upload"
        : step === 2
          ? "dx-step-consolidate"
          : step === 3
            ? "dx-step-customize"
            : "dx-step-results";
    document.getElementById(id)?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const saveMutation = useMutation({
    mutationFn: ({ documentId, values }: { documentId: string; values: Record<string, string | null> }) =>
      updateDocExtractDocumentFields(documentId, values),
    onSuccess: async () => {
      notify({ level: "success", title: "Values saved" });
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batch", batchId] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Save failed", message: getErrorMessage(error) });
    },
  });

  const exportMutation = useMutation({
    mutationFn: (format: "csv" | "xlsx") => downloadDocExtractBatchExport(batchId, format),
    onSuccess: (blob, format) => {
      downloadBlob(blob, `extraction-results-${batchId}.${format}`);
      setDownloadOpen(false);
      notify({ level: "success", title: "Download started" });
    },
    onError: (error) => {
      notify({ level: "error", title: "Export failed", message: getErrorMessage(error) });
    },
  });

  const saveTemplateMutation = useMutation({
    mutationFn: () =>
      saveDocExtractBatchAsTemplate(batchId, {
        name: templateName.trim() || `Extraction ${new Date().toLocaleDateString()}`,
        description: "Saved from extraction results columns.",
      }),
    onSuccess: async (template) => {
      notify({
        level: "success",
        title: "Template saved as draft",
        message: `"${template.name}" is draft — publish it under Templates before it appears in Extract.`,
      });
      setTemplateName("");
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "templates"] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not save template", message: getErrorMessage(error) });
    },
  });

  const schemaMutation = useMutation({
    mutationFn: (nextFields: DocExtractField[]) => updateDocExtractBatchFields(batchId, nextFields),
    onSuccess: async () => {
      notify({ level: "success", title: "Columns updated" });
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batch", batchId] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not update columns", message: getErrorMessage(error) });
    },
  });

  const requeueMutation = useMutation({
    mutationFn: () => requeueDocExtractBatch(batchId),
    onSuccess: async (result) => {
      const remapped = result.remapped ?? 0;
      const queued = result.requeued ?? 0;
      const background = result.queued === true;
      const documentCount = result.document_count ?? 0;
      notify({
        level: "success",
        title: background ? "Rescan queued" : remapped > 0 ? "Values rescanned" : "Scans requeued",
        message: background
          ? `${documentCount} document${documentCount === 1 ? "" : "s"} remapping in the background. Refresh results in a minute.`
          : remapped > 0
            ? `${remapped} document${remapped === 1 ? "" : "s"} remapped from stored OCR${queued > 0 ? `; ${queued} queued for full scan` : ""}.`
            : `${queued} document${queued === 1 ? "" : "s"} queued for scan.`,
      });
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batch", batchId] });
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batches"] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not rescan values", message: getErrorMessage(error) });
    },
  });

  const processing = useMemo(
    () => batchQuery.data?.status === "processing" || batchQuery.data?.status === "pending",
    [batchQuery.data?.status],
  );

  const readyCount = documents?.filter((document) => document.status === "ready").length ?? 0;
  const pageCount = useMemo(() => {
    return (documents ?? []).reduce((sum, document) => sum + (document.page_count ?? 0), 0);
  }, [documents]);

  const openTableViewer = (document: DocExtractDocument, field: DocExtractField) => {
    const serverRaw = document.field_values?.[field.key];
    const serverValue =
      serverRaw == null ? "" : typeof serverRaw === "string" ? serverRaw : JSON.stringify(serverRaw);
    const raw = drafts[document.id]?.[field.key] ?? serverValue;
    setTableViewer({
      open: true,
      documentId: document.id,
      filename: document.original_filename,
      field,
      data: parseDocExtractTableValue(raw, field),
    });
  };

  const saveRow = (documentId: string) => {
    const values = drafts[documentId] ?? {};
    const payload: Record<string, string | null> = {};
    for (const field of visibleFields) {
      const raw = values[field.key] ?? "";
      payload[field.key] = raw.trim() === "" ? null : raw;
    }
    saveMutation.mutate({ documentId, values: payload });
  };

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractView]}>
      <div className="w-full space-y-8" data-help="dx-batch-workspace">
        <LiveProductTourHost />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-3">
            <Button size="sm" variant="ghost" render={<Link href="/doc-extract" />}>
              <ArrowLeft className="size-4" />
              Batches
            </Button>
            {canRun ? (
              <Button size="sm" variant="outline" render={<Link href="/doc-extract/new" data-help="dx-new-extract" />}>
                New extraction
              </Button>
            ) : null}
          </div>
          <DocExtractHelpEntryActions />
        </div>

        <div>
          <h1 className="text-2xl font-semibold text-foreground" data-help="dx-results-title">
            Extraction workspace
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {batchQuery.data?.template_name ?? (isAuto ? "Auto-detect" : "Template")} · records were consolidated on
            upload; arrange columns in step 3, then validate values in results
          </p>
          <ol className="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
            {(
              [
                { step: 1 as const, label: "1. Upload files" },
                { step: 2 as const, label: "2. Consolidate" },
                { step: 3 as const, label: "3. Customize fields" },
                { step: 4 as const, label: "4. View results" },
              ] as const
            ).map((item) => (
              <li key={item.step}>
                <button
                  type="button"
                  onClick={() => scrollToStep(item.step)}
                  className={cn(
                    "rounded-md border px-2.5 py-1 transition-colors",
                    activeStep === item.step
                      ? "border-primary/30 bg-primary/5 font-medium text-foreground"
                      : "border-border bg-muted/40 hover:bg-muted",
                  )}
                >
                  {item.label}
                </button>
              </li>
            ))}
          </ol>
        </div>

        {batchQuery.isLoading ? (
          <DashboardContentSkeleton />
        ) : batchQuery.isError ? (
          <p className="text-sm text-destructive">{getErrorMessage(batchQuery.error)}</p>
        ) : (
          <>
            {/* Section 1 — upload summary */}
            <section
              id="dx-step-upload"
              data-help="dx-files-section"
              className="space-y-3 rounded-xl border border-border bg-card p-5 shadow-sm"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-xl font-semibold text-foreground">1. Upload files</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Source files for this batch. Page membership was set in Consolidate.
                  </p>
                </div>
                <div className="flex flex-wrap gap-2 text-xs">
                  <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-muted-foreground">
                    {documents?.length ?? 0} records
                  </span>
                  {pageCount > 0 ? (
                    <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-muted-foreground">
                      {pageCount} pages scanned
                    </span>
                  ) : null}
                  {processing ? (
                    <span className="inline-flex items-center gap-1 rounded-md border border-sky-200 bg-sky-50 px-2 py-0.5 text-sky-700">
                      <Spinner className="size-3" /> Scanning…
                    </span>
                  ) : (
                    <span className="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">
                      {readyCount} ready
                    </span>
                  )}
                </div>
              </div>
            </section>

            {(documents?.length ?? 0) > 0 ? (
              <DocExtractScanProgressPanel
                documents={documents ?? []}
                batchStatus={batchQuery.data?.status}
                live={processing}
                canRequeue={canRun}
                requeuePending={requeueMutation.isPending}
                onRequeue={() => requeueMutation.mutate()}
              />
            ) : null}

            {/* Section 2 — consolidated records */}
            <section
              id="dx-step-consolidate"
              data-help="dx-consolidate-section"
              className="space-y-3 rounded-xl border border-border bg-card p-5 shadow-sm"
            >
              <div>
                <h2 className="text-xl font-semibold text-foreground">2. Consolidate</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Final record layout used for extraction (one results row per record). Live scan status is above.
                </p>
              </div>
              <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border">
                {(documents ?? []).map((document) => {
                  const pages = document.source_pages?.length
                    ? document.source_pages
                    : document.source_page
                      ? [document.source_page]
                      : null;
                  return (
                    <li key={document.id} className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                      <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">{document.original_filename}</p>
                        <p className="text-xs capitalize text-muted-foreground">
                          {document.status}
                          {pages
                            ? ` · page${pages.length === 1 ? "" : "s"} ${pages.join(", ")}`
                            : document.page_count
                              ? ` · ${document.page_count} page${document.page_count === 1 ? "" : "s"}`
                              : ""}
                        </p>
                      </div>
                      {document.error_message ? (
                        <p className="max-w-xs truncate text-xs text-destructive">{document.error_message}</p>
                      ) : null}
                    </li>
                  );
                })}
              </ul>
            </section>

            {/* Section 3 — customize */}
            <section
              id="dx-step-customize"
              data-help="dx-customize-section"
              className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-xl font-semibold text-foreground">3. Customize data to extract</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Edit column name, description, and type. Drag to reorder. Save as a reusable template when ready.
                  </p>
                </div>
                {canManageTemplates && visibleFields.length > 0 ? (
                  <div className="flex flex-wrap items-center gap-2" data-help="dx-save-template">
                    <Input
                      className="h-9 w-44"
                      placeholder="Template name"
                      value={templateName}
                      onChange={(event) => setTemplateName(event.target.value)}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={saveTemplateMutation.isPending || processing}
                      onClick={() => saveTemplateMutation.mutate()}
                    >
                      Save as template
                    </Button>
                  </div>
                ) : null}
              </div>
              {visibleFields.length === 0 ? (
                <p className="rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                  {processing ? "Waiting for field recommendations…" : "No fields yet."}
                </p>
              ) : (
                <DocExtractColumnsDefinitionEditor
                  fields={visibleFields}
                  disabled={schemaMutation.isPending || processing}
                  readOnly={!canCurate}
                  onChange={(nextFields) => {
                    if (!canCurate) return;
                    schemaMutation.mutate(nextFields);
                  }}
                />
              )}
            </section>

            {/* Section 3 — results */}
            <section
              id="dx-step-results"
              data-help="dx-results-section"
              className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-xl font-semibold text-foreground">4. View results</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Click a cell to edit · validate important values before export
                  </p>
                </div>
                {canExport ? (
                  <div className="relative" data-help="dx-download">
                    <Button
                      type="button"
                      size="sm"
                      disabled={exportMutation.isPending || !batchQuery.data}
                      onClick={() => setDownloadOpen((open) => !open)}
                    >
                      <Download className="size-4" />
                      Download
                      <ChevronDown className="size-3.5 opacity-70" />
                    </Button>
                    {downloadOpen ? (
                      <div className="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-border bg-card shadow-md">
                        <button
                          type="button"
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-muted"
                          onClick={() => exportMutation.mutate("xlsx")}
                        >
                          <FileSpreadsheet className="size-4" />
                          Excel (.xlsx)
                        </button>
                        <button
                          type="button"
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-muted"
                          onClick={() => exportMutation.mutate("csv")}
                        >
                          <Download className="size-4" />
                          CSV
                        </button>
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </div>

          <div className="overflow-hidden rounded-lg border border-border">
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-[13px]">
                <thead className="border-b border-border bg-muted text-xs font-medium text-muted-foreground">
                  <tr>
                    <th className="sticky left-0 z-10 bg-muted px-4 py-3 whitespace-nowrap">File Name</th>
                    {visibleFields.map((field) => (
                      <th key={field.key} className="px-4 py-3 whitespace-nowrap">
                        <span>{field.label}</span>
                      </th>
                    ))}
                    {canRun ? <th className="px-4 py-3" /> : null}
                  </tr>
                </thead>
                <tbody>
                  {(documents ?? []).map((document) => (
                    <tr key={document.id} className="border-b border-border last:border-0 hover:bg-muted/20">
                      <td className="sticky left-0 z-10 bg-card px-4 py-3 font-medium text-foreground">
                        <div className="max-w-[16rem] truncate" title={document.original_filename}>
                          {document.original_filename}
                        </div>
                        {document.error_message ? (
                          <div className="mt-1 text-xs text-destructive">{document.error_message}</div>
                        ) : (
                          <div className="mt-0.5 text-[11px] capitalize text-muted-foreground">{document.status}</div>
                        )}
                      </td>
                      {visibleFields.map((field) => {
                        const serverValue = fieldValueToText(document.field_values?.[field.key]);
                        const value = fieldValueToText(drafts[document.id]?.[field.key] ?? serverValue);
                        const isEditing =
                          editingCell?.documentId === document.id && editingCell.key === field.key;

                        if (isTableLikeField(field)) {
                          const table = parseDocExtractTableValue(value, field);
                          return (
                            <td key={field.key} className="px-4 py-3">
                              <button
                                type="button"
                                className="inline-flex items-center gap-1.5 text-sm font-medium text-sky-700 hover:underline"
                                onClick={() => openTableViewer(document, field)}
                              >
                                <FileSpreadsheet className="size-3.5 text-emerald-600" />
                                View
                                {table.rows.length > 0 ? (
                                  <span className="text-xs font-normal text-muted-foreground">
                                    ({table.rows.length})
                                  </span>
                                ) : null}
                              </button>
                            </td>
                          );
                        }

                        return (
                          <td key={field.key} className="px-4 py-2 align-middle">
                            {isEditing && canRun ? (
                              field.type === "boolean" ? (
                                <Select
                                  className="h-8 min-w-[8rem]"
                                  autoFocus
                                  value={value}
                                  onChange={(event) =>
                                    setDrafts((current) => ({
                                      ...current,
                                      [document.id]: {
                                        ...(current[document.id] ?? {}),
                                        [field.key]: event.target.value,
                                      },
                                    }))
                                  }
                                  onBlur={() => setEditingCell(null)}
                                >
                                  <option value="">—</option>
                                  <option value="Yes">Yes</option>
                                  <option value="No">No</option>
                                </Select>
                              ) : (
                                <Input
                                  className={cn("h-8 min-w-[9rem]", field.type === "currency" && "tabular-nums")}
                                  autoFocus
                                  type={docExtractHtmlInputType(field.type as DocExtractFieldType, value)}
                                  inputMode={docExtractInputMode(field.type as DocExtractFieldType)}
                                  value={value}
                                  onChange={(event) =>
                                    setDrafts((current) => ({
                                      ...current,
                                      [document.id]: {
                                        ...(current[document.id] ?? {}),
                                        [field.key]: event.target.value,
                                      },
                                    }))
                                  }
                                  onBlur={() => setEditingCell(null)}
                                  onKeyDown={(event) => {
                                    if (event.key === "Enter" || event.key === "Escape") {
                                      setEditingCell(null);
                                    }
                                  }}
                                />
                              )
                            ) : (
                              <button
                                type="button"
                                className={cn(
                                  "group flex min-h-8 min-w-[7rem] max-w-[16rem] items-center gap-1 rounded px-1 text-left text-foreground hover:bg-muted/60",
                                  !canRun && "cursor-default hover:bg-transparent",
                                )}
                                onClick={() => {
                                  if (canRun) setEditingCell({ documentId: document.id, key: field.key });
                                }}
                              >
                                <span className="truncate">{displayScalar(value)}</span>
                                {canRun ? (
                                  <Pencil className="size-3 shrink-0 opacity-0 group-hover:opacity-40" />
                                ) : null}
                              </button>
                            )}
                          </td>
                        );
                      })}
                      {canRun ? (
                        <td className="px-4 py-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={saveMutation.isPending}
                            onClick={() => saveRow(document.id)}
                          >
                            Save
                          </Button>
                        </td>
                      ) : null}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {(documents?.length ?? 0) === 0 ? (
              <p className="p-8 text-sm text-muted-foreground">No documents in this batch.</p>
            ) : (
              <p className="border-t border-border px-4 py-2 text-right text-[11px] text-muted-foreground">
                Validate important values before export. OCR can misread characters.
              </p>
            )}
          </div>
            </section>
          </>
        )}

        <DocExtractTableViewerDialog
          open={Boolean(tableViewer?.open)}
          filename={tableViewer?.filename}
          field={tableViewer?.field ?? null}
          data={tableViewer?.data ?? null}
          canEdit={canRun}
          saving={saveMutation.isPending || schemaMutation.isPending}
          onOpenChange={(open) => {
            if (!open) setTableViewer(null);
          }}
          onChange={(nextData) => {
            setTableViewer((current) => (current ? { ...current, data: nextData } : current));
          }}
          onSave={(encoded, columns) => {
            if (!tableViewer) return;
            const documentId = tableViewer.documentId;
            const fieldKey = tableViewer.field.key;

            setDrafts((current) => ({
              ...current,
              [documentId]: {
                ...(current[documentId] ?? {}),
                [fieldKey]: encoded,
              },
            }));

            const values = {
              ...(drafts[documentId] ?? {}),
              [fieldKey]: encoded,
            };
            const payload: Record<string, string | null> = {};
            for (const field of visibleFields) {
              const raw = values[field.key] ?? "";
              payload[field.key] = raw.trim() === "" ? null : raw;
            }

            const schemaChanged = tableColumnsChanged(tableViewer.field.columns, columns);
            const persistRow = () => {
              saveMutation.mutate(
                { documentId, values: payload },
                { onSuccess: () => setTableViewer(null) },
              );
            };

            if (schemaChanged && canCurate) {
              const nextFields = visibleFields.map((field) =>
                field.key === fieldKey ? { ...field, type: "table" as const, columns } : field,
              );
              schemaMutation.mutate(nextFields, {
                onSuccess: () => persistRow(),
                onError: () => persistRow(),
              });
              return;
            }

            persistRow();
          }}
        />
      </div>
    </PermissionGate>
  );
}
