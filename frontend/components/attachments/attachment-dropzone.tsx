"use client";

import { CheckCircle2, FileImage, FileText, Paperclip, Upload, X } from "lucide-react";
import { useEffect, useId, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export type AttachmentDropzoneItem = {
  id: string;
  file: File;
  /** 0–100; omit or 100 when ready / complete */
  progress?: number;
  status?: "ready" | "uploading" | "complete" | "error";
  error?: string | null;
  previewUrl?: string | null;
};

export type AttachmentDropzonePersistedItem = {
  id: string;
  fileName: string;
  sizeBytes?: number | null;
  mimeType?: string | null;
  previewUrl?: string | null;
  badge?: string;
};

type Props = {
  files: File[];
  onChange: (files: File[]) => void;
  accept?: string;
  multiple?: boolean;
  disabled?: boolean;
  maxFiles?: number;
  /** Extra slots already used (e.g. saved draft attachments). */
  occupiedSlots?: number;
  /** Already-persisted attachments shown in the same card grid (e.g. Saved on draft). */
  persistedItems?: AttachmentDropzonePersistedItem[];
  onRemovePersisted?: (id: string) => void | Promise<void>;
  removingPersistedId?: string | null;
  /** Load a blob for image thumbnails of saved attachments (PDFs are skipped). */
  fetchPersistedPreview?: (id: string, fileName: string) => Promise<Blob | null>;
  hint?: string;
  error?: string | null;
  /** Enable Ctrl/Cmd+V screenshot paste while the dropzone is focused or hovered. */
  enablePaste?: boolean;
  className?: string;
  /**
   * Optional live upload progress keyed by local file identity (`name:size:lastModified`).
   * When omitted, selected files show as ready with remove.
   */
  uploadStateByKey?: Record<
    string,
    { progress: number; status: "uploading" | "complete" | "error"; error?: string | null }
  >;
  onCancelUpload?: (fileKey: string) => void;
};

function fileKey(file: File): string {
  return `${file.name}:${file.size}:${file.lastModified}`;
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function isImageFile(fileName: string, mimeType?: string | null): boolean {
  if (mimeType?.startsWith("image/")) return true;
  return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(fileName);
}

function isPdfFile(fileName: string, mimeType?: string | null): boolean {
  if (mimeType === "application/pdf") return true;
  return /\.pdf$/i.test(fileName);
}

function isAccepted(file: File, accept?: string): boolean {
  if (!accept || accept.trim() === "" || accept === "*/*") return true;
  const tokens = accept.split(",").map((t) => t.trim().toLowerCase()).filter(Boolean);
  const name = file.name.toLowerCase();
  const type = (file.type || "").toLowerCase();
  return tokens.some((token) => {
    if (token.endsWith("/*")) {
      const prefix = token.slice(0, -1);
      return type.startsWith(prefix);
    }
    if (token.startsWith(".")) {
      return name.endsWith(token);
    }
    return type === token;
  });
}

function UploadProgressOverlay({ progress }: { progress: number }) {
  const pct = Math.min(100, Math.max(0, Math.round(progress)));
  const radius = 34;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (pct / 100) * circumference;

  return (
    <div className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 bg-slate-950/55 backdrop-blur-[1px]">
      <div className="relative h-20 w-20">
        <svg className="h-20 w-20 -rotate-90" viewBox="0 0 80 80" aria-hidden>
          <circle
            cx="40"
            cy="40"
            r={radius}
            fill="none"
            stroke="rgb(148 163 184 / 0.35)"
            strokeWidth="6"
          />
          <circle
            cx="40"
            cy="40"
            r={radius}
            fill="none"
            stroke="rgb(56 189 248)"
            strokeWidth="6"
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            className="transition-[stroke-dashoffset] duration-150 ease-out"
          />
        </svg>
        <span className="absolute inset-0 flex items-center justify-center text-sm font-semibold text-white">
          {pct}%
        </span>
      </div>
      <p className="text-[11px] font-medium text-slate-100">Uploading…</p>
    </div>
  );
}

export function AttachmentDropzone({
  files,
  onChange,
  accept,
  multiple = true,
  disabled,
  maxFiles = 20,
  occupiedSlots = 0,
  persistedItems = [],
  onRemovePersisted,
  removingPersistedId = null,
  fetchPersistedPreview,
  hint,
  error,
  enablePaste = true,
  className,
  uploadStateByKey,
  onCancelUpload,
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const zoneRef = useRef<HTMLDivElement>(null);
  const inputId = useId();
  const [dragging, setDragging] = useState(false);
  const [pasteHint, setPasteHint] = useState(false);
  const [previewUrls, setPreviewUrls] = useState<Record<string, string>>({});
  const [persistedPreviewUrls, setPersistedPreviewUrls] = useState<Record<string, string>>({});

  const remaining = Math.max(0, maxFiles - occupiedSlots - files.length);

  useEffect(() => {
    const next: Record<string, string> = {};
    for (const file of files) {
      if (file.type.startsWith("image/")) {
        next[fileKey(file)] = URL.createObjectURL(file);
      }
    }
    setPreviewUrls((prev) => {
      for (const url of Object.values(prev)) URL.revokeObjectURL(url);
      return next;
    });
    return () => {
      for (const url of Object.values(next)) URL.revokeObjectURL(url);
    };
  }, [files]);

  useEffect(() => {
    if (!fetchPersistedPreview) return;
    let cancelled = false;
    const created: string[] = [];

    const load = async () => {
      const next: Record<string, string> = {};
      for (const item of persistedItems) {
        if (item.previewUrl) continue;
        if (!isImageFile(item.fileName, item.mimeType)) continue;
        try {
          const blob = await fetchPersistedPreview(item.id, item.fileName);
          if (!blob || cancelled) continue;
          if (!blob.type.startsWith("image/") && !isImageFile(item.fileName)) continue;
          const url = URL.createObjectURL(blob);
          created.push(url);
          next[item.id] = url;
        } catch {
          // Keep placeholder when download/preview fails.
        }
      }
      if (!cancelled) {
        setPersistedPreviewUrls((prev) => {
          for (const url of Object.values(prev)) URL.revokeObjectURL(url);
          return next;
        });
      } else {
        for (const url of created) URL.revokeObjectURL(url);
      }
    };

    void load();
    return () => {
      cancelled = true;
    };
  }, [persistedItems, fetchPersistedPreview]);

  const mergeIncoming = (incoming: FileList | File[] | null) => {
    if (!incoming || disabled || remaining === 0) return;
    const list = Array.from(incoming instanceof FileList ? incoming : incoming);
    const next = [...files];
    for (const file of list) {
      if (!isAccepted(file, accept)) continue;
      if (next.some((f) => fileKey(f) === fileKey(file))) continue;
      if (occupiedSlots + next.length >= maxFiles) break;
      next.push(file);
      if (!multiple) break;
    }
    onChange(multiple ? next.slice(0, Math.max(0, maxFiles - occupiedSlots)) : next.slice(0, 1));
  };

  useEffect(() => {
    if (!enablePaste || disabled) return;

    const onPaste = (event: ClipboardEvent) => {
      const target = event.target;
      if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
        // Allow normal text paste in fields; still accept image clipboard when zone is hovered.
        if (!pasteHint) return;
      }
      const items = event.clipboardData?.items;
      if (!items) return;
      const pasted: File[] = [];
      for (const item of Array.from(items)) {
        if (item.kind !== "file") continue;
        const file = item.getAsFile();
        if (!file) continue;
        const named =
          file.name && file.name !== "image.png"
            ? file
            : new File([file], `screenshot-${Date.now()}.png`, {
                type: file.type || "image/png",
                lastModified: Date.now(),
              });
        pasted.push(named);
      }
      if (pasted.length === 0) return;
      event.preventDefault();
      mergeIncoming(pasted);
    };

    window.addEventListener("paste", onPaste);
    return () => window.removeEventListener("paste", onPaste);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- mergeIncoming closes over latest files
  }, [enablePaste, disabled, pasteHint, files, remaining, accept, multiple, maxFiles, occupiedSlots]);

  const removeAt = (index: number) => {
    onChange(files.filter((_, i) => i !== index));
  };

  return (
    <div className={cn("space-y-3", className)}>
      <div
        ref={zoneRef}
        tabIndex={0}
        role="button"
        aria-disabled={disabled || remaining === 0}
        aria-label="Drop files here, click to upload, or paste a screenshot"
        onMouseEnter={() => setPasteHint(true)}
        onMouseLeave={() => setPasteHint(false)}
        onFocus={() => setPasteHint(true)}
        onBlur={() => setPasteHint(false)}
        onClick={() => {
          if (!disabled && remaining > 0) inputRef.current?.click();
        }}
        onKeyDown={(e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            if (!disabled && remaining > 0) inputRef.current?.click();
          }
        }}
        onDragEnter={(e) => {
          e.preventDefault();
          e.stopPropagation();
          if (!disabled) setDragging(true);
        }}
        onDragOver={(e) => {
          e.preventDefault();
          e.stopPropagation();
          if (!disabled) setDragging(true);
        }}
        onDragLeave={(e) => {
          e.preventDefault();
          if (e.currentTarget === e.target) setDragging(false);
        }}
        onDrop={(e) => {
          e.preventDefault();
          e.stopPropagation();
          setDragging(false);
          mergeIncoming(e.dataTransfer.files);
        }}
        className={cn(
          "rounded-xl border border-dashed px-4 py-8 text-center transition-colors outline-none",
          "focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
          dragging
            ? "border-sky-500 bg-sky-500/5"
            : "border-border bg-muted/10 hover:border-muted-foreground/40",
          (disabled || remaining === 0) && "cursor-not-allowed opacity-60",
          !disabled && remaining > 0 && "cursor-pointer",
        )}
      >
        <Upload className="mx-auto h-8 w-8 text-muted-foreground/70" aria-hidden />
        <p className="mt-3 text-sm font-medium text-foreground">
          {remaining === 0 ? "Attachment limit reached" : "Drop files here or click to upload"}
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          {hint ??
            (enablePaste
              ? "Drag & drop, browse, or paste a screenshot (Ctrl+V / ⌘V)"
              : "Drag & drop or browse")}
        </p>
        <input
          id={inputId}
          ref={inputRef}
          type="file"
          className="sr-only"
          accept={accept}
          multiple={multiple}
          disabled={disabled || remaining === 0}
          onChange={(e) => {
            mergeIncoming(e.target.files);
            e.target.value = "";
          }}
          onClick={(e) => e.stopPropagation()}
        />
      </div>

      {error ? <p className="text-xs text-destructive">{error}</p> : null}

      {persistedItems.length > 0 || files.length > 0 ? (
        <ul className="grid gap-3 sm:grid-cols-2">
          {persistedItems.map((item) => {
            const preview = item.previewUrl ?? persistedPreviewUrls[item.id] ?? null;
            const image = isImageFile(item.fileName, item.mimeType);
            const pdf = isPdfFile(item.fileName, item.mimeType);

            return (
              <li
                key={`persisted-${item.id}`}
                className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
              >
                <div className="relative flex aspect-[4/3] items-center justify-center bg-muted/30">
                  {preview ? (
                    // eslint-disable-next-line @next/next/no-img-element -- blob/remote preview
                    <img src={preview} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <div className="flex flex-col items-center gap-1.5 text-muted-foreground">
                      {pdf ? (
                        <FileText className="h-9 w-9 text-rose-500/80" aria-hidden />
                      ) : (
                        <FileImage className="h-9 w-9 opacity-50" aria-hidden />
                      )}
                      <span className="text-[11px] font-medium uppercase tracking-wide">
                        {pdf ? "PDF" : image ? "Loading preview…" : "Document"}
                      </span>
                    </div>
                  )}
                  <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-emerald-600 px-1.5 py-0.5 text-[10px] font-medium text-white shadow-sm">
                    <CheckCircle2 className="h-3 w-3" aria-hidden />
                    {item.badge ?? "Saved on draft"}
                  </span>
                </div>
                <div className="space-y-2 p-3">
                  <div className="flex items-start gap-2">
                    <Paperclip className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium text-foreground">{item.fileName}</p>
                      <p className="text-xs text-muted-foreground">
                        {item.sizeBytes != null && item.sizeBytes > 0
                          ? formatSize(item.sizeBytes)
                          : "Saved on draft"}
                      </p>
                    </div>
                  </div>
                  {onRemovePersisted ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      className="w-full"
                      disabled={disabled || removingPersistedId === item.id}
                      onClick={(e) => {
                        e.stopPropagation();
                        void onRemovePersisted(item.id);
                      }}
                    >
                      <X className="mr-1 h-3.5 w-3.5" aria-hidden />
                      {removingPersistedId === item.id ? "Removing…" : "Remove file"}
                    </Button>
                  ) : null}
                </div>
              </li>
            );
          })}

          {files.map((file, index) => {
            const key = fileKey(file);
            const upload = uploadStateByKey?.[key];
            const status = upload?.status ?? "ready";
            const progress = upload?.progress ?? 0;
            const preview = previewUrls[key];
            const pdf = isPdfFile(file.name, file.type);

            return (
              <li
                key={key}
                className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
              >
                <div className="relative flex aspect-[4/3] items-center justify-center bg-muted/30">
                  {preview ? (
                    // eslint-disable-next-line @next/next/no-img-element -- local blob preview
                    <img src={preview} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <div className="flex flex-col items-center gap-1.5 text-muted-foreground">
                      {pdf ? (
                        <FileText className="h-9 w-9 text-rose-500/80" aria-hidden />
                      ) : (
                        <FileImage className="h-9 w-9 opacity-50" aria-hidden />
                      )}
                      <span className="text-[11px] font-medium uppercase tracking-wide">
                        {pdf ? "PDF" : "Document"}
                      </span>
                    </div>
                  )}
                  {status === "complete" ? (
                    <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-emerald-600 px-1.5 py-0.5 text-[10px] font-medium text-white shadow-sm">
                      <CheckCircle2 className="h-3 w-3" aria-hidden />
                      Uploaded
                    </span>
                  ) : null}
                  {status === "uploading" ? <UploadProgressOverlay progress={progress} /> : null}
                  {status === "ready" && !upload ? (
                    <span className="absolute left-2 top-2 rounded-md bg-slate-800/90 px-1.5 py-0.5 text-[10px] font-medium text-white shadow-sm">
                      Pending upload
                    </span>
                  ) : null}
                  {status === "error" ? (
                    <span className="absolute left-2 top-2 rounded-md bg-destructive px-1.5 py-0.5 text-[10px] font-medium text-white shadow-sm">
                      Failed
                    </span>
                  ) : null}
                </div>
                <div className="space-y-2 p-3">
                  <div className="flex items-start gap-2">
                    <Paperclip className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium text-foreground">{file.name}</p>
                      <p className="text-xs text-muted-foreground">{formatSize(file.size)}</p>
                      {upload?.error ? (
                        <p className="mt-1 text-xs text-destructive">{upload.error}</p>
                      ) : null}
                    </div>
                  </div>
                  {status === "uploading" && onCancelUpload ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      className="w-full"
                      onClick={(e) => {
                        e.stopPropagation();
                        onCancelUpload(key);
                      }}
                    >
                      Cancel upload
                    </Button>
                  ) : (
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      className="w-full"
                      disabled={disabled || status === "uploading"}
                      onClick={(e) => {
                        e.stopPropagation();
                        removeAt(index);
                      }}
                    >
                      <X className="mr-1 h-3.5 w-3.5" aria-hidden />
                      {status === "uploading" ? "Uploading…" : "Remove file"}
                    </Button>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      ) : null}
    </div>
  );
}
