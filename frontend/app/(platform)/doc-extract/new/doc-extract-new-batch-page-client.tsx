"use client";

import { useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMutation, useQuery } from "@tanstack/react-query";
import { ArrowLeft, FileText, Trash2, Upload } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { createDocExtractBatch, fetchDocExtractTemplates } from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

function formatBytes(size: number): string {
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

export function DocExtractNewBatchPageClient() {
  const router = useRouter();
  const notify = useNotificationStore((state) => state.push);
  const inputRef = useRef<HTMLInputElement>(null);
  const [templateId, setTemplateId] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const [dragOver, setDragOver] = useState(false);

  const templatesQuery = useQuery({
    queryKey: ["doc-extract", "templates", "published"],
    queryFn: () => fetchDocExtractTemplates({ status: "published" }),
  });

  const createMutation = useMutation({
    mutationFn: createDocExtractBatch,
    onSuccess: (batch) => {
      notify({
        level: "success",
        title: "Extraction started",
        message: templateId
          ? "Scanning with your template. Customize columns and review results next."
          : "Auto-detecting fields. Customize recommended columns, then review results.",
      });
      router.push(`/doc-extract/batches/${batch.id}`);
    },
    onError: (error) => {
      notify({ level: "error", title: "Upload failed", message: getErrorMessage(error) });
    },
  });

  const totalBytes = useMemo(() => files.reduce((sum, file) => sum + file.size, 0), [files]);

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
  };

  const removeFile = (index: number) => {
    setFiles((current) => current.filter((_, i) => i !== index));
  };

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractRun]}>
      <div className="mx-auto w-full max-w-[90rem] space-y-8" data-help="dx-new-workspace">
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
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            Upload → review files → choose a template or auto-detect → extract. Then customize columns and export
            results.
          </p>
          <ol className="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
            <li className="rounded-md border border-primary/30 bg-primary/5 px-2.5 py-1 font-medium text-foreground">
              1. Upload files
            </li>
            <li className="rounded-md border border-border bg-muted/40 px-2.5 py-1">2. Customize fields</li>
            <li className="rounded-md border border-border bg-muted/40 px-2.5 py-1">3. View results</li>
          </ol>
        </div>

        {/* Section 1 — Upload */}
        <section data-help="dx-upload-section" className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
          <div>
            <h2 className="text-xl font-semibold text-foreground">1. Upload files</h2>
            <p className="mt-1 text-sm text-muted-foreground">PDF or images (PNG, JPEG, WebP, TIFF).</p>
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
            <p className="mt-1 text-xs text-muted-foreground">Same layout works best for reusable templates</p>
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
                  <li key={`${file.name}-${file.size}-${file.lastModified}`} className="flex items-center gap-3 px-3 py-2.5">
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

          <div data-help="dx-template-select" className="border-t border-border pt-4">
            <Label htmlFor="batch-template">Template</Label>
            <Select
              id="batch-template"
              className="mt-1.5"
              value={templateId}
              onChange={(event) => setTemplateId(event.target.value)}
              disabled={templatesQuery.isLoading}
            >
              <option value="">Auto-detect fields (recommended)</option>
              {(templatesQuery.data ?? []).map((template) => (
                <option key={template.id} value={template.id}>
                  {template.name} ({template.fields.length} fields)
                </option>
              ))}
            </Select>
            <p className="mt-2 text-xs text-muted-foreground">
              Auto-detect recommends columns from OCR. Only <span className="font-medium text-foreground">published</span>{" "}
              templates appear here — drafts stay on Templates until you publish them.
            </p>
          </div>

          <div className="flex justify-end border-t border-border pt-4">
            <Button
              type="button"
              data-help="dx-extract-button"
              disabled={files.length === 0 || createMutation.isPending}
              onClick={() =>
                createMutation.mutate({
                  templateId: templateId || null,
                  files,
                })
              }
            >
              {createMutation.isPending ? "Uploading…" : "Extract data"}
            </Button>
          </div>
        </section>
      </div>
    </PermissionGate>
  );
}
