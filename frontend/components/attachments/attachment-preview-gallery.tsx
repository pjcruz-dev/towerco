"use client";

import { useEffect, useMemo, useState } from "react";
import { FileText, ImageIcon, Paperclip } from "lucide-react";

import { getErrorMessage } from "@/lib/api/error";
import { cn } from "@/lib/utils";

export type AttachmentGalleryItem = {
  id: string;
  fileName: string;
  mimeType?: string | null;
  sizeBytes?: number | null;
  /** Card title override (e.g. camera caption / slot). */
  title?: string | null;
  /** Secondary line under the title (size, geotag, timestamp). */
  subtitle?: string | null;
  /** Group photos by field / category. */
  groupKey?: string | null;
  groupLabel?: string | null;
  /** Force image card even when extension is ambiguous (camera capture). */
  preferImagePreview?: boolean;
  /** Skip blob fetch when a ready URL is already available (tours / CDN thumbs). */
  previewUrl?: string | null;
};

export function isImageAttachment(fileName: string, mimeType?: string | null): boolean {
  if (mimeType?.startsWith("image/")) return true;
  return /\.(png|jpe?g|gif|webp|bmp|heic|heif)$/i.test(fileName);
}

export function isPdfAttachment(fileName: string, mimeType?: string | null): boolean {
  if (mimeType === "application/pdf") return true;
  return /\.pdf$/i.test(fileName);
}

export function isPreviewableAttachment(fileName: string, mimeType?: string | null): boolean {
  return isImageAttachment(fileName, mimeType) || isPdfAttachment(fileName, mimeType);
}

/** Image + PDF get the large card gallery; other types stay in the compact list. */
export function isCardGalleryAttachment(item: AttachmentGalleryItem): boolean {
  return (
    Boolean(item.preferImagePreview) ||
    isImageAttachment(item.fileName, item.mimeType) ||
    isPdfAttachment(item.fileName, item.mimeType)
  );
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
    case "pdf":
      return "application/pdf";
    default:
      return null;
  }
}

