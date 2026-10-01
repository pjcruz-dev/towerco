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

      {Object.keys(proposal.preview ?? {}).length > 0 ? (
        <dl className="grid gap-1.5 rounded-md border border-border/80 bg-muted/30 px-2.5 py-2 text-[11px]">
          {Object.entries(proposal.preview).map(([key, value]) => {
            if (value == null || value === "") return null;
            const display =
              Array.isArray(value)
                ? value.map(String).join(", ")
                : typeof value === "object"
                  ? null
                  : String(value);
            if (display == null || display === "") return null;
            return (
              <div key={key} className="flex gap-2">
                <dt className="shrink-0 font-medium capitalize text-muted-foreground">
                  {key.replace(/_/g, " ")}
                </dt>
                <dd className="min-w-0 truncate text-foreground">{display}</dd>
              </div>
            );
          })}
        </dl>
      ) : null}

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
              const trimmed = value.trim();
              if (key === "pin_to_dashboard") {
                payload[key] =
                  trimmed === "1" || trimmed.toLowerCase() === "true" || trimmed.toLowerCase() === "yes";
                continue;
              }
              if (key === "replace") {
                payload[key] =
                  trimmed === "1" || trimmed.toLowerCase() === "true" || trimmed.toLowerCase() === "yes";
                continue;
              }
              if (key === "roles" && trimmed.includes(",")) {
                payload[key] = trimmed
                  .split(",")
                  .map((part) => part.trim())
                  .filter(Boolean);
                continue;
              }
              if (key === "roles" && trimmed !== "") {
                payload[key] = [trimmed];
                continue;
              }
              payload[key] = trimmed === "" ? null : trimmed;
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
