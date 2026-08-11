"use client";

import { Plus, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  parseCameraFieldOptions,
  patchCameraFieldOptions,
  type CameraCaptureMode,
} from "@/modules/e-approval/field-camera-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  onChange: (options: Record<string, unknown>) => void;
};

export function EApprovalFieldCameraOptionsEditor({ field, onChange }: Props) {
  const options = parseCameraFieldOptions(field);

  const update = (patch: Parameters<typeof patchCameraFieldOptions>[1]) => {
    onChange(patchCameraFieldOptions(field, patch));
  };

  const addSlot = () => {
    update({ slots: [...options.slots, `Slot ${options.slots.length + 1}`] });
  };

  const updateSlot = (index: number, value: string) => {
    const next = [...options.slots];
    next[index] = value;
    update({ slots: next });
  };

  const removeSlot = (index: number) => {
    update({ slots: options.slots.filter((_, i) => i !== index) });
  };

  return (
    <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
      <p className="text-xs font-medium text-foreground">Camera / photo options</p>

      <div className="space-y-1">
        <Label htmlFor="ea-camera-mode">Capture source</Label>
        <select
          id="ea-camera-mode"
          className="flex h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
          value={options.capture_mode}
          onChange={(e) => update({ capture_mode: e.target.value as CameraCaptureMode })}
        >
          <option value="camera">Camera only (mobile rear camera)</option>
          <option value="camera_or_gallery">Camera or gallery</option>
        </select>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="space-y-1">
          <Label htmlFor="ea-camera-min">Minimum photos</Label>
          <Input
            id="ea-camera-min"
            type="number"
            min={0}
            max={50}
            value={options.min}
            onChange={(e) => {
              const n = Number(e.target.value);
              update({ min: Number.isFinite(n) ? n : 0 });
            }}
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor="ea-camera-max">Maximum photos</Label>
          <Input
            id="ea-camera-max"
            type="number"
            min={1}
            max={50}
            value={options.max}
            onChange={(e) => {
              const n = Number(e.target.value);
              update({ max: Number.isFinite(n) ? n : 12 });
            }}
          />
        </div>
      </div>

      <label className="flex items-center gap-2 text-sm">
        <Checkbox
          checked={options.geotag}
          onCheckedChange={(v) => update({ geotag: v === true })}
          className="size-4"
        />
        Capture GPS + timestamp with each photo
      </label>

      <label className="flex items-center gap-2 text-sm">
        <Checkbox
          checked={options.caption}
          onCheckedChange={(v) => update({ caption: v === true })}
          className="size-4"
        />
        Allow a caption / note per photo
      </label>

      <div className="space-y-2">
        <div className="flex items-center justify-between gap-2">
          <Label>Named capture slots</Label>
          <Button type="button" size="sm" variant="outline" onClick={addSlot}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add slot
          </Button>
        </div>
        <p className="text-xs text-muted-foreground">
          Optional. Use for survey angles or labeled shots (e.g. 0°, 30°, Access road). Each slot needs at least one
          photo on submit.
        </p>
        {options.slots.length === 0 ? (
          <p className="text-xs text-muted-foreground">No named slots — submitters can capture any photos up to the max.</p>
        ) : (
          <ul className="space-y-2">
            {options.slots.map((slot, index) => (
              <li key={`slot-${index}`} className="flex items-center gap-2">
                <Input
                  value={slot}
                  onChange={(e) => updateSlot(index, e.target.value)}
                  placeholder={`Slot ${index + 1}`}
                />
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className="h-8 w-8 shrink-0"
                  onClick={() => removeSlot(index)}
                  aria-label={`Remove slot ${index + 1}`}
                >
                  <X className="h-4 w-4" />
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
