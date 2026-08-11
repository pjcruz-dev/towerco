"use client";

import { Button } from "@/components/ui/button";
import type { ControlledDocumentRequestMode } from "@/modules/e-approval/controlled-document-compose";

type Props = {
  mode: ControlledDocumentRequestMode;
  onChange: (mode: ControlledDocumentRequestMode) => void;
  disabled?: boolean;
};

export function ControlledDocumentRequestModePicker({ mode, onChange, disabled }: Props) {
  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <p className="text-sm font-medium text-foreground">What are you submitting?</p>
      <p className="mt-1 text-xs text-muted-foreground">
        New documents receive an automatic code after submit (e.g. ATC-QMS-P-001). Revisions reuse an existing code.
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        <Button
          type="button"
          size="sm"
          variant={mode === "new" ? "default" : "outline"}
          disabled={disabled}
          onClick={() => onChange("new")}
        >
          New document
        </Button>
        <Button
          type="button"
          size="sm"
          variant={mode === "revision" ? "default" : "outline"}
          disabled={disabled}
          onClick={() => onChange("revision")}
        >
          Revision of existing
        </Button>
      </div>
      {mode === "new" ? (
        <p className="mt-3 text-xs text-muted-foreground">
          You do not need to enter a document code. It is assigned when you submit and appears on the approval request.
        </p>
      ) : (
        <p className="mt-3 text-xs text-muted-foreground">
          Search and select the document from the registry. Fields below will prefill automatically.
        </p>
      )}
    </div>
  );
}
