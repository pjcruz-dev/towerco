"use client";

import { ChevronDown } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { formatAttachmentBytes, isInlineRenderableAttachment } from "@/components/attachments/attachment-preview-gallery";
import { Button } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { apiClient } from "@/lib/api/client";
import { getErrorMessage } from "@/lib/api/error";
import { getControlledRevisionDownloadInfo } from "@/lib/api/modules/controlled-documents-api";
import { downloadEApprovalAttachmentFile, downloadEApprovalAttachment } from "@/lib/api/modules/e-approval-api";
import { fetchMyRelatedRecords } from "@/lib/api/modules/me-related-records-api";
import { downloadTicketingAttachment, fetchTicketingAttachmentBlob } from "@/lib/api/modules/ticketing-api";
import type { RelatedRecord, RelatedRecordAttachment } from "@/modules/identity/related-records";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

function openBlob(blob: Blob, fileName: string, download: boolean): void {
  const url = window.URL.createObjectURL(blob);
  if (download) {
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = fileName || "attachment";
    anchor.rel = "noopener";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
    return;
  }
  window.open(url, "_blank", "noopener,noreferrer");
  window.setTimeout(() => window.URL.revokeObjectURL(url), 60_000);
}

async function loadControlledBlob(documentId: string, revisionId: string): Promise<{ blob: Blob | null; remoteUrl: string | null }> {
  const info = await getControlledRevisionDownloadInfo(documentId, revisionId);
  if (!info.stream) {
    return { blob: null, remoteUrl: info.url };
  }
  const response = await apiClient.get<Blob>(info.url, { responseType: "blob", timeout: 120_000 });
  return { blob: response.data, remoteUrl: null };
}

export function AccountRelatedRecords() {
  const push = useNotificationStore((state) => state.push);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [openModules, setOpenModules] = useState<Record<string, boolean>>({});
  const [openFiles, setOpenFiles] = useState<Record<string, boolean>>({});
  const query = useQuery({
    queryKey: ["me", "related-records"],
    queryFn: fetchMyRelatedRecords,
  });

  const runFileAction = async (
    file: RelatedRecordAttachment,
    mode: "preview" | "download",
  ) => {
    const key = `${mode}:${file.source}:${file.id}`;
    setBusyKey(key);
    try {
      if (file.source === "ticketing") {
        if (mode === "download") {
          await downloadTicketingAttachment(file.id, file.file_name);
          return;
        }
        openBlob(await fetchTicketingAttachmentBlob(file.id), file.file_name, false);
        return;
      }
      if (file.source === "e_approval") {
        if (mode === "download") {
          await downloadEApprovalAttachmentFile(file.id, file.file_name);
          return;
        }
        openBlob(await downloadEApprovalAttachment(file.id), file.file_name, false);
        return;
      }
      if (file.source === "document_register") {
        const loaded = await loadControlledBlob(file.record_id, file.id);
        if (loaded.remoteUrl) {
          window.open(loaded.remoteUrl, "_blank", "noopener,noreferrer");
          return;
        }
        if (loaded.blob) {
          openBlob(loaded.blob, file.file_name, mode === "download");
        }
      }
    } catch (error) {
      push({
        level: "error",
        title: mode === "download" ? "Download failed" : "Preview failed",
        message: getErrorMessage(error) || "Could not open this file.",
      });
    } finally {
      setBusyKey(null);
    }
  };

  if (query.isLoading) {
    return <SectionCardSkeleton fields={4} />;
  }

  if (query.isError) {
    return (
      <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        <p>Could not load related records.</p>
        <Button type="button" size="sm" variant="outline" className="mt-3" onClick={() => void query.refetch()}>
          Try again
        </Button>
      </div>
    );
  }

  const modules = query.data?.modules ?? [];
  if (modules.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-border px-4 py-6 text-sm text-muted-foreground">
        No modules you can open have related records on this profile.
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {modules.map((module) => {
        const open = openModules[module.key] === true;
        const count = module.records.length;
        return (
          <section key={module.key} className="rounded-xl border border-border bg-card shadow-sm">
            {count === 0 ? (
              <div className="px-4 py-3">
                <p className="text-base font-medium text-foreground">{module.label}</p>
                <p className="mt-0.5 text-xs text-muted-foreground">No records linked to you yet.</p>
              </div>
            ) : (
            <button
              type="button"
              className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
              aria-expanded={open}
              onClick={() =>
                setOpenModules((current) => ({ ...current, [module.key]: !current[module.key] }))
              }
            >
              <span className="min-w-0">
                <span className="text-base font-medium text-foreground">{module.label}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  {`${count} ${count === 1 ? "record" : "records"}`}
                </span>
              </span>
              <ChevronDown
                className={cn("size-4 shrink-0 text-muted-foreground transition-transform", open && "rotate-180")}
                aria-hidden
              />
            </button>
            )}
            {open && count > 0 ? (
              <ul className="divide-y divide-border border-t border-border px-4">
                {module.records.map((record) => {
                  const filesKey = `${module.key}:${record.id}`;
                  return (
                  <RecordRow
                    key={filesKey}
                    record={record}
                    filesOpen={openFiles[filesKey] === true}
                    onToggleFiles={() =>
                      setOpenFiles((current) => ({ ...current, [filesKey]: !current[filesKey] }))
                    }
                    busyKey={busyKey}
                    onFileAction={(file, mode) => void runFileAction(file, mode)}
                  />
                  );
                })}
              </ul>
            ) : null}
          </section>
        );
      })}
    </div>
  );
}

