"use client";

import { useEffect, useMemo, useState } from "react";
import { useMutation } from "@tanstack/react-query";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { captureProcurementRfqBid } from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import {
  allowsMonthlyQuote,
  allowsYearlyQuote,
  formatMoney,
  quoteBasisLabel,
  requiresUnitPrice,
} from "@/lib/procurement/quote-basis";
import type { ProcurementRfqDetail } from "@/modules/procurement-one/types";
import { useNotificationStore } from "@/stores/notification-store";

type LineDraft = {
  rfq_line_id: string;
  quantity: string;
  unit_price: string;
  monthly_unit_price: string;
  yearly_unit_price: string;
  lead_time_days: string;
};

type Props = {
  rfqId: string;
  rfq: ProcurementRfqDetail;
  onCaptured: (rfq: ProcurementRfqDetail) => void;
};

export function ProcurementRfqCaptureBidSection({ rfqId, rfq, onCaptured }: Props) {
  const pushNotification = useNotificationStore((s) => s.push);
  const [vendorId, setVendorId] = useState("");
  const [lines, setLines] = useState<LineDraft[]>([]);

  useEffect(() => {
    setLines(
      rfq.lines.map((line) => ({
        rfq_line_id: line.id,
        quantity: String(line.quantity),
        unit_price: line.target_unit_price != null ? String(line.target_unit_price) : "",
        monthly_unit_price: "",
        yearly_unit_price: "",
        lead_time_days: "7",
      })),
    );
  }, [rfq.lines]);

  const captureMutation = useMutation({
    mutationFn: () => {
      const selectedVendorId = vendorId || rfq.invited_vendors[0]?.vendor_id;
      if (!selectedVendorId) {
        throw new Error("Select a vendor before capturing a quotation.");
      }

      return captureProcurementRfqBid(rfqId, {
        vendor_id: selectedVendorId,
        lines: lines.map((line) => ({
          rfq_line_id: line.rfq_line_id,
          quantity: Number(line.quantity) || 0,
          unit_price: line.unit_price.trim() !== "" ? Number(line.unit_price) : undefined,
          monthly_unit_price: line.monthly_unit_price.trim() !== "" ? Number(line.monthly_unit_price) : undefined,
          yearly_unit_price: line.yearly_unit_price.trim() !== "" ? Number(line.yearly_unit_price) : undefined,
          lead_time_days: Number(line.lead_time_days) || undefined,
        })),
      });
    },
    onSuccess: (updated) => {
      onCaptured(updated);
      pushNotification({ title: "Vendor quotation captured", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const hasSubscriptionLines = useMemo(
    () => rfq.lines.some((line) => line.quote_basis && line.quote_basis !== "one_time"),
    [rfq.lines],
  );

  if (rfq.status !== "open") {
    return null;
  }

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium">Capture quotation</h2>
      <p className="mt-1 text-sm text-muted-foreground">
        Record an offline vendor quote when the portal is unavailable or for phone/email bids.
        {hasSubscriptionLines ? " Enter monthly and/or yearly prices where required by quote basis." : ""}
      </p>

      <div className="mt-4 flex flex-wrap items-end gap-3">
        <div>
          <Label htmlFor="capture_bid_vendor">Vendor</Label>
          <select
            id="capture_bid_vendor"
            value={vendorId}
            onChange={(e) => setVendorId(e.target.value)}
            className="mt-1 h-9 rounded-lg border border-input bg-background px-3 text-sm"
          >
            <option value="">Select vendor</option>
            {rfq.invited_vendors.map((vendor) => (
              <option key={vendor.vendor_id} value={vendor.vendor_id}>
                {vendor.vendor_name ?? vendor.vendor_code}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="mt-4 space-y-4">
        {rfq.lines.map((line, index) => {
          const draft = lines[index];
          const basis = line.quote_basis ?? "one_time";
          const qty = Number(draft?.quantity ?? line.quantity) || 0;
          const monthlyTotal =
            draft?.monthly_unit_price && Number(draft.monthly_unit_price) > 0
              ? qty * Number(draft.monthly_unit_price)
              : null;
          const yearlyTotal =
            draft?.yearly_unit_price && Number(draft.yearly_unit_price) > 0
              ? qty * Number(draft.yearly_unit_price)
              : null;

          return (
            <div key={line.id} className="rounded-lg border border-border p-3">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="text-sm font-medium">{line.description}</p>
                  <p className="text-xs text-muted-foreground">
                    Qty {line.quantity} {line.uom ?? "ea"} · {line.quote_basis_label ?? quoteBasisLabel(basis)}
                  </p>
                </div>
                {monthlyTotal != null || yearlyTotal != null ? (
                  <div className="text-right text-xs text-muted-foreground">
                    {monthlyTotal != null ? (
                      <p>Monthly total: {formatMoney(monthlyTotal, rfq.currency_code)}</p>
                    ) : null}
                    {yearlyTotal != null ? (
                      <p>Yearly total: {formatMoney(yearlyTotal, rfq.currency_code)}</p>
                    ) : null}
                  </div>
                ) : null}
              </div>
              <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                  <Label htmlFor={`capture_qty_${line.id}`}>Quantity</Label>
                  <Input
                    id={`capture_qty_${line.id}`}
                    type="number"
                    min={0}
                    step="any"
                    value={draft?.quantity ?? ""}
                    onChange={(e) =>
                      setLines((current) =>
                        current.map((row, rowIndex) =>
                          rowIndex === index ? { ...row, quantity: e.target.value } : row,
                        ),
                      )
                    }
                    className="mt-1"
                  />
                </div>
                {requiresUnitPrice(basis) ? (
                  <div>
                    <Label htmlFor={`capture_price_${line.id}`}>Unit price</Label>
                    <Input
                      id={`capture_price_${line.id}`}
                      type="number"
                      min={0}
                      step="0.01"
                      value={draft?.unit_price ?? ""}
                      onChange={(e) =>
                        setLines((current) =>
                          current.map((row, rowIndex) =>
                            rowIndex === index ? { ...row, unit_price: e.target.value } : row,
                          ),
                        )
                      }
                      className="mt-1"
                    />
                  </div>
                ) : null}
                {allowsMonthlyQuote(basis) ? (
                  <div>
                    <Label htmlFor={`capture_monthly_${line.id}`}>Monthly unit price</Label>
                    <Input
                      id={`capture_monthly_${line.id}`}
                      type="number"
                      min={0}
                      step="0.01"
                      value={draft?.monthly_unit_price ?? ""}
                      onChange={(e) =>
                        setLines((current) =>
                          current.map((row, rowIndex) =>
                            rowIndex === index ? { ...row, monthly_unit_price: e.target.value } : row,
                          ),
                        )
                      }
                      className="mt-1"
                    />
                  </div>
                ) : null}
                {allowsYearlyQuote(basis) ? (
                  <div>
                    <Label htmlFor={`capture_yearly_${line.id}`}>Yearly unit price</Label>
                    <Input
                      id={`capture_yearly_${line.id}`}
                      type="number"
                      min={0}
                      step="0.01"
                      value={draft?.yearly_unit_price ?? ""}
                      onChange={(e) =>
                        setLines((current) =>
                          current.map((row, rowIndex) =>
                            rowIndex === index ? { ...row, yearly_unit_price: e.target.value } : row,
                          ),
                        )
                      }
                      className="mt-1"
                    />
                  </div>
                ) : null}
                <div>
                  <Label htmlFor={`capture_lead_${line.id}`}>Lead time (days)</Label>
                  <Input
                    id={`capture_lead_${line.id}`}
                    type="number"
                    min={0}
                    value={draft?.lead_time_days ?? ""}
                    onChange={(e) =>
                      setLines((current) =>
                        current.map((row, rowIndex) =>
                          rowIndex === index ? { ...row, lead_time_days: e.target.value } : row,
                        ),
                      )
                    }
                    className="mt-1"
                  />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <Button size="sm" onClick={() => captureMutation.mutate()} disabled={captureMutation.isPending}>
          Capture quotation
        </Button>
        {captureMutation.isError ? (
          <OperationalAlert level="error" title="Capture failed" description={getErrorMessage(captureMutation.error)} />
        ) : null}
      </div>
    </section>
  );
}
