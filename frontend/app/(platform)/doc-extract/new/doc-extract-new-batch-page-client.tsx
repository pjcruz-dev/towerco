"use client";

import { useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMutation, useQuery } from "@tanstack/react-query";
import { ArrowLeft, FileText, Trash2, Upload } from "lucide-react";

import {
  buildDefaultConsolidateRecords,
  DocExtractConsolidateStep,
} from "@/components/doc-extract/doc-extract-consolidate-step";
import { PermissionGate } from "@/components/layout/permission-gate";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import {
  createDocExtractBatch,
  fetchDocExtractTemplates,
  previewDocExtractFiles,
} from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";
import type { DocExtractConsolidateRecord, DocExtractPreviewFile } from "@/modules/doc-extract/types";

function formatBytes(size: number): string {
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

type WizardStep = 1 | 2;

export function DocExtractNewBatchPageClient() {
  const router = useRouter();
  const notify = useNotificationStore((state) => state.push);
  const inputRef = useRef<HTMLInputElement>(null);
  const [wizardStep, setWizardStep] = useState<WizardStep>(1);
  const [templateId, setTemplateId] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const [previews, setPreviews] = useState<DocExtractPreviewFile[]>([]);
  const [records, setRecords] = useState<DocExtractConsolidateRecord[]>([]);
  const [dragOver, setDragOver] = useState(false);

  const templatesQuery = useQuery({
    queryKey: ["doc-extract", "templates", "published"],
    queryFn: () => fetchDocExtractTemplates({ status: "published" }),
  });

  const previewMutation = useMutation({
    mutationFn: previewDocExtractFiles,
    onSuccess: (nextPreviews) => {
      setPreviews(nextPreviews);
      setRecords(buildDefaultConsolidateRecords(nextPreviews));
      setWizardStep(2);
      notify({
        level: "success",
        title: "Ready to consolidate",
        message: "Review page thumbnails and group pages that belong to the same record.",
      });
    },
    onError: (error) => {
      notify({ level: "error", title: "Preview failed", message: getErrorMessage(error) });
    },
  });

  const createMutation = useMutation({
    mutationFn: createDocExtractBatch,
    onSuccess: (batch) => {
      const count = batch.document_count ?? records.length;
      notify({
        level: "success",
        title: "Extraction started",
        message: `Created ${count} record${count === 1 ? "" : "s"} from your consolidate layout. Customize columns next.`,
      });
      router.push(`/doc-extract/batches/${batch.id}`);
    },
    onError: (error) => {
      notify({ level: "error", title: "Upload failed", message: getErrorMessage(error) });
    },
  });

  const totalBytes = useMemo(() => files.reduce((sum, file) => sum + file.size, 0), [files]);
  const canExtract = records.length > 0 && records.length <= 25 && files.length > 0;

  const addFiles = (incoming: FileList | File[]) => {
    const next = Array.from(incoming).filter((file) => {
      const lower = file.name.toLowerCase();
      return (
        file.type === "application/pdf" ||
        file.type.startsWith("image/") ||
        lower.endsWith(".pdf") ||
        lower.endsWith(".png") ||
        lower.endsWith(".jpg") ||
        lower.endsWith(".jpeg") ||
        lower.endsWith(".webp") ||
        lower.endsWith(".tif") ||
        lower.endsWith(".tiff")
      );
    });
    if (next.length === 0) return;
    setFiles((current) => {
      const map = new Map(current.map((file) => [`${file.name}:${file.size}:${file.lastModified}`, file]));
      for (const file of next) {
        map.set(`${file.name}:${file.size}:${file.lastModified}`, file);
      }
      return Array.from(map.values());
    });
    // Changing files invalidates consolidate preview.
    setPreviews([]);
    setRecords([]);
    setWizardStep(1);
  };

  const removeFile = (index: number) => {
    setFiles((current) => current.filter((_, i) => i !== index));
    setPreviews([]);
    setRecords([]);
    setWizardStep(1);
  };

  const goToConsolidate = () => {
    if (files.length === 0) return;
    previewMutation.mutate(files);
  };

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractRun]}>
      <div className="w-full space-y-8" data-help="dx-new-workspace">
        <LiveProductTourHost />
        <div className="flex flex-wrap items-center gap-3">
          <Button size="sm" variant="ghost" render={<Link href="/doc-extract" data-help="dx-back-batches" />}>
            <ArrowLeft className="size-4" />
            Batches
          </Button>
        </div>

        <div>
          <h1 className="text-2xl font-semibold text-foreground" data-help="dx-new-title">
            Extract data from documents
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Upload → consolidate pages into records → extract → customize columns → view results.
          </p>
          <ol className="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
            {(
              [
                { step: 1, label: "1. Upload files", active: wizardStep === 1 },
                { step: 2, label: "2. Consolidate", active: wizardStep === 2 },
                { step: 3, label: "3. Customize fields", active: false },
                { step: 4, label: "4. View results", active: false },
              ] as const
            ).map((item) => (
              <li
                key={item.step}
                className={cn(
                  "rounded-md border px-2.5 py-1",
                  item.active
                    ? "border-primary/30 bg-primary/5 font-medium text-foreground"
                    : wizardStep > item.step
                      ? "border-border bg-muted/60 text-foreground"
                      : "border-border bg-muted/40",
                )}
              >
                {item.label}
              </li>
            ))}
          </ol>
        </div>

        {wizardStep === 1 ? (
          <section data-help="dx-upload-section" className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <div>
              <h2 className="text-xl font-semibold text-foreground">1. Upload files</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                PDF or images (PNG, JPEG, WebP, TIFF). Max 50 MB per file. Next you will arrange pages into records.
              </p>
            </div>

            <div
              className={cn(
                "flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-12 text-center transition-colors",
                dragOver ? "border-primary bg-muted/50" : "border-border bg-muted/20",
              )}
              onDragOver={(event) => {
                event.preventDefault();
                setDragOver(true);
              }}
              onDragLeave={() => setDragOver(false)}
              onDrop={(event) => {
                event.preventDefault();
                setDragOver(false);
                if (event.dataTransfer.files?.length) {
                  addFiles(event.dataTransfer.files);
                }
              }}
            >
              <Upload className="size-8 text-muted-foreground" aria-hidden />
              <p className="mt-3 text-sm font-medium text-foreground">Drop files here or browse</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Merged PDFs with multiple forms work best after Consolidate · up to 50 MB each
              </p>
              <Button
                type="button"
                size="sm"
                className="mt-4"
                data-help="dx-upload-button"
                onClick={() => inputRef.current?.click()}
              >
                Upload files
              </Button>
              <input
                ref={inputRef}
                type="file"
                multiple
                accept=".pdf,image/png,image/jpeg,image/webp,image/tiff"
                className="hidden"
                onChange={(event) => {
                  if (event.target.files?.length) {
                    addFiles(event.target.files);
                  }
                  event.target.value = "";
                }}
              />
            </div>

            <div data-help="dx-uploaded-list">
              <div className="flex items-center justify-between gap-2">
                <h3 className="text-base font-medium text-foreground">Uploaded files</h3>
                {files.length > 0 ? (
                  <p className="text-xs text-muted-foreground">
                    {files.length} file{files.length === 1 ? "" : "s"} · {formatBytes(totalBytes)}
                  </p>
                ) : null}
              </div>
              {files.length === 0 ? (
                <p className="mt-3 rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                  No files yet
                </p>
              ) : (
                <ul className="mt-3 divide-y divide-border overflow-hidden rounded-lg border border-border">
                  {files.map((file, index) => (
                    <li
                      key={`${file.name}-${file.size}-${file.lastModified}`}
                      className="flex items-center gap-3 px-3 py-2.5"
                    >
                      <FileText className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-foreground">{file.name}</p>
                        <p className="text-xs text-muted-foreground">{formatBytes(file.size)}</p>
                      </div>
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        aria-label={`Remove ${file.name}`}
                        onClick={() => removeFile(index)}
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <div className="flex justify-end border-t border-border pt-4">
              <Button
                type="button"
                data-help="dx-continue-consolidate"
                disabled={files.length === 0 || previewMutation.isPending}
                onClick={goToConsolidate}
              >
                {previewMutation.isPending ? (
                  <>
                    <Spinner className="size-4" />
                    Reading pages…
                  </>
                ) : (
                  "Continue to Consolidate"
                )}
              </Button>
            </div>
          </section>
        ) : (
          <section data-help="dx-consolidate-section" className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-xl font-semibold text-foreground">2. Consolidate</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Confirm which pages belong together. Extraction uses only these groupings — not the whole PDF unless
                  you keep a file whole.
                </p>
              </div>
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={previewMutation.isPending || createMutation.isPending}
                onClick={() => setWizardStep(1)}
              >
                Back to upload
              </Button>
            </div>

            <DocExtractConsolidateStep
              previews={previews}
              records={records}
              onChange={setRecords}
              disabled={createMutation.isPending}
            />

            <div data-help="dx-template-select" className="border-t border-border pt-4">
              <Label htmlFor="batch-template">Template</Label>
              <Select
                id="batch-template"
                className="mt-1.5"
                value={templateId}
                onChange={(event) => setTemplateId(event.target.value)}
                disabled={templatesQuery.isLoading || createMutation.isPending}
              >
                <option value="">Auto-detect fields (recommended)</option>
                {(templatesQuery.data ?? []).map((template) => (
                  <option key={template.id} value={template.id}>
                    {template.name} ({template.fields.length} fields)
                  </option>
                ))}
              </Select>
              <p className="mt-2 text-xs text-muted-foreground">
                Auto-detect recommends columns from OCR. Only{" "}
                <span className="font-medium text-foreground">published</span> templates appear here.
              </p>
            </div>

            <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
              <Button
                type="button"
                data-help="dx-extract-button"
                disabled={!canExtract || createMutation.isPending}
                onClick={() =>
                  createMutation.mutate({
                    templateId: templateId || null,
                    files,
                    records,
                    filePageCounts: Object.fromEntries(
                      previews.map((file) => [file.index, file.page_count]),
                    ),
                  })
                }
              >
                {createMutation.isPending ? (
                  <>
                    <Spinner className="size-4" />
                    Starting extraction…
                  </>
                ) : (
                  `Extract ${records.length} record${records.length === 1 ? "" : "s"}`
                )}
              </Button>
            </div>
          </section>
        )}
      </div>
    </PermissionGate>
  );
}
