"use client";

import { useQuery } from "@tanstack/react-query";
import { ClipboardList } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalOpenPurchaseRequisitions } from "@/lib/api/modules/e-approval-api";
import { formatSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalOpenPurchaseRequisition } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  formId: string;
  value: string | null;
  onChange: (item: EApprovalOpenPurchaseRequisition | null) => void;
  error?: string | null;
  enabled?: boolean;
  className?: string;
  scope?: "requestor" | "procurement";
  emptyDescription?: string;
};

function formatPurchaseRequisitionLabel(item: EApprovalOpenPurchaseRequisition): string {
  const balance = formatSubmissionAmount(item.open_balance);
  const estimated = formatSubmissionAmount(item.estimated_total);
  const title = item.requisition_title?.trim();
  const titleSuffix = title ? ` · ${title}` : "";

  return `${item.document_no} · open ${balance} of ${estimated}${titleSuffix}`;
}

export function EApprovalPurchaseRequisitionPicker({
  formId,
  value,
  onChange,
  error,
  enabled = true,
  className,
  scope = "requestor",
  emptyDescription,
}: Props) {
  const openQuery = useQuery({
    queryKey: ["e-approval", "purchase-requisitions", "open", formId, scope],
    queryFn: () => fetchEApprovalOpenPurchaseRequisitions(formId, scope === "procurement" ? { scope: "procurement" } : undefined),
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
      title="Purchase requisition to fulfill"
      description="Select an approved purchase requisition with remaining budget. This link is required before you can save or submit."
    >
      {openQuery.isLoading ? (
        <div className="h-10 animate-pulse rounded-lg bg-muted/50" />
      ) : openQuery.isError ? (
        <OperationalAlert
          level="error"
          title="Could not load purchase requisitions"
          description="Refresh the page or try again in a moment."
        />
      ) : items.length === 0 ? (
        <OperationalAlert
          level="warning"
          title="No open purchase requisitions"
          description={
            emptyDescription ??
            (scope === "procurement"
              ? "Only fully approved purchase requisitions with remaining budget appear here. If a PR is still in workflow, wait until all approvers finish."
              : "You need an approved purchase requisition with remaining budget before you can submit a purchase order.")
          }
        />
      ) : (
        <div className="space-y-3">
          <Label className="block">
            <span className="text-xs font-medium text-muted-foreground">Approved purchase requisition</span>
            <Select
              className={cn("mt-1 h-11 w-full text-base sm:h-9 sm:text-sm", error ? "border-destructive" : undefined)}
              value={value ?? ""}
              onChange={(event) => handleSelect(event.target.value)}
            >
              <option value="">Select a purchase requisition…</option>
              {items.map((item) => (
                <option key={item.id} value={item.id}>
                  {formatPurchaseRequisitionLabel(item)}
                </option>
              ))}
            </Select>
            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
          </Label>

          {selected ? (
            <div className="flex items-start gap-3 rounded-lg border border-border bg-muted/20 px-3 py-2.5 text-sm">
              <ClipboardList className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
              <div className="min-w-0 space-y-0.5">
                <p className="font-medium text-foreground">{selected.document_no}</p>
                {selected.requisition_title ? (
                  <p className="text-muted-foreground">{selected.requisition_title}</p>
                ) : null}
                <p className="text-muted-foreground">
                  Open balance{" "}
                  <span className="font-medium text-foreground">{formatSubmissionAmount(selected.open_balance)}</span>
                  {" · "}Estimated {formatSubmissionAmount(selected.estimated_total)}
                  {selected.committed_amount > 0
                    ? ` · Already ordered ${formatSubmissionAmount(selected.committed_amount)}`
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
