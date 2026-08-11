"use client";

import { useEffect, useMemo, useRef, useState } from "react";

import { Camera, MapPin, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  formatCameraFieldValue,
  parseCameraFieldOptions,
  validateCameraSelection,
  type EApprovalCameraPhotoMetadata,
} from "@/modules/e-approval/field-camera-options";
import type { EApprovalSavedAttachmentRef } from "@/modules/e-approval/draft-attachments";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

export type CameraPhotoEntry = {
  file: File;
  metadata: EApprovalCameraPhotoMetadata;
  previewUrl: string;
};

type Props = {
  field: EApprovalFormFieldInput;
  files: File[];
  metadataByName?: Record<string, EApprovalCameraPhotoMetadata>;
  existingAttachments?: EApprovalSavedAttachmentRef[];
  disabled?: boolean;
  onChange: (files: File[], metadataByName: Record<string, EApprovalCameraPhotoMetadata>) => void;
  onRemoveSaved?: (attachmentId: string) => void | Promise<void>;
  removingSavedId?: string | null;
};

async function captureGeolocation(): Promise<{ lat: number; lng: number } | null> {
  if (typeof navigator === "undefined" || !navigator.geolocation) {
    return null;
  }

  return new Promise((resolve) => {
    // Short timeout — never block photo attach on GPS permission / desktop hang.
    const timer = window.setTimeout(() => resolve(null), 2500);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        window.clearTimeout(timer);
        resolve({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        });
      },
      () => {
        window.clearTimeout(timer);
        resolve(null);
      },
      { enableHighAccuracy: false, timeout: 2000, maximumAge: 60_000 },
    );
  });
}

