"use client";

import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  AI_CAPABILITIES,
  AI_PRESETS,
  capabilitiesForPreset,
  isAiPreset,
  resolvedAiCapabilities,
  toggleAiCapability,
  type AiPreset,
  type RoleAiAccess,
} from "@/lib/rbac/role-ai-access";
import { cn } from "@/lib/utils";

type Props = {
  value: RoleAiAccess | undefined;
  disabled?: boolean;
  saving?: boolean;
  onSave: (next: RoleAiAccess) => void;
};

export function RoleAiAccessPanel({ value, disabled, saving, onSave }: Props) {
  const preset: AiPreset = value?.preset && isAiPreset(value.preset) ? value.preset : "unrestricted";
  const capabilities = resolvedAiCapabilities({ preset, capabilities: value?.capabilities });
  const [instructions, setInstructions] = useState(value?.instructions ?? "");
  useEffect(() => {
    setInstructions(value?.instructions ?? "");
  }, [value?.instructions]);
  function savePreset(next: AiPreset) {
    onSave({
      preset: next,
      capabilities: next === "custom" ? capabilitiesForPreset("custom") : undefined,
      instructions: value?.instructions,
    });
  }

  function saveCapability(id: (typeof AI_CAPABILITIES)[number]["id"], checked: boolean) {
    onSave({
      preset: "custom",
      capabilities: toggleAiCapability({ preset, capabilities: value?.capabilities }, id, checked),
      instructions: value?.instructions,
    });
  }

  return (
    <div className="space-y-4">
      <div className="rounded-lg border border-info/30 bg-info/10 px-3 py-2 text-xs text-foreground">
        Decide what the AI assistant may do for users in this role. This is enforced on the server —
        the assistant is only given the tools allowed here, refuses anything else, and answers from
        that list. General System → AI Assistant still decides whether the role can open the assistant
        at all.
      </div>

      <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        {AI_PRESETS.map((item) => {
          const selected = preset === item.id;
          return (
            <label
              key={item.id}
              className={cn(
                "flex cursor-pointer gap-3 rounded-lg border border-border p-3",
                selected ? "border-foreground/20 bg-muted/50" : "bg-card hover:bg-muted/40",
                disabled && "cursor-default opacity-80",
              )}
            >
              <Checkbox
                checked={selected}
                disabled={disabled}
                onCheckedChange={() => {
                  if (!selected) savePreset(item.id);
                }}
                className="mt-0.5"
              />
              <span className="min-w-0">
                <span className="block text-sm font-medium text-foreground">{item.title}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{item.description}</span>
              </span>
            </label>
          );
        })}
      </div>

      <div>
        <h3 className="text-xs font-medium tracking-wide text-muted-foreground">Capabilities</h3>
        <p className="mt-1 text-xs text-muted-foreground">
          A preset fills these in. Clear or tick a box to switch this role to Custom.
        </p>
        <div className="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
          {AI_CAPABILITIES.map((cap) => (
            <label
              key={cap.id}
              className={cn(
                "flex gap-3 rounded-lg border border-border p-3",
                disabled ? "cursor-default" : "cursor-pointer hover:bg-muted/40",
              )}
            >
              <Checkbox
                checked={capabilities[cap.id]}
                disabled={disabled}
                onCheckedChange={(checked) => saveCapability(cap.id, checked === true)}
                className="mt-0.5"
              />
              <span className="min-w-0">
                <span className="block text-sm font-medium text-foreground">{cap.title}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{cap.description}</span>
              </span>
            </label>
          ))}
        </div>
      </div>

      <label className="block space-y-1.5">
        <span className="text-xs font-medium text-muted-foreground">Extra instructions for this role (optional)</span>
        <textarea
          className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          maxLength={2000}
          disabled={disabled}
          placeholder="e.g. Answer in terms of Alliance Towers finance processes. Never show vendor bank details."
          value={instructions}
          onChange={(event) => setInstructions(event.target.value)}
          onBlur={() => {
            const next = instructions.trim();
            if (next === (value?.instructions ?? "")) return;
            onSave({
              preset,
              capabilities: preset === "custom" ? capabilities : undefined,
              instructions: next || undefined,
            });
          }}
        />
      </label>

      <Button
        type="button"
        size="sm"
        disabled={disabled || saving}
        onClick={() =>
          onSave({
            preset,
            capabilities: preset === "custom" ? capabilities : undefined,
            instructions: instructions.trim() || undefined,
          })
        }
      >
        {saving ? "Saving…" : "Save AI access"}
      </Button>
    </div>
  );
}
