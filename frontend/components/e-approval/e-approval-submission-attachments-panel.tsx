"use client";

import { useEffect, useMemo, useState } from "react";
import { ImageIcon, MapPin, Paperclip } from "lucide-react";

import { useEApprovalPdfPreview } from "@/hooks/use-e-approval-pdf-preview";
import {
  downloadEApprovalAttachment,
  downloadEApprovalAttachmentFile,
} from "@/lib/api/modules/e-approval-api";
import { useNotificationStore } from "@/stores/notification-store";
import { getErrorMessage } from "@/lib/api/error";
import { hasSignatureValue } from "@/modules/e-approval/signature";
import { cn } from "@/lib/utils";

export type EApprovalSubmissionAttachmentRow = {
  id: string;
  file_name: string;
  field_name?: string | null;
  metadata?: {
    lat?: number;
    lng?: number;
    captured_at?: string;
    caption?: string;
    slot?: string;
  } | null;
};

type Props = {
  submissionId: string;
  attachments: EApprovalSubmissionAttachmentRow[];
  /** Approved steps with signatures — used for footer hint on cards. */
  stampedApprovalCount?: number;
  title?: string;
  className?: string;
  /** Optional field labels keyed by field name (for gallery section titles). */
  fieldLabelsByName?: Record<string, string>;
};

function isImageFileName(fileName: string): boolean {
  return /\.(png|jpe?g|gif|webp|bmp|heic|heif)$/i.test(fileName);
}

function isPdfPreviewable(fileName: string): boolean {
  return /\.(pdf|png|jpe?g|gif|webp)$/i.test(fileName);
}

function inferMimeFromFileName(fileName: string): string | null {
  const ext = fileName.split(".").pop()?.toLowerCase() ?? "";
  switch (ext) {
    case "png":
      return "image/png";
    case "jpg":
    case "jpeg":
      return "image/jpeg";
    case "gif":
      return "image/gif";
    case "webp":
      return "image/webp";
    case "bmp":
      return "image/bmp";
    default:
      return null;
  }
}

function hasCameraMetadata(metadata: EApprovalSubmissionAttachmentRow["metadata"]): boolean {
  if (!metadata) return false;
  return (
    metadata.slot != null ||
    metadata.caption != null ||
    metadata.lat != null ||
    metadata.lng != null ||
    metadata.captured_at != null
  );
}

function formatGeotag(metadata: NonNullable<EApprovalSubmissionAttachmentRow["metadata"]>): string | null {
  const parts: string[] = [];
  if (metadata.lat != null && metadata.lng != null) {
    parts.push(`${Number(metadata.lat).toFixed(5)}, ${Number(metadata.lng).toFixed(5)}`);
  }
  if (metadata.captured_at) {
    const date = new Date(metadata.captured_at);
    parts.push(Number.isNaN(date.getTime()) ? metadata.captured_at : date.toLocaleString());
  }
  return parts.length > 0 ? parts.join(" · ") : null;
}

function fieldSectionTitle(
  fieldName: string | null | undefined,
  fieldLabelsByName: Record<string, string> | undefined,
): string {
  if (!fieldName) return "Other files";
  const label = fieldLabelsByName?.[fieldName]?.trim();
  return label || fieldName;
}

/** Avoid repeating the panel title (e.g. "Attachments") on every file card. */
function attachmentFieldCaption(
  fieldName: string | null | undefined,
  fieldLabelsByName: Record<string, string> | undefined,
  panelTitle: string,
): string | null {
  if (!fieldName) {
    return null;
  }
  const label = (fieldLabelsByName?.[fieldName] ?? fieldName).trim();
  if (!label) {
    return null;
  }
  const normalizedLabel = label.toLowerCase();
  const normalizedTitle = panelTitle.trim().toLowerCase();
  if (normalizedLabel === normalizedTitle) {
    return null;
  }
  if (normalizedLabel === "attachment" || normalizedLabel === "attachments" || normalizedLabel === "supporting documents") {
    return null;
  }
  return label;
}

export function countStampedApprovals(
  approvals: { status: string; approval_status?: string; signature?: string | null }[] | undefined,
): number {
  return (approvals ?? []).filter(
    (row) =>
      (row.approval_status ?? row.status).toLowerCase() === "approved" &&
      hasSignatureValue(row.signature),
  ).length;
}

