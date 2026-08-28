"use client";

import { isImageSignature, isTypedSignature } from "@/modules/e-approval/signature";
import type { ApprovalHistorySlot } from "@/modules/e-approval/print-template-types";
import { cn } from "@/lib/utils";

type Props = {
  slots: ApprovalHistorySlot[];
  className?: string;
  title?: string;
  emptyMessage?: string;
  variant?: "screen" | "print-footer";
};

function SignatureMark({ value, compact }: { value: string | null; compact?: boolean }) {
  return (
    <div
      className={cn(
        "flex items-center justify-center rounded border border-slate-200 bg-slate-50 px-2",
        compact ? "min-h-[40px]" : "min-h-[44px]",
      )}
    >
      {value ? (
        isImageSignature(value) ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={value} alt="" className="max-h-14 max-w-full object-contain" />
        ) : (
          <span className={cn("text-slate-800", compact ? "text-[11px] italic" : "text-xs")}>{value}</span>
        )
      ) : (
        <span className="text-xs text-slate-400">—</span>
      )}
    </div>
  );
}

export function ApprovalHistoryPrintBlock({
  slots,
  className,
  title = "Approval history",
  emptyMessage = "No signed approvals yet.",
  variant = "screen",
}: Props) {
  const signedSlots = slots.filter((slot) => slot.signature?.trim() || slot.kind === "prepared_by");

  return (
    <footer
      className={cn(
        "border-t border-slate-300 pt-4",
        variant === "print-footer" && "eapproval-print-approval-footer print:fixed print:bottom-0 print:left-0 print:right-0 print:bg-white print:px-8 print:pb-4",
        className,
      )}
    >
      <p className="text-center text-xs font-semibold uppercase tracking-wide text-slate-700">{title}</p>
      {signedSlots.length > 0 ? (
        <div className="mt-3 grid gap-3 sm:grid-cols-2 md:grid-cols-3">
          {signedSlots.map((slot) => (
            <div
              key={slot.key}
              className="rounded border border-slate-300 bg-white p-2 text-center"
            >
              <p className="text-[10px] font-medium text-slate-700">
                {slot.subtitle ? `${slot.subtitle}` : slot.label}
              </p>
              {slot.subtitle && slot.label !== slot.subtitle ? (
                <p className="text-[10px] text-slate-800">{slot.label}</p>
              ) : null}
              {slot.kind === "approver" && slot.subtitle?.startsWith("Approved") ? null : (
                <p className="text-[9px] text-slate-500">
                  {slot.kind === "approver" ? slot.subtitle : isTypedSignature(slot.signature) ? "Typed signature" : "Signature"}
                </p>
              )}
              <div className="mt-1">
                <SignatureMark value={slot.signature} />
              </div>
            </div>
          ))}
        </div>
      ) : (
        <p className="mt-2 text-center text-xs text-slate-500">{emptyMessage}</p>
      )}
    </footer>
  );
}
