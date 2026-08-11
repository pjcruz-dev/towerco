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
import { fetchProcurementPo, updateProcurementPo } from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import type { ProcurementPoLine } from "@/modules/procurement-one/types";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { poId: string };

const emptyLine = (): ProcurementPoLine => ({
  description: "",
  quantity: 1,
  unit_price: 0,
  discount: 0,
  uom: "EA",
});

export function ProcurementPoEditPageClient({ poId }: Props) {
  const router = useRouter();
  const pushNotification = useNotificationStore((s) => s.push);

  const [supplier, setSupplier] = useState("");
  const [vendorCode, setVendorCode] = useState("");
  const [deliveryDate, setDeliveryDate] = useState("");
  const [paymentTerms, setPaymentTerms] = useState("");
  const [vatRate, setVatRate] = useState(12);
  const [lines, setLines] = useState<ProcurementPoLine[]>([emptyLine()]);

  const poQuery = useQuery({
    queryKey: ["procurement-one", "po", poId],
    queryFn: () => fetchProcurementPo(poId),
  });

  useEffect(() => {
    const po = poQuery.data;
    if (!po) return;
    setSupplier(po.supplier ?? "");
    setVendorCode(po.vendor_code ?? "");
    setDeliveryDate(po.delivery_date ?? "");
    setPaymentTerms(po.payment_terms ?? "");
    setVatRate(po.vat_rate);
    setLines(po.lines.length > 0 ? po.lines : [emptyLine()]);
  }, [poQuery.data]);

  const vatable = useMemo(
    () => lines.reduce((sum, line) => sum + Math.max(0, line.quantity * line.unit_price - (line.discount ?? 0)), 0),
    [lines],
  );

  const saveMutation = useMutation({
    mutationFn: () =>
      updateProcurementPo(poId, {
        supplier: supplier.trim(),
        vendor_code: vendorCode.trim() || undefined,
        delivery_date: deliveryDate || undefined,
        payment_terms: paymentTerms.trim() || undefined,
        vat_rate: vatRate,
        lines: lines.filter((line) => line.description.trim() !== ""),
      }),
    onSuccess: () => {
      pushNotification({ title: "Purchase order saved", variant: "success" });
      router.push(`/procurement/pos/${poId}`);
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const updateLine = (index: number, patch: Partial<ProcurementPoLine>) => {
    setLines((current) => current.map((line, i) => (i === index ? { ...line, ...patch } : line)));
  };

  if (poQuery.isLoading) return <SectionCardSkeleton />;
  const po = poQuery.data;
  if (!po || po.status !== "draft") {
    return <p className="text-sm text-destructive">Only draft purchase orders can be edited.</p>;
  }

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href={`/procurement/pos/${poId}`} className="hover:text-primary">
              {po.document_no ?? "Draft PO"}
            </Link>
          }
          title="Edit purchase order"
          description={`Vatable preview: ${po.currency_code} ${vatable.toLocaleString()}`}
        />

        <form
          className="space-y-6"
          onSubmit={(e) => {
            e.preventDefault();
            saveMutation.mutate();
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
                    value={line.quantity}
                    onChange={(e) => updateLine(index, { quantity: Number(e.target.value) })}
                  />
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
                    value={line.unit_price}
                    onChange={(e) => updateLine(index, { unit_price: Number(e.target.value) })}
                  />
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
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

          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" render={<Link href={`/procurement/pos/${poId}`} />}>
              Cancel
            </Button>
            <Button type="submit" disabled={saveMutation.isPending}>
              Save draft
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}
