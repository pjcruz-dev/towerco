"use client";

import { EApprovalFieldCameraOptionsEditor } from "@/components/e-approval/e-approval-field-camera-options-editor";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  ALLOWED_FILE_TYPE_LABELS,
  DEFAULT_MAX_FILE_SIZE_MB,
  E_APPROVAL_ALLOWED_FILE_TYPES,
  parseFileFieldOptions,
  patchFileFieldOptions,
} from "@/modules/e-approval/field-file-options";
import {
  parseLocationAllowGeolocation,
  parseRatingMaxStars,
  parseTagSuggestions,
  parseTagsAllowCustom,
  patchRatingOptions,
  patchTagSuggestions,
} from "@/modules/e-approval/field-type-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  onChange: (options: Record<string, unknown>) => void;
  onValidationChange?: (validation: Record<string, unknown> | null) => void;
};

export function EApprovalFieldTypeOptionsEditor({ field, onChange, onValidationChange }: Props) {
  if (field.type === "camera") {
    return <EApprovalFieldCameraOptionsEditor field={field} onChange={onChange} />;
  }

  if (field.type === "rating") {
    const max = parseRatingMaxStars(field);

    return (
      <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
        <p className="text-xs font-medium text-foreground">Rating options</p>
        <div className="space-y-1">
          <Label htmlFor="ea-rating-max">Max stars (1–10)</Label>
          <Input
            id="ea-rating-max"
            type="number"
            min={1}
            max={10}
            value={max}
            onChange={(e) => {
              const n = Number(e.target.value);
              onChange(patchRatingOptions(field, Number.isFinite(n) ? n : 5));
            }}
          />
        </div>
      </div>
    );
  }

  if (field.type === "tags") {
    const suggestions = parseTagSuggestions(field);
    const allowCustom = parseTagsAllowCustom(field);

    return (
      <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
        <p className="text-xs font-medium text-foreground">Tags options</p>
        <div className="space-y-1">
          <Label htmlFor="ea-tag-suggestions">Suggested tags (comma-separated)</Label>
          <Input
            id="ea-tag-suggestions"
            value={suggestions.join(", ")}
            onChange={(e) =>
              onChange(
                patchTagSuggestions(
                  field,
                  e.target.value.split(",").map((s) => s.trim()),
                ),
              )
            }
            placeholder="urgent, finance, hr"
          />
        </div>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={allowCustom}
            onCheckedChange={(v) => {
              const opts =
                field.options && typeof field.options === "object" && !Array.isArray(field.options)
                  ? { ...(field.options as Record<string, unknown>) }
                  : {};
              onChange({ ...opts, allow_custom: v === true });
            }}
            className="size-4"
          />
          Allow custom tags
        </label>
      </div>
    );
  }

  if (field.type === "location") {
    const allowGeo = parseLocationAllowGeolocation(field);

    return (
      <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
        <p className="text-xs font-medium text-foreground">Location options</p>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={allowGeo}
            onCheckedChange={(v) => {
              const opts =
                field.options && typeof field.options === "object" && !Array.isArray(field.options)
                  ? { ...(field.options as Record<string, unknown>) }
                  : {};
              onChange({ ...opts, allow_geolocation: v === true });
            }}
            className="size-4"
          />
          Show &quot;Use my location&quot; button
        </label>
      </div>
    );
  }

  if (field.type === "file") {
    const { allowedFileTypes, maxFiles, maxFileSizeMb, minFileSizeKb } = parseFileFieldOptions(field);

    return (
      <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
        <p className="text-xs font-medium text-foreground">File upload options</p>
        <div className="space-y-2">
          <Label>Allowed file types</Label>
          <div className="flex flex-wrap gap-3 text-sm">
            {E_APPROVAL_ALLOWED_FILE_TYPES.map((type) => (
              <label key={type} className="flex items-center gap-2">
                <Checkbox
                  checked={allowedFileTypes.includes(type)}
                  onCheckedChange={(v) => {
                    const next =
                      v === true
                        ? [...allowedFileTypes, type]
                        : allowedFileTypes.filter((item) => item !== type);
                    if (next.length === 0) {
                      return;
                    }
                    onValidationChange?.(patchFileFieldOptions(field, { allowedFileTypes: next }));
                  }}
                  className="size-4"
                />
                {ALLOWED_FILE_TYPE_LABELS[type]}
              </label>
            ))}
          </div>
        </div>
        <div className="space-y-1">
          <Label htmlFor="ea-file-max">Maximum files (1–20)</Label>
          <Input
            id="ea-file-max"
            type="number"
            min={1}
            max={20}
            value={maxFiles}
            onChange={(e) => {
              const n = Number(e.target.value);
              onValidationChange?.(
                patchFileFieldOptions(field, {
                  maxFiles: Number.isFinite(n) ? Math.min(20, Math.max(1, Math.floor(n))) : 5,
                }),
              );
            }}
          />
          <p className="text-xs text-muted-foreground">
            Set to 1 for a single file. Submitters can pick multiple files when max is greater than 1.
          </p>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1">
            <Label htmlFor="ea-file-max-mb">Max file size (MB)</Label>
            <Input
              id="ea-file-max-mb"
              type="number"
              min={0.1}
              max={25}
              step={0.1}
              value={maxFileSizeMb ?? ""}
              onChange={(e) => {
                const raw = e.target.value.trim();
                onValidationChange?.(
                  patchFileFieldOptions(field, {
                    maxFileSizeMb:
                      raw === ""
                        ? null
                        : Math.min(25, Math.max(0.1, Number(raw) || 0.1)),
                  }),
                );
              }}
              placeholder={`Platform default (${DEFAULT_MAX_FILE_SIZE_MB} MB)`}
            />
            <p className="text-xs text-muted-foreground">Leave empty to use the platform limit (25 MB).</p>
          </div>
          <div className="space-y-1">
            <Label htmlFor="ea-file-min-kb">Min file size (KB)</Label>
            <Input
              id="ea-file-min-kb"
              type="number"
              min={1}
              step={1}
              value={minFileSizeKb ?? ""}
              onChange={(e) => {
                const raw = e.target.value.trim();
                onValidationChange?.(
                  patchFileFieldOptions(field, {
                    minFileSizeKb: raw === "" ? null : Math.max(1, Math.floor(Number(raw) || 1)),
                  }),
                );
              }}
              placeholder="No minimum"
            />
            <p className="text-xs text-muted-foreground">Optional. Reject files smaller than this size.</p>
          </div>
        </div>
      </div>
    );
  }

  return null;
}
