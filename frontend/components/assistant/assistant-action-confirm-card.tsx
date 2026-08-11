"use client";

import { useState } from "react";
import Link from "next/link";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { AssistantProposedAction } from "@/lib/api/modules/assistant-api";

type Props = {
  proposal: AssistantProposedAction;
  pending?: boolean;
  onConfirm: (proposalId: string, payload: Record<string, unknown>) => void;
  onCancel: (proposalId: string) => void;
  resultHref?: string | null;
  resultLabel?: string | null;
  resolved?: boolean;
};

export function AssistantActionConfirmCard({
  proposal,
  pending,
  onConfirm,
  onCancel,
  resultHref,
  resultLabel,
  resolved,
}: Props) {
  const [fields, setFields] = useState<Record<string, string>>(() => {
    const initial: Record<string, string> = {};
    const payload = proposal.payload ?? {};
    for (const field of proposal.editable_fields ?? []) {
      const value = payload[field.key];
      initial[field.key] = value == null ? "" : String(value);
    }
    return initial;
  });

  if (resolved) {
    return (
      <div className="mt-3 rounded-lg border border-border bg-muted/20 p-3">
        <p className="text-xs font-medium text-foreground">Action completed</p>
        <p className="mt-1 text-xs text-muted-foreground">
          {resultLabel ? `${resultLabel} was created.` : "The confirmed action finished successfully."}
        </p>
        {resultHref ? (
          <Link
            href={resultHref}
            className="mt-2 inline-block text-xs font-medium text-foreground underline-offset-2 hover:underline"
          >
            Open result
          </Link>
        ) : null}
      </div>
    );
  }

  if (proposal.status !== "pending") {
    return (
      <div className="mt-3 rounded-lg border border-border bg-muted/20 p-3">
        <p className="text-xs text-muted-foreground">
          This proposed action is {proposal.status}.
        </p>
      </div>
    );
  }

  return (
    <div className="mt-3 space-y-3 rounded-lg border border-border bg-card p-3 shadow-sm">
      <div>
        <p className="text-sm font-medium text-foreground">{proposal.title}</p>
        {proposal.summary ? (
          <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{proposal.summary}</p>
        ) : null}
        <p className="mt-2 text-[11px] text-muted-foreground">
          Requires your confirmation. Nothing is saved until you confirm.
        </p>
      </div>

      <div className="space-y-2">
        {(proposal.editable_fields ?? []).map((field) => (
          <div key={field.key} className="space-y-1">
            <Label htmlFor={`action-${proposal.id}-${field.key}`} className="text-xs">
              {field.label}
              {field.required ? " *" : ""}
            </Label>
            {field.type === "textarea" ? (
              <Textarea
                id={`action-${proposal.id}-${field.key}`}
                className="min-h-[72px] text-xs"
                value={fields[field.key] ?? ""}
                onChange={(e) =>
                  setFields((prev) => ({ ...prev, [field.key]: e.target.value }))
                }
                disabled={pending}
              />
            ) : (
              <Input
                id={`action-${proposal.id}-${field.key}`}
                className="h-8 text-xs"
                value={fields[field.key] ?? ""}
                onChange={(e) =>
                  setFields((prev) => ({ ...prev, [field.key]: e.target.value }))
                }
                disabled={pending}
              />
            )}
          </div>
        ))}
      </div>

      <div className="flex flex-wrap justify-end gap-2">
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={pending}
          onClick={() => onCancel(proposal.id)}
        >
          Cancel
        </Button>
        <Button
          type="button"
          size="sm"
          disabled={pending}
          onClick={() => {
            const payload: Record<string, unknown> = { ...proposal.payload };
            for (const [key, value] of Object.entries(fields)) {
              payload[key] = value.trim() === "" ? null : value.trim();
            }
            onConfirm(proposal.id, payload);
          }}
        >
          {pending ? "Working…" : proposal.confirm_label || "Confirm"}
        </Button>
      </div>
    </div>
  );
}