export function EApprovalCameraField({
  field,
  files,
  metadataByName = {},
  existingAttachments = [],
  disabled,
  onChange,
  onRemoveSaved,
  removingSavedId = null,
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const filesRef = useRef(files);
  const metadataRef = useRef(metadataByName);
  const [selectionError, setSelectionError] = useState<string | null>(null);
  const [pendingSlot, setPendingSlot] = useState<string>("");
  const options = parseCameraFieldOptions(field);
  const remainingSlots = Math.max(0, options.max - (files.length + existingAttachments.length));

  filesRef.current = files;
  metadataRef.current = metadataByName;

  const previewEntries = useMemo(() => {
    return files.map((file) => ({
      file,
      metadata: metadataByName[file.name] ?? {},
      previewUrl: URL.createObjectURL(file),
    }));
  }, [files, metadataByName]);

  useEffect(() => {
    return () => {
      for (const entry of previewEntries) {
        URL.revokeObjectURL(entry.previewUrl);
      }
    };
  }, [previewEntries]);

  useEffect(() => {
    if (options.slots.length > 0 && !pendingSlot) {
      setPendingSlot(options.slots[0]!);
    }
  }, [options.slots, pendingSlot]);

  const emit = (nextFiles: File[], nextMeta: Record<string, EApprovalCameraPhotoMetadata>) => {
    setSelectionError(validateCameraSelection(field, nextFiles, nextMeta));
    onChange(nextFiles, nextMeta);
  };

  const mergeIncoming = (incoming: FileList | null, slotOverride?: string) => {
    if (!incoming || incoming.length === 0 || remainingSlots <= 0) {
      return;
    }

    const capturedAt = new Date().toISOString();
    const nextFiles = [...files];
    const nextMeta = { ...metadataByName };
    const addedNames: string[] = [];
    const assignSlot =
      options.slots.length > 0 ? (slotOverride?.trim() || pendingSlot || options.slots[0] || "") : "";

    for (const file of Array.from(incoming)) {
      if (!file.type.startsWith("image/") && !/\.(jpe?g|png|webp|gif)$/i.test(file.name)) {
        continue;
      }
      if (
        nextFiles.some((existing) => existing.name === file.name && existing.size === file.size) ||
        existingAttachments.some((existing) => existing.file_name === file.name)
      ) {
        continue;
      }
      if (nextFiles.length >= options.max - existingAttachments.length) {
        break;
      }

      nextFiles.push(file);
      addedNames.push(file.name);
      nextMeta[file.name] = {
        captured_at: capturedAt,
        ...(assignSlot ? { slot: assignSlot } : {}),
        ...(options.caption ? { caption: "" } : {}),
      };
    }

    if (addedNames.length === 0) {
      setSelectionError("No new image was added. Choose a JPEG/PNG/WebP file.");
      return;
    }

    // Attach photos immediately — do not wait on GPS (desktop permission prompts can hang).
    emit(nextFiles, nextMeta);

    if (!options.geotag) {
      return;
    }

    void captureGeolocation().then((geo) => {
      if (!geo) {
        return;
      }
      const currentFiles = filesRef.current;
      const currentMeta = { ...metadataRef.current };
      let changed = false;
      for (const name of addedNames) {
        if (!currentFiles.some((file) => file.name === name)) {
          continue;
        }
        currentMeta[name] = {
          ...(currentMeta[name] ?? {}),
          lat: geo.lat,
          lng: geo.lng,
        };
        changed = true;
      }
      if (changed) {
        emit(currentFiles, currentMeta);
      }
    });
  };

  const removeAt = (index: number) => {
    const removed = files[index];
    const nextFiles = files.filter((_, i) => i !== index);
    const nextMeta = { ...metadataByName };
    if (removed) {
      delete nextMeta[removed.name];
    }
    emit(nextFiles, nextMeta);
  };

  const patchMeta = (fileName: string, patch: Partial<EApprovalCameraPhotoMetadata>) => {
    emit(files, {
      ...metadataByName,
      [fileName]: { ...(metadataByName[fileName] ?? {}), ...patch },
    });
  };

  const entryBySlot = useMemo(() => {
    const map = new Map<string, { entry: (typeof previewEntries)[number]; index: number }>();
    previewEntries.forEach((entry, index) => {
      const slot = entry.metadata.slot?.trim();
      if (slot) {
        map.set(slot, { entry, index });
      }
    });
    return map;
  }, [previewEntries]);

  const pendingSlotRef = useRef(pendingSlot);
  pendingSlotRef.current = pendingSlot;

  const openPickerForSlot = (slot: string) => {
    setPendingSlot(slot);
    pendingSlotRef.current = slot;
    window.setTimeout(() => inputRef.current?.click(), 0);
  };

  const hasSlotGrid = options.slots.length > 0;

  return (
    <div className="space-y-3">
      {!hasSlotGrid ? (
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={disabled || remainingSlots === 0}
            onClick={() => inputRef.current?.click()}
          >
            <Camera className="mr-1.5 h-4 w-4" />
            {options.capture_mode === "camera" ? "Take photo" : "Take / choose photo"}
          </Button>
          <p className="text-xs text-muted-foreground">
            {files.length + existingAttachments.length}/{options.max} photo(s)
            {options.min > 0 ? ` · min ${options.min}` : ""}
            {options.geotag ? " · GPS on" : ""}
          </p>
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">
          {files.length + existingAttachments.length}/{options.max} photo(s)
          {options.min > 0 ? ` · min ${options.min}` : ""}
          {options.geotag ? " · GPS on" : ""}
          {" · assign one photo per angle"}
        </p>
      )}

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        capture={options.capture_mode === "camera" ? "environment" : undefined}
        multiple={!hasSlotGrid && options.max > 1}
        className="hidden"
        disabled={disabled || remainingSlots === 0}
        onChange={(event) => {
          mergeIncoming(event.target.files, pendingSlotRef.current);
          event.target.value = "";
        }}
      />

      {selectionError ? <p className="text-xs text-destructive">{selectionError}</p> : null}

      {existingAttachments.length > 0 ? (
        <ul className="space-y-1 rounded-lg border border-border/70 bg-muted/10 p-2">
          {existingAttachments.map((attachment) => (
            <li key={attachment.id} className="flex items-center justify-between gap-2 text-xs">
              <span className="truncate">{attachment.file_name}</span>
              {onRemoveSaved ? (
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className="h-7 w-7"
                  disabled={removingSavedId === attachment.id}
                  onClick={() => void onRemoveSaved(attachment.id)}
                >
                  <X className="h-3.5 w-3.5" />
                </Button>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}

      {hasSlotGrid ? (
        <ul className="grid gap-3 sm:grid-cols-2">
          {options.slots.map((slot) => {
            const matched = entryBySlot.get(slot);
            if (matched) {
              const { entry, index } = matched;
              return (
                <li
                  key={slot}
                  className="overflow-hidden rounded-lg border border-border bg-card"
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={entry.previewUrl}
                    alt={entry.file.name}
                    className="h-36 w-full object-cover"
                  />
                  <div className="space-y-2 p-2">
                    <div className="flex items-start justify-between gap-2">
                      <p className="text-xs font-medium text-foreground">{slot}</p>
                      <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="h-7 w-7 shrink-0"
                        disabled={disabled}
                        onClick={() => removeAt(index)}
                      >
                        <X className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                    {entry.metadata.lat != null && entry.metadata.lng != null ? (
                      <p className="flex items-center gap-1 text-[11px] text-muted-foreground">
                        <MapPin className="h-3 w-3" />
                        {entry.metadata.lat.toFixed(5)}, {entry.metadata.lng.toFixed(5)}
                        {entry.metadata.captured_at
                          ? ` · ${new Date(entry.metadata.captured_at).toLocaleString()}`
                          : ""}
                      </p>
                    ) : null}
                    {options.caption ? (
                      <Input
                        disabled={disabled}
                        value={entry.metadata.caption ?? ""}
                        placeholder="Caption / note"
                        onChange={(e) => patchMeta(entry.file.name, { caption: e.target.value })}
                        className={cn("h-8 text-xs")}
                      />
                    ) : null}
                  </div>
                </li>
              );
            }

            return (
              <li
                key={slot}
                className="flex min-h-[11rem] flex-col overflow-hidden rounded-lg border border-dashed border-border bg-muted/10"
              >
                <button
                  type="button"
                  disabled={disabled || remainingSlots === 0}
                  onClick={() => openPickerForSlot(slot)}
                  className="flex flex-1 flex-col items-center justify-center gap-2 px-3 py-6 text-muted-foreground transition-colors hover:bg-muted/30 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Camera className="h-5 w-5" />
                  <span className="text-xs">Add photo</span>
                </button>
                <p className="border-t border-border/70 px-2 py-2 text-center text-xs font-medium text-foreground">
                  {slot}
                </p>
              </li>
            );
          })}
        </ul>
      ) : previewEntries.length > 0 ? (
        <ul className="grid gap-3 sm:grid-cols-2">
          {previewEntries.map((entry, index) => (
            <li
              key={`${entry.file.name}-${entry.file.size}-${index}`}
              className="overflow-hidden rounded-lg border border-border bg-card"
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={entry.previewUrl}
                alt={entry.file.name}
                className="h-36 w-full object-cover"
              />
              <div className="space-y-2 p-2">
                <div className="flex items-start justify-between gap-2">
                  <p className="truncate text-xs font-medium">{entry.file.name}</p>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7 shrink-0"
                    disabled={disabled}
                    onClick={() => removeAt(index)}
                  >
                    <X className="h-3.5 w-3.5" />
                  </Button>
                </div>
                {entry.metadata.lat != null && entry.metadata.lng != null ? (
                  <p className="flex items-center gap-1 text-[11px] text-muted-foreground">
                    <MapPin className="h-3 w-3" />
                    {entry.metadata.lat.toFixed(5)}, {entry.metadata.lng.toFixed(5)}
                    {entry.metadata.captured_at
                      ? ` · ${new Date(entry.metadata.captured_at).toLocaleString()}`
                      : ""}
                  </p>
                ) : null}
                {options.caption ? (
                  <Input
                    disabled={disabled}
                    value={entry.metadata.caption ?? ""}
                    placeholder="Caption / note"
                    onChange={(e) => patchMeta(entry.file.name, { caption: e.target.value })}
                    className={cn("h-8 text-xs")}
                  />
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      ) : null}

      <p className="sr-only">{formatCameraFieldValue(files)}</p>
    </div>
  );
}
