"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  Check,
  ChevronDown,
  ChevronUp,
  Download,
  FileSpreadsheet,
  Pencil,
  X,
} from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import {
  downloadDocExtractBatchExport,
  fetchDocExtractBatch,
  removeDocExtractBatchField,
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
  DOC_EXTRACT_FIELD_TYPES,
  docExtractHtmlInputType,
  docExtractInputMode,
  formatDocExtractFieldTypeShort,
} from "@/modules/doc-extract/field-types";
import {
  isTableLikeField,
  parseDocExtractTableValue,
  stringifyDocExtractTableValue,
  type DocExtractTableData,
} from "@/modules/doc-extract/table-values";
import type { DocExtractDocument, DocExtractField, DocExtractFieldType } from "@/modules/doc-extract/types";

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

function buildDraftRow(document: DocExtractDocument, fields: DocExtractField[]): Record<string, string> {
  const row: Record<string, string> = {};
  for (const field of fields) {
    const value = document.field_values?.[field.key];
    row[field.key] = value == null ? "" : typeof value === "string" ? value : JSON.stringify(value);
  }
  return row;
}

function displayScalar(value: string): string {
  const trimmed = value.trim();
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
  const [activeStep, setActiveStep] = useState<1 | 2 | 3>(1);
  const [editingFieldIndex, setEditingFieldIndex] = useState<number | null>(null);
  const [fieldDraft, setFieldDraft] = useState<DocExtractField | null>(null);
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
      setActiveStep(2);
      customizeFocusedRef.current = true;
    } else if ((documents?.length ?? 0) > 0) {
      setActiveStep(1);
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
        const hydrateToken = `${document.status}:${JSON.stringify(document.field_values ?? {})}`;
        const existing = next[document.id];

        if (!existing) {
          next[document.id] = buildDraftRow(document, nextFields);
          hydratedRef.current[document.id] = hydrateToken;
          changed = true;
          continue;
        }

        if (hydratedRef.current[document.id] !== hydrateToken) {
          const row = { ...existing };
          let rowChanged = false;
          for (const field of nextFields) {
            const serverRaw = document.field_values?.[field.key];
            const server = serverRaw == null ? "" : typeof serverRaw === "string" ? serverRaw : JSON.stringify(serverRaw);
            const draft = row[field.key] ?? "";
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

  const scrollToStep = (step: 1 | 2 | 3) => {
    setActiveStep(step);
    const id = step === 1 ? "dx-step-files" : step === 2 ? "dx-step-customize" : "dx-step-results";
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

  const removeFieldMutation = useMutation({
    mutationFn: (fieldKey: string) => removeDocExtractBatchField(batchId, fieldKey),
    onSuccess: async () => {
      notify({ level: "success", title: "Column removed" });
      setEditingFieldIndex(null);
      setFieldDraft(null);
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batch", batchId] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not remove column", message: getErrorMessage(error) });
    },
  });

  const schemaMutation = useMutation({
    mutationFn: (nextFields: DocExtractField[]) => updateDocExtractBatchFields(batchId, nextFields),
    onSuccess: async () => {
      notify({ level: "success", title: "Columns updated" });
      setEditingFieldIndex(null);
      setFieldDraft(null);
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batch", batchId] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not update columns", message: getErrorMessage(error) });
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

  const startEditField = (index: number) => {
    const field = visibleFields[index];
    if (!field) return;
    setEditingFieldIndex(index);
    setFieldDraft({ ...field, description: field.description ?? "", columns: field.columns?.map((c) => ({ ...c })) });
    setActiveStep(2);
  };

  const commitFieldEdit = () => {
    if (editingFieldIndex === null || !fieldDraft) return;
    const label = fieldDraft.label.trim();
    if (!label) {
      notify({ level: "error", title: "Column name required" });
      return;
    }
    const next = visibleFields.map((field, index) =>
      index === editingFieldIndex
        ? {
            ...fieldDraft,
            label,
            description: fieldDraft.description?.trim() || null,
            hint: fieldDraft.hint?.trim() || null,
          }
        : field,
    );
    schemaMutation.mutate(next);
  };

  const moveField = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= visibleFields.length) return;
    if (editingFieldIndex !== null) {
      notify({ level: "error", title: "Save or cancel the open column first" });
      return;
    }
    const next = [...visibleFields];
    const [item] = next.splice(index, 1);
    if (!item) return;
    next.splice(target, 0, item);
    schemaMutation.mutate(next);
  };

  const openTableViewer = (document: DocExtractDocument, field: DocExtractField) => {
    const raw = drafts[document.id]?.[field.key] ?? "";
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
      <div className="space-y-8" data-help="dx-batch-workspace">
        <LiveProductTourHost />
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

        <div>
          <h1 className="text-2xl font-semibold text-foreground" data-help="dx-results-title">
            Extraction workspace
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {batchQuery.data?.template_name ?? (isAuto ? "Auto-detect" : "Template")} · arrange columns in step 2,
            then validate values in results
          </p>
          <ol className="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
            {(
              [
                { step: 1 as const, label: "1. Uploaded files" },
                { step: 2 as const, label: "2. Customize fields" },
                { step: 3 as const, label: "3. View results" },
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
            {/* Section 1 — files */}
            <section data-help="dx-files-section" className="space-y-3 rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-xl font-semibold text-foreground">1. Uploaded files</h2>
                  <p className="mt-1 text-sm text-muted-foreground">Documents in this extraction batch.</p>
                </div>
                <div className="flex flex-wrap gap-2 text-xs">
                  <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-muted-foreground">
                    {documents?.length ?? 0} files
                  </span>
                  {pageCount > 0 ? (
                    <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-muted-foreground">
                      {pageCount} pages
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
              <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border">
                {(documents ?? []).map((document) => (
                  <li key={document.id} className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-foreground">{document.original_filename}</p>
                      <p className="text-xs capitalize text-muted-foreground">
                        {document.status}
                        {document.page_count ? ` · ${document.page_count} page${document.page_count === 1 ? "" : "s"}` : ""}
                      </p>
                    </div>
                    {document.error_message ? (
                      <p className="max-w-xs truncate text-xs text-destructive">{document.error_message}</p>
                    ) : null}
                  </li>
                ))}
              </ul>
            </section>

            {/* Section 2 — customize */}
            <section data-help="dx-customize-section" className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-xl font-semibold text-foreground">2. Customize data to extract</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {isAuto
                      ? "Remove noisy columns from auto-detect recommendations, then save as a reusable template."
                      : "Columns come from the selected template. Edit cell values in results below."}
                  </p>
                </div>
                {canManageTemplates && isAuto && visibleFields.length > 0 ? (
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
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {visibleFields.map((field) => (
                    <div
                      key={field.key}
                      className="rounded-lg border border-border bg-background p-3"
                      data-help="dx-field-card"
                    >
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium text-foreground">{field.label}</p>
                          <p className="mt-0.5 text-xs text-muted-foreground">
                            {field.type}
                            {field.hint ? ` · hint: ${field.hint}` : ""}
                          </p>
                        </div>
                        {isAuto && canRun ? (
                          <button
                            type="button"
                            className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title={`Remove ${field.label}`}
                            disabled={removeFieldMutation.isPending}
                            onClick={() => removeFieldMutation.mutate(field.key)}
                          >
                            <X className="size-3.5" />
                          </button>
                        ) : null}
                      </div>
                      {field.type === "table" && field.columns && field.columns.length > 0 ? (
                        <p className="mt-2 text-[11px] text-muted-foreground">
                          Table · {field.columns.map((column) => column.label).join(", ")}
                        </p>
                      ) : null}
                    </div>
                  ))}
                </div>
              )}
            </section>

            {/* Section 3 — results */}
            <section data-help="dx-results-section" className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-xl font-semibold text-foreground">3. View results</h2>
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
                <thead className="border-b border-border bg-muted/30 text-xs font-medium text-muted-foreground">
                  <tr>
                    <th className="sticky left-0 z-10 bg-muted/30 px-4 py-3 whitespace-nowrap">File Name</th>
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
                        const value = drafts[document.id]?.[field.key] ?? "";
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

        <Dialog
          open={Boolean(tableViewer?.open)}
          onOpenChange={(open) => {
            if (!open) setTableViewer(null);
          }}
        >
          <DialogContent className="flex max-h-[90vh] w-[min(96vw,52rem)] max-w-none flex-col gap-0 overflow-hidden p-0 sm:max-w-none">
            <DialogHeader className="border-b border-border px-6 py-4">
              <DialogTitle className="flex flex-wrap items-center gap-2">
                {tableViewer?.field.label ?? "Table"}
                {tableViewer ? (
                  <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-xs font-normal text-muted-foreground">
                    {tableViewer.data.rows.length} rows × {tableViewer.data.columns.length} columns
                  </span>
                ) : null}
              </DialogTitle>
              <p className="mt-1 text-sm text-muted-foreground">{tableViewer?.filename}</p>
            </DialogHeader>
            <DialogBody className="min-h-0 flex-1 overflow-auto px-6 py-4">
              {tableViewer && tableViewer.data.rows.length > 0 ? (
                <div className="overflow-x-auto rounded-lg border border-border">
                  <table className="min-w-full text-left text-[13px]">
                    <thead className="border-b border-border bg-muted/30 text-xs font-medium text-muted-foreground">
                      <tr>
                        {tableViewer.data.columns.map((column) => (
                          <th key={column.key} className="px-3 py-2 whitespace-nowrap">
                            {column.label}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {tableViewer.data.rows.map((row, rowIndex) => (
                        <tr key={rowIndex} className="border-b border-border last:border-0">
                          {tableViewer.data.columns.map((column) => (
                            <td key={column.key} className="px-3 py-2 text-foreground">
                              {canRun ? (
                                <Input
                                  className="h-8"
                                  value={row[column.key] ?? ""}
                                  onChange={(event) => {
                                    const nextValue = event.target.value;
                                    setTableViewer((current) => {
                                      if (!current) return current;
                                      const rows = current.data.rows.map((entry, index) =>
                                        index === rowIndex ? { ...entry, [column.key]: nextValue } : entry,
                                      );
                                      return { ...current, data: { ...current.data, rows } };
                                    });
                                  }}
                                />
                              ) : (
                                row[column.key] || "—"
                              )}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">
                  No table rows extracted yet for this field. After OCR improves nested tables, rows will appear here.
                </p>
              )}
            </DialogBody>
            <DialogFooter className="border-t border-border px-6 py-4">
              <Button type="button" variant="outline" onClick={() => setTableViewer(null)}>
                Close
              </Button>
              {canRun && tableViewer ? (
                <Button
                  type="button"
                  disabled={saveMutation.isPending}
                  onClick={() => {
                    const encoded = stringifyDocExtractTableValue(tableViewer.data);
                    setDrafts((current) => ({
                      ...current,
                      [tableViewer.documentId]: {
                        ...(current[tableViewer.documentId] ?? {}),
                        [tableViewer.field.key]: encoded,
                      },
                    }));
                    const values = {
                      ...(drafts[tableViewer.documentId] ?? {}),
                      [tableViewer.field.key]: encoded,
                    };
                    const payload: Record<string, string | null> = {};
                    for (const field of visibleFields) {
                      const raw = values[field.key] ?? "";
                      payload[field.key] = raw.trim() === "" ? null : raw;
                    }
                    saveMutation.mutate(
                      { documentId: tableViewer.documentId, values: payload },
                      {
                        onSuccess: () => setTableViewer(null),
                      },
                    );
                  }}
                >
                  Save table
                </Button>
              ) : null}
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </PermissionGate>
  );
}
