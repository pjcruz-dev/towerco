"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Plus, Trash2 } from "lucide-react";

import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { createProcurementPoFromPr, fetchProcurementPr } from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import type { ProcurementPoLine } from "@/modules/procurement-one/types";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { prId: string };

const emptyLine = (): ProcurementPoLine => ({
  description: "",
  quantity: 1,
  unit_price: 0,
  discount: 0,
  uom: "EA",
});

function formatMoney(value: number, currency = "PHP"): string {
  return `${currency} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function ProcurementPoFromPrComposePageClient({ prId }: Props) {
  const router = useRouter();
  const pushNotification = useNotificationStore((s) => s.push);

  const [supplier, setSupplier] = useState("");
  const [vendorCode, setVendorCode] = useState("");
  const [deliveryDate, setDeliveryDate] = useState("");
  const [paymentTerms, setPaymentTerms] = useState("");
  const [vatRate, setVatRate] = useState(12);
  const [lines, setLines] = useState<ProcurementPoLine[]>([emptyLine()]);

  const prQuery = useQuery({
    queryKey: ["procurement-one", "pr", prId],
    queryFn: () => fetchProcurementPr(prId),
  });

  useEffect(() => {
    const pr = prQuery.data;
    if (!pr || pr.lines.length === 0) return;
    setLines(
      pr.lines.map((line) => ({
        description: line.description,
        quantity: line.quantity,
        unit_price: line.unit_price,
        discount: 0,
        uom: "EA",
        pr_id: prId,
        pr_line_id: line.id,
      })),
    );
  }, [prQuery.data, prId]);

  const vatable = useMemo(
    () => lines.reduce((sum, line) => sum + Math.max(0, line.quantity * line.unit_price - (line.discount ?? 0)), 0),
    [lines],
  );
  const vatAmount = useMemo(() => Math.round(vatable * vatRate) / 100, [vatable, vatRate]);
  const grandTotal = useMemo(() => vatable + vatAmount, [vatable, vatAmount]);

  const createMutation = useMutation({
    mutationFn: () =>
      createProcurementPoFromPr(prId, {
        supplier: supplier.trim(),
        vendor_code: vendorCode.trim() || undefined,
        delivery_date: deliveryDate || undefined,
        payment_terms: paymentTerms.trim() || undefined,
        vat_rate: vatRate,
        lines: lines.filter((line) => line.description.trim() !== ""),
      }),
    onSuccess: (po) => {
      pushNotification({ title: "Purchase order draft created", variant: "success" });
      router.push(`/procurement/pos/${po.id}`);
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const updateLine = (index: number, patch: Partial<ProcurementPoLine>) => {
    setLines((current) => current.map((line, i) => (i === index ? { ...line, ...patch } : line)));
  };

  if (prQuery.isLoading) return <SectionCardSkeleton />;
  const pr = prQuery.data;
  if (!pr) return <p className="text-sm text-destructive">Could not load purchase requisition.</p>;

  const canCreate = (pr.status === "approved" || pr.status === "converted") && (pr.open_po_balance ?? pr.estimated_total) > 0;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href={`/procurement/prs/${prId}`} className="hover:text-primary">
              {pr.document_no ?? pr.title}
            </Link>
          }
          title="Create purchase order"
          description={`Open PR balance: ${formatMoney(pr.open_po_balance ?? 0, pr.currency)}`}
        />

        {!canCreate ? (
          <p className="text-sm text-destructive">This purchase requisition cannot be converted to a PO.</p>
        ) : (
          <form
            className="space-y-6"
            onSubmit={(e) => {
              e.preventDefault();
              createMutation.mutate();
            }}
          >
            <section className="rounded-xl border border-border bg-card p-4 shadow-sm space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="supplier">Supplier *</Label>
                  <Input id="supplier" value={supplier} onChange={(e) => setSupplier(e.target.value)} required />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="vendor_code">Vendor code</Label>
                  <Input id="vendor_code" value={vendorCode} onChange={(e) => setVendorCode(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="delivery_date">Delivery date</Label>
                  <DatePicker id="delivery_date" value={deliveryDate} onChange={setDeliveryDate} />
                </div>
                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="payment_terms">Payment terms</Label>
                  <Input id="payment_terms" value={paymentTerms} onChange={(e) => setPaymentTerms(e.target.value)} />
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center justify-between gap-3">
                <h2 className="text-base font-medium">Line items</h2>
                <Button type="button" size="sm" variant="outline" onClick={() => setLines((c) => [...c, emptyLine()])}>
                  <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                  Add line
                </Button>
              </div>
              <div className="mt-4 space-y-3">
                {lines.map((line, index) => (
                  <div key={index} className="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6">
                    <Input
                      placeholder="Description"
                      value={line.description}
                      onChange={(e) => updateLine(index, { description: e.target.value })}
                      className="md:col-span-2"
                      required
                    />
                    <Input
                      type="number"
                      min={0}
                      step="0.01"
                      placeholder="Qty"
                      value={line.quantity}
                      onChange={(e) => updateLine(index, { quantity: Number(e.target.value) })}
                    />
                    <Input
                      type="number"
                      min={0}
                      step="0.01"
                      placeholder="Unit price"
                      value={line.unit_price}
                      onChange={(e) => updateLine(index, { unit_price: Number(e.target.value) })}
                    />
                    <Input
                      type="number"
                      min={0}
                      step="0.01"
                      placeholder="Discount"
                      value={line.discount ?? 0}
                      onChange={(e) => updateLine(index, { discount: Number(e.target.value) })}
                    />
                    <Button type="button" variant="ghost" size="icon" onClick={() => setLines((c) => c.filter((_, i) => i !== index))}>
                      <Trash2 className="h-4 w-4" aria-hidden />
                    </Button>
                  </div>
                ))}
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <h2 className="text-base font-medium">Tax summary</h2>
              <dl className="mt-3 grid gap-2 text-sm md:grid-cols-2">
                <div className="flex justify-between gap-4">
                  <dt className="text-muted-foreground">Vatable</dt>
                  <dd className="tabular-nums">{formatMoney(vatable, pr.currency)}</dd>
                </div>
                <div className="flex items-center justify-between gap-4">
                  <dt className="text-muted-foreground">VAT rate %</dt>
                  <Input
                    type="number"
                    min={0}
                    className="h-8 w-24"
                    value={vatRate}
                    onChange={(e) => setVatRate(Number(e.target.value))}
                  />
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-muted-foreground">VAT amount</dt>
                  <dd className="tabular-nums">{formatMoney(vatAmount, pr.currency)}</dd>
                </div>
                <div className="flex justify-between gap-4 font-medium">
                  <dt>Grand total</dt>
                  <dd className="tabular-nums">{formatMoney(grandTotal, pr.currency)}</dd>
                </div>
              </dl>
            </section>

            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" render={<Link href={`/procurement/prs/${prId}`} />}>
                Cancel
              </Button>
              <Button type="submit" disabled={createMutation.isPending || supplier.trim() === ""}>
                Create draft PO
              </Button>
            </div>
          </form>
        )}
      </div>
    </PermissionGate>
  );
}
