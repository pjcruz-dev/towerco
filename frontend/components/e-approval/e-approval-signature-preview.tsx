"use client";

import { cn } from "@/lib/utils";
import { isDrawnSignature, isTypedSignature } from "@/modules/e-approval/signature";

type Props = {
  value: string | null | undefined;
  label?: string;
  emptyText?: string;
  className?: string;
};

export function EApprovalSignaturePreview({
  value,
  label = "Preview",
  emptyText = "No signature saved yet.",
  className,
}: Props) {
  return (
    <div className={cn("space-y-2", className)}>
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="flex min-h-[88px] items-center justify-center rounded-lg border border-dashed border-border bg-muted/20 px-4 py-3">
        {!value?.trim() ? (
          <p className="text-sm text-muted-foreground">{emptyText}</p>
        ) : isDrawnSignature(value) ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={value} alt="Saved signature" className="max-h-20 max-w-full object-contain" />
        ) : isTypedSignature(value) ? (
          <p className="font-[family-name:var(--font-signature,'Segoe_Script','Brush_Script_MT',cursive)] text-2xl text-foreground">
            {value}
          </p>
        ) : (
          <p className="text-sm text-foreground">{value}</p>
        )}
      </div>
    </div>
  );
}