function useAttachmentPreviewUrls(attachments: EApprovalSubmissionAttachmentRow[]) {
  const [urls, setUrls] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});

  const imageIds = useMemo(
    () =>
      attachments
        .filter((a) => isImageFileName(a.file_name) || hasCameraMetadata(a.metadata))
        .map((a) => a.id)
        .sort()
        .join(","),
    [attachments],
  );

  useEffect(() => {
    let cancelled = false;
    const createdUrls: string[] = [];

    const load = async () => {
      const imageAttachments = attachments.filter(
        (a) => isImageFileName(a.file_name) || hasCameraMetadata(a.metadata),
      );
      if (imageAttachments.length === 0) {
        setUrls({});
        setErrors({});
        return;
      }

      const nextUrls: Record<string, string> = {};
      const nextErrors: Record<string, string> = {};

      await Promise.all(
        imageAttachments.map(async (attachment) => {
          try {
            const blob = await downloadEApprovalAttachment(attachment.id);
            const inferredMime = inferMimeFromFileName(attachment.file_name);
            const typedBlob =
              inferredMime && (blob.type === "" || blob.type === "application/octet-stream")
                ? new Blob([blob], { type: inferredMime })
                : blob;
            const objectUrl = URL.createObjectURL(typedBlob);
            createdUrls.push(objectUrl);
            nextUrls[attachment.id] = objectUrl;
          } catch (error) {
            nextErrors[attachment.id] = getErrorMessage(error) || "Could not load preview.";
          }
        }),
      );

      if (!cancelled) {
        setUrls(nextUrls);
        setErrors(nextErrors);
      } else {
        createdUrls.forEach((url) => URL.revokeObjectURL(url));
      }
    };

    void load();

    return () => {
      cancelled = true;
      createdUrls.forEach((url) => URL.revokeObjectURL(url));
    };
    // imageIds captures attachment identity for image loads
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [imageIds]);

  return { urls, errors };
}

