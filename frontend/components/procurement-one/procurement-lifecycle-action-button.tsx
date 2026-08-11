"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

type Props = {
  action: "cancel" | "void";
  label: string;
  pending?: boolean;
  onConfirm: (reason: string) => void;
};

export function ProcurementLifecycleActionButton({ action, label, pending = false, onConfirm }: Props) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");

  if (!open) {
    return (
      <Button
        size="sm"
        variant="outline"
        onClick={() => setOpen(true)}
        disabled={pending}
        className={action === "void" ? "border-destructive/40 text-destructive hover:bg-destructive/5" : undefined}
      >
        {label}
      </Button>
    );
  }

  return (
    <div className="w-full min-w-[240px] rounded-lg border border-border bg-muted/30 p-3">
      <Label htmlFor={`lifecycle-reason-${action}`} className="text-xs font-medium">
        {action === "void" ? "Void reason (required)" : "Cancellation reason"}
      </Label>
      <Textarea
        id={`lifecycle-reason-${action}`}
        className="mt-2 min-h-20 text-sm"
        value={reason}
        onChange={(event) => setReason(event.target.value)}
        placeholder="Explain why this document is being voided or cancelled."
      />
      <div className="mt-2 flex flex-wrap gap-2">
        <Button
          size="sm"
          variant={action === "void" ? "destructive" : "default"}
          disabled={pending || (action === "void" && reason.trim().length < 3)}
          onClick={() => {
            onConfirm(reason.trim());
            setOpen(false);
            setReason("");
          }}
        >
          Confirm
        </Button>
        <Button
          size="sm"
          variant="ghost"
          disabled={pending}
          onClick={() => {
            setOpen(false);
            setReason("");
          }}
        >
          Close
        </Button>
      </div>
    </div>
  );
}
