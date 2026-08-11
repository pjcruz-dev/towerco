"use client";

import { useState } from "react";

import { Paperclip, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  ALLOWED_FILE_TYPE_LABELS,
  fileAcceptAttribute,
  fileMatchesAllowedTypes,
  formatFileFieldValue,
  parseFileFieldOptions,
  validateSelectedFiles,
} from "@/modules/e-approval/field-file-options";
import type { EApprovalSavedAttachmentRef } from "@/modules/e-approval/draft-attachments";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  field: EApprovalFormFieldInput;
  files: File[];
  existingAttachments?: EApprovalSavedAttachmentRef[];
  disabled?: boolean;
  onChange: (files: File[]) => void;
  onRemoveSaved?: (attachmentId: string) => void | Promise<void>;
  removingSavedId?: string | null;
};

export function EApprovalFileField({
  field,
  files,
  existingAttachments = [],
  disabled,
  onChange,
  onRemoveSaved,
  removingSavedId = null,
}: Props) {
  const [selectionError, setSelectionError] = useState<string | null>(null);
  const { allowedFileTypes, maxFiles, maxFileSizeMb, minFileSizeKb } = parseFileFieldOptions(field);
  const multiple = maxFiles > 1;
  const accept = fileAcceptAttribute(allowedFileTypes);
  const allowedLabel = allowedFileTypes.map((type) => ALLOWED_FILE_TYPE_LABELS[type]).join(", ");
  const sizeHint = [
    maxFileSizeMb != null ? `max ${maxFileSizeMb} MB per file` : null,
    minFileSizeKb != null ? `min ${minFileSizeKb} KB per file` : null,
  ]
    .filter(Boolean)
    .join(" · ");
  const savedCount = existingAttachments.length;
  const selectedCount = files.length;
  const totalCount = savedCount + selectedCount;
  const remainingSlots = Math.max(0, maxFiles - totalCount);

  const mergeFiles = (incoming: FileList | null) => {
    if (!incoming || incoming.length === 0) {
      return;
    }

    const next = [...files];
    let rejected = 0;
    for (const file of Array.from(incoming)) {
      if (!fileMatchesAllowedTypes(file, allowedFileTypes)) {
        rejected += 1;
        continue;
      }
      if (
        next.some((existing) => existing.name === file.name && existing.size === file.size) ||
        existingAttachments.some((existing) => existing.file_name === file.name)
      ) {
        continue;
      }
      if (next.length >= maxFiles) {
        break;
      }
      next.push(file);
    }

    // Cap by total maxFiles — remainingSlots is only for the input disabled state.
    const limited = next.slice(0, maxFiles);
    if (rejected > 0) {
      setSelectionError(`Some files were skipped. Allowed types: ${allowedLabel}.`);
    } else {
      const validationError = validateSelectedFiles(field, limited);
      setSelectionError(validationError);
    }
    onChange(limited);
  };

  const removeAt = (index: number) => {
    const next = files.filter((_, i) => i !== index);
    setSelectionError(validateSelectedFiles(field, next));
    onChange(next);
  };

  return (
    <div className="space-y-2">
      <input
        type="file"
        disabled={disabled || remainingSlots === 0}
        accept={accept}
        multiple={multiple}
        className={cn(
          "block w-full cursor-pointer rounded-lg border border-input bg-background px-2.5 py-2 text-sm text-foreground",
          "file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground",
          "disabled:cursor-not-allowed disabled:opacity-50",
        )}
        onChange={(event) => {
          mergeFiles(event.target.files);
          event.target.value = "";
        }}
      />
      <p className="text-xs text-muted-foreground">
        {multiple ? `Up to ${maxFiles} files` : "Single file"} · {allowedLabel}
        {sizeHint ? ` · ${sizeHint}` : ""}
      </p>
      {selectionError ? <p className="text-xs text-destructive">{selectionError}</p> : null}
      {savedCount > 0 ? (
        <ul className="space-y-1 rounded-lg border border-border/70 bg-muted/10 p-2">
          {existingAttachments.map((attachment) => (
            <li
              key={attachment.id}
              className="flex items-center justify-between gap-2 text-xs text-foreground"
            >
              <span className="flex min-w-0 items-center gap-2">
                <Paperclip className="h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden />
                <span className="truncate font-medium">{attachment.file_name}</span>
              </span>
              <span className="flex shrink-0 items-center gap-1">
                <span className="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300">
                  Saved on draft
                </span>
                {onRemoveSaved ? (
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7 text-muted-foreground hover:text-destructive"
                    disabled={disabled || removingSavedId === attachment.id}
                    aria-label={`Remove ${attachment.file_name}`}
                    onClick={() => void onRemoveSaved(attachment.id)}
                  >
                    <X className="h-3.5 w-3.5" />
                  </Button>
                ) : null}
              </span>
            </li>
          ))}
        </ul>
      ) : null}
      {selectedCount > 0 ? (
        <ul className="space-y-1 rounded-lg border border-border/70 bg-muted/10 p-2">
          {files.map((file, index) => (
            <li
              key={`${file.name}-${file.size}-${index}`}
              className="flex items-center justify-between gap-2 text-xs text-foreground"
            >
              <span className="flex min-w-0 items-center gap-2">
                <span className="truncate font-medium">{file.name}</span>
                <span className="shrink-0 text-muted-foreground">
                  ({Math.max(1, Math.round(file.size / 1024))} KB)
                </span>
              </span>
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className={cn("h-7 w-7 shrink-0 text-muted-foreground hover:text-destructive")}
                disabled={disabled}
                aria-label={`Remove ${file.name}`}
                onClick={() => removeAt(index)}
              >
                <X className="h-3.5 w-3.5" />
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
      {totalCount > 0 ? (
        <p className="text-xs text-muted-foreground">
          {savedCount > 0 && selectedCount > 0
            ? `${savedCount} saved · ${selectedCount} pending upload`
            : savedCount > 0
              ? `${savedCount} file${savedCount === 1 ? "" : "s"} saved on draft`
              : `Selected: ${formatFileFieldValue(files)}`}
        </p>
      ) : null}
    </div>
  );
}
