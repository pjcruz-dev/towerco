"use client";

import { useState } from "react";

import { EApprovalSignaturePreview } from "@/components/e-approval/e-approval-signature-preview";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { hasSignatureValue } from "@/modules/e-approval/signature";
import { cn } from "@/lib/utils";

type Props = {
  name: string;
  signature?: string | null;
  /** When true, render the name only (print / PDF shows signatures elsewhere). */
  alwaysShowSignatures?: boolean;
  className?: string;
};

/** Approver display name; hover/focus shows signature when one is recorded. */
export function EApprovalApproverNameSignature({
  name,
  signature,
  alwaysShowSignatures = false,
  className,
}: Props) {
  const [open, setOpen] = useState(false);
  const hasSignature = hasSignatureValue(signature);

  if (!hasSignature || alwaysShowSignatures) {
    return <span className={className}>{name}</span>;
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        render={
          <button
            type="button"
            className={cn(
              "rounded-sm font-medium text-foreground underline decoration-border underline-offset-2 hover:decoration-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50",
              className,
            )}
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
            onFocus={() => setOpen(true)}
            onBlur={() => setOpen(false)}
            onClick={(e) => e.stopPropagation()}
            aria-label={`${name} — show signature`}
          >
            {name}
          </button>
        }
      />
      <PopoverContent
        align="start"
        side="top"
        className="w-72 p-3"
        onMouseEnter={() => setOpen(true)}
        onMouseLeave={() => setOpen(false)}
      >
        <EApprovalSignaturePreview value={signature} label="Signature" className="space-y-1.5" />
      </PopoverContent>
    </Popover>
  );
}