function RecordRow({
  record,
  filesOpen,
  onToggleFiles,
  busyKey,
  onFileAction,
}: {
  record: RelatedRecord;
  filesOpen: boolean;
  onToggleFiles: () => void;
  busyKey: string | null;
  onFileAction: (file: RelatedRecordAttachment, mode: "preview" | "download") => void;
}) {
  const fileCount = record.attachments.length;

  return (
    <li className="py-3">
      <div className="min-w-0">
        <Link href={record.href} className="text-sm font-medium text-foreground hover:underline">
          {record.title}
        </Link>
        {record.subtitle ? <p className="mt-0.5 text-xs text-muted-foreground">{record.subtitle}</p> : null}
      </div>
      {fileCount === 0 ? (
        <p className="mt-2 text-xs text-muted-foreground">No attachments.</p>
      ) : (
        <div className="mt-2">
          <button
            type="button"
            className="inline-flex min-h-8 items-center gap-1 text-xs font-medium text-foreground hover:underline"
            aria-expanded={filesOpen}
            onClick={onToggleFiles}
          >
            <ChevronDown className={cn("size-3.5 transition-transform", filesOpen && "rotate-180")} aria-hidden />
            {fileCount} {fileCount === 1 ? "file" : "files"}
          </button>
          {filesOpen ? (
            <ul className="mt-1.5 space-y-1.5 pl-5">
              {record.attachments.map((file) => (
                <FileRow key={file.id} file={file} busyKey={busyKey} onFileAction={onFileAction} />
              ))}
            </ul>
          ) : null}
        </div>
      )}
    </li>
  );
}

function FileRow({
  file,
  busyKey,
  onFileAction,
}: {
  file: RelatedRecordAttachment;
  busyKey: string | null;
  onFileAction: (file: RelatedRecordAttachment, mode: "preview" | "download") => void;
}) {
  const sizeLabel = formatAttachmentBytes(file.size_bytes);
  const canPreview = file.source !== "doc_extract" && isInlineRenderableAttachment(file.file_name, file.mime_type);
  const canDownload = file.source !== "doc_extract";
  const previewKey = `preview:${file.source}:${file.id}`;
  const downloadKey = `download:${file.source}:${file.id}`;

  return (
    <li className="flex flex-wrap items-center gap-x-3 gap-y-1">
      <span className="min-w-0 truncate text-sm text-foreground">{file.file_name}</span>
      {sizeLabel ? <span className="text-xs text-muted-foreground">{sizeLabel}</span> : null}
      {canPreview ? (
        <button
          type="button"
          className="inline-flex min-h-8 items-center text-xs font-medium text-sky-700 hover:underline disabled:opacity-50 dark:text-sky-400"
          disabled={busyKey === previewKey}
          onClick={() => onFileAction(file, "preview")}
        >
          {busyKey === previewKey ? "Opening…" : "Preview"}
        </button>
      ) : null}
      {canDownload ? (
        <button
          type="button"
          className="inline-flex min-h-8 items-center text-xs font-medium text-foreground hover:underline disabled:opacity-50"
          disabled={busyKey === downloadKey}
          onClick={() => onFileAction(file, "download")}
        >
          {busyKey === downloadKey ? "Downloading…" : "Download"}
        </button>
      ) : null}
    </li>
  );
}