export function formatAttachmentBytes(bytes: number | null | undefined): string | null {
  if (bytes == null || !Number.isFinite(bytes) || bytes < 0) return null;
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Load object URLs for image/PDF card previews via an injected blob fetcher. */
export function useAttachmentPreviewUrls(
  items: AttachmentGalleryItem[],
  fetchBlob: (id: string) => Promise<Blob>,
) {
  const [urls, setUrls] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});

  const previewIds = useMemo(
    () =>
      items
        .filter((item) => !item.previewUrl && isCardGalleryAttachment(item))
        .map((item) => item.id)
        .sort()
        .join(","),
    [items],
  );

  useEffect(() => {
    let cancelled = false;
    const createdUrls: string[] = [];

    const load = async () => {
      const previewItems = items.filter((item) => !item.previewUrl && isCardGalleryAttachment(item));
      if (previewItems.length === 0) {
        setUrls({});
        setErrors({});
        return;
      }

      const nextUrls: Record<string, string> = {};
      const nextErrors: Record<string, string> = {};

      await Promise.all(
        previewItems.map(async (item) => {
          try {
            const blob = await fetchBlob(item.id);
            const inferredMime = item.mimeType || inferMimeFromFileName(item.fileName);
            const typedBlob =
              inferredMime && (blob.type === "" || blob.type === "application/octet-stream")
                ? new Blob([blob], { type: inferredMime })
                : blob;
            const objectUrl = URL.createObjectURL(typedBlob);
            createdUrls.push(objectUrl);
            nextUrls[item.id] = objectUrl;
          } catch (error) {
            nextErrors[item.id] = getErrorMessage(error) || "Could not load preview.";
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
    // previewIds captures attachment identity for preview loads
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [previewIds, fetchBlob]);

  return { urls, errors };
}

type Props = {
  items: AttachmentGalleryItem[];
  fetchBlob: (id: string) => Promise<Blob>;
  onDownload: (item: AttachmentGalleryItem) => void | Promise<void>;
  /** Optional open/preview (e.g. stamped PDF). Falls back to opening blob in a new tab for images/PDFs. */
  onOpenPreview?: (item: AttachmentGalleryItem) => void | Promise<void>;
  openPreviewLabel?: string | ((item: AttachmentGalleryItem) => string);
  openPreviewDisabled?: boolean;
  title?: string;
  hint?: string | null;
  emptyMessage?: string;
  className?: string;
};

function PreviewMedia({
  item,
  previewUrl,
  previewError,
}: {
  item: AttachmentGalleryItem;
  previewUrl?: string;
  previewError?: string;
}) {
  const isPdf = isPdfAttachment(item.fileName, item.mimeType);
  const isImage =
    Boolean(item.preferImagePreview) || isImageAttachment(item.fileName, item.mimeType);

  if (previewUrl && isImage && !isPdf) {
    return (
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={previewUrl}
        alt={item.title || item.fileName}
        className="h-full w-full object-cover"
      />
    );
  }

  if (previewUrl && isPdf) {
    return (
      <iframe
        src={`${previewUrl}#toolbar=0&navpanes=0&scrollbar=0`}
        title={item.title || item.fileName}
        className="h-full w-full border-0 bg-white"
      />
    );
  }

  return (
    <div className="flex h-full flex-col items-center justify-center gap-2 px-3 text-center text-xs text-muted-foreground">
      {isPdf ? <FileText className="h-8 w-8 text-muted-foreground/80" aria-hidden /> : null}
      <span>{previewError ?? "Loading preview…"}</span>
    </div>
  );
}

/**
 * Shared attachment gallery used by E-Forms, Ticketing, and other modules.
 * Module-specific extras (approval stamp, GPS) are passed via title/subtitle/actions.
 */
export function AttachmentPreviewGallery({
  items,
  fetchBlob,
  onDownload,
  onOpenPreview,
  openPreviewLabel = "Open preview",
  openPreviewDisabled = false,
  title = "Attachments",
  hint,
  emptyMessage = "No attachments.",
  className,
}: Props) {
  const [downloadingId, setDownloadingId] = useState<string | null>(null);
  const { urls, errors } = useAttachmentPreviewUrls(items, fetchBlob);

  if (items.length === 0) {
    return (
      <div className={cn("rounded-lg border border-dashed border-border px-4 py-6 text-sm text-muted-foreground", className)}>
        {emptyMessage}
      </div>
    );
  }

  const cardItems = items.filter((item) => isCardGalleryAttachment(item));
  const listItems = items.filter((item) => !isCardGalleryAttachment(item));

  const cardsByGroup = new Map<string, AttachmentGalleryItem[]>();
  for (const item of cardItems) {
    const isPhoto =
      Boolean(item.preferImagePreview) || isImageAttachment(item.fileName, item.mimeType);
    const key =
      item.groupKey?.trim() ||
      (isPhoto ? "__photos__" : "__documents__");
    const list = cardsByGroup.get(key) ?? [];
    list.push(item);
    cardsByGroup.set(key, list);
  }

  const resolveOpenLabel = (item: AttachmentGalleryItem) =>
    typeof openPreviewLabel === "function" ? openPreviewLabel(item) : openPreviewLabel;

  const handleDownload = async (item: AttachmentGalleryItem) => {
    setDownloadingId(item.id);
    try {
      await onDownload(item);
    } finally {
      setDownloadingId(null);
    }
  };

  const handleOpen = async (item: AttachmentGalleryItem) => {
    if (onOpenPreview) {
      await onOpenPreview(item);
      return;
    }
    if (item.previewUrl) {
      window.open(item.previewUrl, "_blank", "noopener,noreferrer");
      return;
    }
    try {
      const blob = await fetchBlob(item.id);
      const inferredMime = item.mimeType || inferMimeFromFileName(item.fileName);
      const typedBlob =
        inferredMime && (blob.type === "" || blob.type === "application/octet-stream")
          ? new Blob([blob], { type: inferredMime })
          : blob;
      const url = URL.createObjectURL(typedBlob);
      window.open(url, "_blank", "noopener,noreferrer");
      window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch {
      // Caller can surface errors via onOpenPreview when needed.
    }
  };

  const showHeader = Boolean(title?.trim()) || Boolean(hint?.trim());

  return (
    <div className={cn("space-y-5", className)}>
      {showHeader ? (
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          {title?.trim() ? (
            <h3 className="text-sm font-medium text-foreground">{title}</h3>
          ) : (
            <span />
          )}
          {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
        </div>
      ) : null}

      {[...cardsByGroup.entries()].map(([groupKey, cards]) => {
        const isPhotoGroup =
          groupKey === "__photos__" ||
          cards.every(
            (item) =>
              Boolean(item.preferImagePreview) || isImageAttachment(item.fileName, item.mimeType),
          );
        const label =
          cards[0]?.groupLabel?.trim() ||
          (groupKey === "__photos__"
            ? "Photos"
            : groupKey === "__documents__"
              ? "Documents"
              : groupKey);
        const countLabel = isPhotoGroup
          ? `${cards.length} photo${cards.length === 1 ? "" : "s"}`
          : `${cards.length} file${cards.length === 1 ? "" : "s"}`;

        return (
          <div key={groupKey} className="space-y-3">
            <div className="flex items-center gap-2">
              {isPhotoGroup ? (
                <ImageIcon className="h-3.5 w-3.5 text-muted-foreground" aria-hidden />
              ) : (
                <FileText className="h-3.5 w-3.5 text-muted-foreground" aria-hidden />
              )}
              <h4 className="text-xs font-medium text-muted-foreground">
                {label}
                <span className="ml-1.5 font-normal">· {countLabel}</span>
              </h4>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {cards.map((item) => {
                const previewUrl = item.previewUrl || urls[item.id];
                const previewError = errors[item.id];
                const canPreview =
                  Boolean(item.previewUrl) || isPreviewableAttachment(item.fileName, item.mimeType);
                return (
                  <div
                    key={item.id}
                    className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                  >
                    <div className="relative aspect-[4/3] bg-muted/40">
                      <PreviewMedia
                        item={item}
                        previewUrl={previewUrl}
                        previewError={previewError}
                      />
                    </div>
                    <div className="space-y-1.5 p-3">
                      <p className="truncate text-sm font-medium text-foreground">
                        {item.title?.trim() || item.fileName}
                      </p>
                      {item.subtitle ? (
                        <p className="text-xs text-muted-foreground">{item.subtitle}</p>
                      ) : null}
                      <div className="flex flex-wrap gap-x-3 gap-y-1 pt-1">
                        {canPreview ? (
                          <button
                            type="button"
                            className="text-xs font-medium text-sky-700 hover:underline disabled:opacity-50 dark:text-sky-400"
                            disabled={openPreviewDisabled}
                            onClick={() => void handleOpen(item)}
                          >
                            {resolveOpenLabel(item)}
                          </button>
                        ) : null}
                        <button
                          type="button"
                          className="text-xs font-medium text-foreground hover:underline disabled:opacity-50"
                          disabled={downloadingId === item.id}
                          onClick={() => void handleDownload(item)}
                        >
                          {downloadingId === item.id ? "Downloading…" : "Download"}
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        );
      })}

      {listItems.length > 0 ? (
        <div className="space-y-3">
          {cardItems.length > 0 ? (
            <h4 className="text-xs font-medium text-muted-foreground">Other files</h4>
          ) : null}
          <div className="grid gap-3 sm:grid-cols-2">
            {listItems.map((item) => {
              const canPreview = isPreviewableAttachment(item.fileName, item.mimeType);
              return (
                <div
                  key={item.id}
                  className="flex items-start gap-3 rounded-xl border border-border bg-card p-3 shadow-sm"
                >
                  <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-sky-500/10 text-sky-700 dark:text-sky-400">
                    {canPreview ? (
                      <FileText className="h-4 w-4" aria-hidden />
                    ) : (
                      <Paperclip className="h-4 w-4" aria-hidden />
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-foreground">
                      {item.title?.trim() || item.fileName}
                    </p>
                    {item.subtitle ? (
                      <p className="mt-0.5 text-xs text-muted-foreground">{item.subtitle}</p>
                    ) : null}
                    <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                      {canPreview ? (
                        <button
                          type="button"
                          className="text-xs font-medium text-sky-700 hover:underline disabled:opacity-50 dark:text-sky-400"
                          disabled={openPreviewDisabled}
                          onClick={() => void handleOpen(item)}
                        >
                          {resolveOpenLabel(item)}
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="text-xs font-medium text-foreground hover:underline disabled:opacity-50"
                        disabled={downloadingId === item.id}
                        onClick={() => void handleDownload(item)}
                      >
                        {downloadingId === item.id ? "Downloading…" : "Download"}
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