export function EApprovalSubmissionAttachmentsPanel({
  submissionId,
  attachments,
  stampedApprovalCount = 0,
  title = "Attachments",
  className,
  fieldLabelsByName,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const { openPdfPreview, isGenerating } = useEApprovalPdfPreview();
  const [downloadingId, setDownloadingId] = useState<string | null>(null);
  const { urls, errors } = useAttachmentPreviewUrls(attachments);

  if (attachments.length === 0) {
    return null;
  }

  const photoAttachments = attachments.filter(
    (a) => isImageFileName(a.file_name) || hasCameraMetadata(a.metadata),
  );
  const fileAttachments = attachments.filter(
    (a) => !isImageFileName(a.file_name) && !hasCameraMetadata(a.metadata),
  );

  const photosByField = new Map<string, EApprovalSubmissionAttachmentRow[]>();
  for (const attachment of photoAttachments) {
    const key = attachment.field_name?.trim() || "__other__";
    const list = photosByField.get(key) ?? [];
    list.push(attachment);
    photosByField.set(key, list);
  }

  const openSignedPdf = (attachmentId: string) => {
    void openPdfPreview(submissionId, attachmentId).catch((error) => {
      push({
        level: "error",
        title: "PDF preview failed",
        message: getErrorMessage(error) || "Could not generate PDF with approval footer.",
      });
    });
  };

  const downloadOriginal = async (attachment: EApprovalSubmissionAttachmentRow) => {
    setDownloadingId(attachment.id);
    try {
      await downloadEApprovalAttachmentFile(attachment.id, attachment.file_name);
    } catch (error) {
      push({
        level: "error",
        title: "Download failed",
        message: getErrorMessage(error) || "Could not download this file.",
      });
    } finally {
      setDownloadingId(null);
    }
  };

  return (
    <div className={cn("space-y-5", className)}>
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h3 className="text-sm font-medium text-foreground">{title}</h3>
        {stampedApprovalCount > 0 ? (
          <p className="text-xs text-muted-foreground">
            {stampedApprovalCount} approval signature{stampedApprovalCount === 1 ? "" : "s"} stamped on PDF
            preview
          </p>
        ) : (
          <p className="text-xs text-muted-foreground">
            Photos show geotag details when captured. PDF/image can open with approval footer.
          </p>
        )}
      </div>

      {[...photosByField.entries()].map(([fieldKey, photos]) => (
        <div key={fieldKey} className="space-y-3">
          <div className="flex items-center gap-2">
            <ImageIcon className="h-3.5 w-3.5 text-muted-foreground" aria-hidden />
            <h4 className="text-xs font-medium text-muted-foreground">
              {fieldSectionTitle(fieldKey === "__other__" ? null : fieldKey, fieldLabelsByName)}
              <span className="ml-1.5 font-normal">
                · {photos.length} photo{photos.length === 1 ? "" : "s"}
              </span>
            </h4>
          </div>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {photos.map((attachment) => {
              const meta = attachment.metadata;
              const geotag = meta ? formatGeotag(meta) : null;
              const previewUrl = urls[attachment.id];
              const previewError = errors[attachment.id];
              const canPreview = isPdfPreviewable(attachment.file_name);

              return (
                <div
                  key={attachment.id}
                  className="overflow-hidden rounded-lg border border-border bg-card shadow-sm"
                >
                  <div className="relative aspect-[4/3] bg-muted/40">
                    {previewUrl ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img
                        src={previewUrl}
                        alt={meta?.caption || attachment.file_name}
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      <div className="flex h-full items-center justify-center px-3 text-center text-xs text-muted-foreground">
                        {previewError ?? "Loading preview…"}
                      </div>
                    )}
                  </div>
                  <div className="space-y-1.5 p-3">
                    {meta?.slot ? (
                      <p className="text-xs font-medium text-foreground">{meta.slot}</p>
                    ) : null}
                    {meta?.caption ? (
                      <p className="text-sm text-foreground">{meta.caption}</p>
                    ) : (
                      <p className="truncate text-sm font-medium text-foreground">{attachment.file_name}</p>
                    )}
                    {geotag ? (
                      <p className="flex items-start gap-1 text-xs text-muted-foreground">
                        <MapPin className="mt-0.5 h-3 w-3 shrink-0" aria-hidden />
                        <span>{geotag}</span>
                      </p>
                    ) : null}
                    <div className="flex flex-wrap gap-x-3 gap-y-1 pt-1">
                      {canPreview ? (
                        <button
                          type="button"
                          className="text-xs font-medium text-primary hover:underline disabled:opacity-50"
                          disabled={isGenerating}
                          onClick={() => openSignedPdf(attachment.id)}
                        >
                          {stampedApprovalCount > 0 ? "Open with approval footer" : "Open preview"}
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="text-xs font-medium text-foreground hover:underline disabled:opacity-50"
                        disabled={downloadingId === attachment.id}
                        onClick={() => void downloadOriginal(attachment)}
                      >
                        {downloadingId === attachment.id ? "Downloading…" : "Download"}
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ))}

      {fileAttachments.length > 0 ? (
        <div className="space-y-3">
          {photoAttachments.length > 0 ? (
            <h4 className="text-xs font-medium text-muted-foreground">Other files</h4>
          ) : null}
          <div className="grid gap-3 sm:grid-cols-2">
            {fileAttachments.map((attachment) => {
              const canPdfPreview = isPdfPreviewable(attachment.file_name);
              const fieldCaption = attachmentFieldCaption(
                attachment.field_name,
                fieldLabelsByName,
                title,
              );
              return (
                <div
                  key={attachment.id}
                  className="flex items-start gap-3 rounded-lg border border-border bg-card p-3 shadow-sm"
                >
                  <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Paperclip className="h-4 w-4" aria-hidden />
                  </div>
                  <div className="min-w-0 flex-1">
                    {fieldCaption ? (
                      <p className="text-xs text-muted-foreground">{fieldCaption}</p>
                    ) : null}
                    <p className="truncate text-sm font-medium text-foreground">{attachment.file_name}</p>
                    <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                      {canPdfPreview ? (
                        <button
                          type="button"
                          className="text-xs font-medium text-primary hover:underline disabled:opacity-50"
                          disabled={isGenerating}
                          onClick={() => openSignedPdf(attachment.id)}
                        >
                          {stampedApprovalCount > 0 ? "Open with approval footer" : "Open preview"}
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="text-xs font-medium text-foreground hover:underline disabled:opacity-50"
                        disabled={downloadingId === attachment.id}
                        onClick={() => void downloadOriginal(attachment)}
                      >
                        {downloadingId === attachment.id ? "Downloading…" : "Download"}
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ) : null}
    </div>
  );
}
