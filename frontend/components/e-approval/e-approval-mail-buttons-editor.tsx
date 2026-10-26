"use client";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import type { MailButton } from "@/modules/e-approval/form-mail-config";

type Props = {
  value: MailButton[] | null;
  fallback: MailButton;
  onChange: (next: MailButton[] | null) => void;
};

export function EApprovalMailButtonsEditor({ value, fallback, onChange }: Props) {
  if (value === null) {
    return (
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs text-muted-foreground">Button: {fallback.label}</p>
        <Button type="button" size="sm" variant="outline" onClick={() => onChange([{ ...fallback }])}>
          Customize buttons
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {value.map((button, index) => (
        <div key={`${button.target}-${index}`} className="grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
          <Input
            value={button.label}
            maxLength={60}
            aria-label={`Button ${index + 1} label`}
            placeholder="Button label"
            onChange={(event) => {
              const next = value.slice();
              next[index] = { ...button, label: event.target.value };
              onChange(next);
            }}
          />
          <Select
            value={button.target}
            aria-label={`Button ${index + 1} destination`}
            onChange={(event) => {
              const target = event.target.value === "open_approval" ? "open_approval" : "open_submission";
              const next = value.slice();
              next[index] = { ...button, target };
              onChange(next);
            }}
          >
            <option value="open_approval">Open approval</option>
            <option value="open_submission">Open submission</option>
          </Select>
          <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={() => onChange(value.filter((_, itemIndex) => itemIndex !== index))}
          >
            Remove
          </Button>
        </div>
      ))}
      <div className="flex flex-wrap gap-2">
        {value.length < 2 ? (
          <Button type="button" size="sm" variant="outline" onClick={() => onChange([...value, { ...fallback }])}>
            Add button
          </Button>
        ) : null}
        <Button type="button" size="sm" variant="ghost" onClick={() => onChange(null)}>
          Use standard button
        </Button>
      </div>
    </div>
  );
}
