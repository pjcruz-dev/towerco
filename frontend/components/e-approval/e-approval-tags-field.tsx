"use client";

import { useMemo, useState } from "react";
import { X } from "lucide-react";

import { Input } from "@/components/ui/input";
import {
  parseTagSuggestions,
  parseTagsAllowCustom,
  parseTagsValue,
  serializeTagsValue,
} from "@/modules/e-approval/field-type-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
};

export function EApprovalTagsField({ field, value, onChange, disabled }: Props) {
  const [draft, setDraft] = useState("");
  const tags = useMemo(() => parseTagsValue(value), [value]);
  const suggestions = parseTagSuggestions(field);
  const allowCustom = parseTagsAllowCustom(field);

  const addTag = (tag: string) => {
    const trimmed = tag.trim();
    if (!trimmed || tags.includes(trimmed)) {
      return;
    }

    onChange(serializeTagsValue([...tags, trimmed]));
    setDraft("");
  };

  const removeTag = (tag: string) => {
    onChange(serializeTagsValue(tags.filter((t) => t !== tag)));
  };

  const unusedSuggestions = suggestions.filter((s) => !tags.includes(s));

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap gap-1.5">
        {tags.map((tag) => (
          <span
            key={tag}
            className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/40 px-2 py-0.5 text-xs font-medium"
          >
            {tag}
            {!disabled ? (
              <button type="button" className="text-muted-foreground hover:text-foreground" onClick={() => removeTag(tag)}>
                <X className="h-3 w-3" aria-label={`Remove ${tag}`} />
              </button>
            ) : null}
          </span>
        ))}
      </div>

      {allowCustom && !disabled ? (
        <Input
          value={draft}
          placeholder="Type a tag and press Enter"
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" || e.key === ",") {
              e.preventDefault();
              addTag(draft);
            }
          }}
        />
      ) : null}

      {unusedSuggestions.length > 0 ? (
        <div className="flex flex-wrap gap-1">
          {unusedSuggestions.map((s) => (
            <button
              key={s}
              type="button"
              disabled={disabled}
              className="rounded-md border border-dashed border-border px-2 py-0.5 text-xs text-muted-foreground hover:border-primary hover:text-primary"
              onClick={() => addTag(s)}
            >
              + {s}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}
