import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type CameraCaptureMode = "camera" | "camera_or_gallery";

export type EApprovalCameraFieldOptions = {
  capture_mode: CameraCaptureMode;
  min: number;
  max: number;
  geotag: boolean;
  caption: boolean;
  slots: string[];
};

export type EApprovalCameraPhotoMetadata = {
  lat?: number;
  lng?: number;
  captured_at?: string;
  caption?: string;
  slot?: string;
};

const DEFAULTS: EApprovalCameraFieldOptions = {
  capture_mode: "camera",
  min: 0,
  max: 12,
  geotag: true,
  caption: true,
  slots: [],
};

function normalizeOptions(field: EApprovalFormFieldInput): Record<string, unknown> {
  if (field.options && typeof field.options === "object" && !Array.isArray(field.options)) {
    return { ...(field.options as Record<string, unknown>) };
  }

  return {};
}

function normalizeSlots(raw: unknown): string[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  return [
    ...new Set(
      raw
        .map((item) => String(item ?? "").trim())
        .filter(Boolean)
        .map((slot) => slot.slice(0, 120)),
    ),
  ];
}

export function parseCameraFieldOptions(field: EApprovalFormFieldInput): EApprovalCameraFieldOptions {
  const opts = normalizeOptions(field);
  const captureMode =
    opts.capture_mode === "camera_or_gallery" ? "camera_or_gallery" : "camera";
  const minRaw = Number(opts.min);
  const maxRaw = Number(opts.max);
  const min = Number.isFinite(minRaw) ? Math.max(0, Math.min(50, Math.floor(minRaw))) : DEFAULTS.min;
  let max = Number.isFinite(maxRaw) ? Math.max(1, Math.min(50, Math.floor(maxRaw))) : DEFAULTS.max;
  if (max < Math.max(min, 1)) {
    max = Math.max(min, 1);
  }

  return {
    capture_mode: captureMode,
    min,
    max,
    geotag: opts.geotag !== false,
    caption: opts.caption !== false,
    slots: normalizeSlots(opts.slots),
  };
}

export function patchCameraFieldOptions(
  field: EApprovalFormFieldInput,
  patch: Partial<EApprovalCameraFieldOptions>,
): Record<string, unknown> {
  const current = parseCameraFieldOptions(field);
  const next: EApprovalCameraFieldOptions = {
    capture_mode: patch.capture_mode ?? current.capture_mode,
    min: patch.min !== undefined ? Math.max(0, Math.min(50, Math.floor(patch.min))) : current.min,
    max: patch.max !== undefined ? Math.max(1, Math.min(50, Math.floor(patch.max))) : current.max,
    geotag: patch.geotag !== undefined ? patch.geotag : current.geotag,
    caption: patch.caption !== undefined ? patch.caption : current.caption,
    slots: patch.slots !== undefined ? normalizeSlots(patch.slots) : current.slots,
  };

  if (next.max < Math.max(next.min, 1)) {
    next.max = Math.max(next.min, 1);
  }

  const opts = normalizeOptions(field);

  return {
    ...opts,
    capture_mode: next.capture_mode,
    min: next.min,
    max: next.max,
    geotag: next.geotag,
    caption: next.caption,
    slots: next.slots,
  };
}

export function formatCameraFieldValue(files: File[]): string {
  return files.map((file) => file.name).join(", ");
}

export function validateCameraSelection(
  field: EApprovalFormFieldInput,
  files: File[],
  metadataByName: Record<string, EApprovalCameraPhotoMetadata> = {},
): string | null {
  const { min, max, slots } = parseCameraFieldOptions(field);
  const label = field.label?.trim() || field.name;

  if (files.length > max) {
    return `${label}: at most ${max} photo(s) allowed.`;
  }

  if (min > 0 && files.length < min) {
    return `${label}: at least ${min} photo(s) required.`;
  }

  for (const file of files) {
    if (!file.type.startsWith("image/") && !/\.(jpe?g|png|webp|gif)$/i.test(file.name)) {
      return `${label}: "${file.name}" must be an image.`;
    }
  }

  if (slots.length > 0) {
    const present = new Set(
      Object.values(metadataByName)
        .map((meta) => meta.slot?.trim())
        .filter((slot): slot is string => Boolean(slot)),
    );
    const missing = slots.filter((slot) => !present.has(slot));
    if (missing.length > 0 && files.length > 0) {
      return `${label}: missing photo(s) for ${missing.join(", ")}.`;
    }
  }

  return null;
}
