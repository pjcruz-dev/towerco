"use client";

import { useQuery } from "@tanstack/react-query";
import { Wallet } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalOpenCashAdvances } from "@/lib/api/modules/e-approval-api";
import { formatSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalOpenCashAdvance } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  formId: string;
  value: string | null;
  onChange: (item: EApprovalOpenCashAdvance | null) => void;
  error?: string | null;
  enabled?: boolean;
  className?: string;
};

function formatCashAdvanceLabel(item: EApprovalOpenCashAdvance): string {
  const balance = formatSubmissionAmount(item.open_balance);
  const requested = formatSubmissionAmount(item.requested_amount);
  return `${item.document_no} · open ${balance} of ${requested}`;
}

export function EApprovalCashAdvancePicker({
  formId,
  value,
  onChange,
  error,
  enabled = true,
  className,
}: Props) {
  const openQuery = useQuery({
    queryKey: ["e-approval", "cash-advances", "open", formId],
    queryFn: () => fetchEApprovalOpenCashAdvances(formId),
    enabled: enabled && !!formId,
    staleTime: 30_000,
  });

  const items = openQuery.data ?? [];
  const selected = items.find((item) => item.id === value) ?? null;

  const handleSelect = (nextId: string) => {
    if (!nextId) {
      onChange(null);
      return;
    }
    const item = items.find((entry) => entry.id === nextId) ?? null;
    onChange(item);
  };

  return (
    <EApprovalSectionCard
      className={className}
      title="Cash advance to liquidate"
      description="Select an approved cash advance with remaining balance. This link is required before you can save or submit."
    >
      {openQuery.isLoading ? (
        <div className="h-10 animate-pulse rounded-lg bg-muted/50" />
      ) : openQuery.isError ? (
        <OperationalAlert
          level="error"
          title="Could not load cash advances"
          description="Refresh the page or try again in a moment."
        />
      ) : items.length === 0 ? (
        <OperationalAlert
          level="warning"
          title="No open cash advances"
          description="You need an approved cash advance with remaining balance before you can submit a liquidation."
        />
      ) : (
        <div className="space-y-3">
          <Label className="block">
            <span className="text-xs font-medium text-muted-foreground">Approved cash advance</span>
            <Select
              className={cn("mt-1 h-11 w-full text-base sm:h-9 sm:text-sm", error ? "border-destructive" : undefined)}
              value={value ?? ""}
              onChange={(event) => handleSelect(event.target.value)}
            >
              <option value="">Select a cash advance…</option>
              {items.map((item) => (
                <option key={item.id} value={item.id}>
                  {formatCashAdvanceLabel(item)}
                </option>
              ))}
            </Select>
            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
          </Label>

          {selected ? (
            <div className="flex items-start gap-3 rounded-lg border border-border bg-muted/20 px-3 py-2.5 text-sm">
              <Wallet className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
              <div className="min-w-0 space-y-0.5">
                <p className="font-medium text-foreground">{selected.document_no}</p>
                <p className="text-muted-foreground">
                  Open balance{" "}
                  <span className="font-medium text-foreground">{formatSubmissionAmount(selected.open_balance)}</span>
                  {" · "}Requested {formatSubmissionAmount(selected.requested_amount)}
                  {selected.reimbursed_amount > 0
                    ? ` · Already liquidated ${formatSubmissionAmount(selected.reimbursed_amount)}`
                    : null}
                </p>
              </div>
            </div>
          ) : null}
        </div>
      )}
    </EApprovalSectionCard>
  );
}
