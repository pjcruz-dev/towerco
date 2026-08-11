"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";

import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useFinanceModulePaths } from "@/hooks/use-finance-module-paths";
import { createProcurementContract, fetchProcurementVendors } from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const fieldClass =
  "h-9 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50";

export function ProcurementContractCreatePageClient() {
  const financePaths = useFinanceModulePaths();
  const router = useRouter();
  const pushNotification = useNotificationStore((s) => s.push);
  const [title, setTitle] = useState("");
  const [vendorId, setVendorId] = useState("");
  const [spendCeiling, setSpendCeiling] = useState("");
  const [effectiveFrom, setEffectiveFrom] = useState("");
  const [endDate, setEndDate] = useState("");
  const [description, setDescription] = useState("");

  const vendorsQuery = useQuery({
    queryKey: ["procurement-one", "vendors", "picker"],
    queryFn: () => fetchProcurementVendors({ per_page: 100, status: "active" }),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createProcurementContract({
        title: title.trim(),
        vendor_id: vendorId,
        description: description.trim() || undefined,
        spend_ceiling: spendCeiling ? Number(spendCeiling) : null,
        effective_from: effectiveFrom || null,
        end_date: endDate || null,
      }),
    onSuccess: (contract) => {
      pushNotification({ title: "Contract draft created", variant: "success" });
      router.push(financePaths.contract(contract.id));
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <>
              <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
              <span className="text-muted-foreground"> / </span>
              <Link href={financePaths.contracts} className="hover:text-primary">
                Vendor contracts
              </Link>
            </>
          }
          title="New vendor contract"
          description="Define vendor, spend ceiling, and term dates. Activate after linking the signed agreement in Documents (Legal → Vendor contracts)."
        />

        <form
          className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm"
          onSubmit={(event) => {
            event.preventDefault();
            createMutation.mutate();
          }}
        >
          <div className="space-y-2">
            <Label htmlFor="title">Title</Label>
            <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} required />
          </div>

          <div className="space-y-2">
            <Label htmlFor="vendor">Vendor</Label>
            <select id="vendor" value={vendorId} onChange={(e) => setVendorId(e.target.value)} className={fieldClass} required>
              <option value="">Select vendor…</option>
              {(vendorsQuery.data?.data ?? []).map((vendor) => (
                <option key={vendor.id} value={vendor.id}>
                  {vendor.company_name ?? vendor.vendor_code}
                </option>
              ))}
            </select>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="ceiling">Spend ceiling (optional)</Label>
              <Input id="ceiling" type="number" min={0} step="0.01" value={spendCeiling} onChange={(e) => setSpendCeiling(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="effective_from">Effective from</Label>
              <DatePicker id="effective_from" value={effectiveFrom} onChange={setEffectiveFrom} className={fieldClass} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="end_date">End date</Label>
              <DatePicker id="end_date" value={endDate} onChange={setEndDate} className={fieldClass} />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea id="description" value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
          </div>

          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" render={<Link href={financePaths.contracts} />}>
              Cancel
            </Button>
            <Button type="submit" disabled={createMutation.isPending || !title.trim() || !vendorId}>
              Create draft
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}
