"use client";

import { useMutation } from "@tanstack/react-query";
import { useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { uploadRolloutFile } from "@/lib/api/modules/rollout-api";
import type { RolloutFileContext, RolloutMediaLink } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  context: RolloutFileContext;
  label?: string;
  accept?: string;
  multiple?: boolean;
  capture?: "environment" | "user";
  value: RolloutMediaLink[];
  onChange: (next: RolloutMediaLink[]) => void;
  disabled?: boolean;
};

export function FileUploadField({
  rolloutId,
  context,
  label = "Attachments",
  accept = "image/*,application/pdf",
  multiple = true,
  capture,
  value,
  onChange,
  disabled,
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);
  const push = useNotificationStore((state) => state.push);
  const [isDragging, setIsDragging] = useState(false);

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadRolloutFile(rolloutId, context, file),
    onSuccess: (uploaded) => {
      onChange([
        ...value,
        {
          file_id: uploaded.id,
          url: uploaded.url,
          label: uploaded.original_filename,
          mime_type: uploaded.mime_type,
        },
      ]);
    },
    onError: (error) =>
      push({ level: "error", title: "Upload failed", message: getErrorMessage(error) }),
  });

  const handleFiles = (files: FileList | null) => {
    if (!files || disabled || uploadMutation.isPending) return;
    Array.from(files).forEach((file) => uploadMutation.mutate(file));
  };

  const resolvedAccept = capture ? "image/*" : accept;

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="text-sm font-medium text-foreground">{label}</span>
        <div className="flex flex-wrap gap-2">
          {capture ? (
            <Button
              type="button"
              size="lg"
              className="min-h-11"
              disabled={disabled || uploadMutation.isPending}
              onClick={() => cameraInputRef.current?.click()}
            >
              {uploadMutation.isPending ? "Uploading…" : "Take photo"}
            </Button>
          ) : null}
          <Button
            type="button"
            size={capture ? "lg" : "sm"}
            variant="outline"
            className={capture ? "min-h-11" : undefined}
            disabled={disabled || uploadMutation.isPending}
            onClick={() => inputRef.current?.click()}
          >
            {uploadMutation.isPending ? "Uploading…" : capture ? "Choose file" : "Choose files"}
          </Button>
        </div>
      </div>

      {!capture ? (
        <div
          className={`hidden rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground transition-colors sm:block ${
            isDragging ? "border-primary bg-primary/5" : "border-border"
          }`}
          onDragOver={(e) => {
            e.preventDefault();
            setIsDragging(true);
          }}
          onDragLeave={() => setIsDragging(false)}
          onDrop={(e) => {
            e.preventDefault();
            setIsDragging(false);
            handleFiles(e.dataTransfer.files);
          }}
        >
          Drag and drop files here, or use the button above.
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">
          Use the camera on your phone to capture site evidence. Upload requires connectivity.
        </p>
      )}

      <input
        ref={inputRef}
        type="file"
        className="hidden"
        accept={resolvedAccept}
        multiple={capture ? false : multiple}
        disabled={disabled || uploadMutation.isPending}
        onChange={(e) => handleFiles(e.target.files)}
      />

      {capture ? (
        <input
          ref={cameraInputRef}
          type="file"
          className="hidden"
          accept="image/*"
          capture={capture}
          disabled={disabled || uploadMutation.isPending}
          onChange={(e) => handleFiles(e.target.files)}
        />
      ) : null}

      {value.length > 0 ? (
        <ul className="space-y-1 text-sm">
          {value.map((item) => (
            <li key={item.file_id} className="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2">
              <span className="truncate">{item.label ?? item.file_id}</span>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="min-h-11 min-w-11"
                disabled={disabled}
                onClick={() => onChange(value.filter((row) => row.file_id !== item.file_id))}
              >
                Remove
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
