"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Building2, UserPlus } from "lucide-react";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchProcurementVendors, inviteProcurementRfqVendors, resendProcurementRfqVendorInvitation } from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import type { ProcurementRfqDetail } from "@/modules/procurement-one/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type InvitedVendor = ProcurementRfqDetail["invited_vendors"][number];

type Props = {
  rfqId: string;
  status: string;
  vendorPortalEnabled?: boolean;
  invitedVendors: InvitedVendor[];
  onUpdated: (rfq: ProcurementRfqDetail) => void;
};

export function ProcurementRfqInviteVendorsSection({ rfqId, status, vendorPortalEnabled = false, invitedVendors, onUpdated }: Props) {
  const pushNotification = useNotificationStore((s) => s.push);
  const [search, setSearch] = useState("");
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const debouncedSearch = useDebouncedValue(search, 300);

  const canInvite = status === "draft" || status === "open";
  const invitedIds = useMemo(() => new Set(invitedVendors.map((v) => v.vendor_id)), [invitedVendors]);

  const vendorsQuery = useQuery({
    queryKey: ["procurement-one", "vendors", "rfq-invite", debouncedSearch],
    queryFn: () =>
      fetchProcurementVendors({
        search: debouncedSearch || undefined,
        per_page: 50,
        page: 1,
      }),
    enabled: canInvite,
    staleTime: 30_000,
  });

  const availableVendors = useMemo(
    () => (vendorsQuery.data?.data ?? []).filter((vendor) => !invitedIds.has(vendor.id)),
    [invitedIds, vendorsQuery.data?.data],
  );

  const inviteMutation = useMutation({
    mutationFn: (vendorIds: string[]) => inviteProcurementRfqVendors(rfqId, vendorIds),
    onSuccess: (rfq, vendorIds) => {
      setSelectedIds([]);
      onUpdated(rfq);
      pushNotification({
        title:
          vendorIds.length === 1
            ? vendorPortalEnabled
              ? "Vendor invited — invitation email sent when configured"
              : "Vendor invited to RFQ"
            : `${vendorIds.length} vendors invited to RFQ`,
        variant: "success",
      });
    },
    onError: (error) =>
      pushNotification({
        title: getErrorMessage(error),
        variant: "destructive",
      }),
  });

  const resendMutation = useMutation({
    mutationFn: (vendorId: string) => resendProcurementRfqVendorInvitation(rfqId, vendorId),
    onSuccess: (rfq) => {
      onUpdated(rfq);
      pushNotification({ title: "Invitation email resent", variant: "success" });
    },
    onError: (error) =>
      pushNotification({
        title: getErrorMessage(error),
        variant: "destructive",
      }),
  });

  const toggleVendor = (vendorId: string) => {
    setSelectedIds((current) =>
      current.includes(vendorId) ? current.filter((id) => id !== vendorId) : [...current, vendorId],
    );
  };

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-medium">Invited vendors</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Invite accredited suppliers before publishing. At least one vendor is required to open bidding.
            {vendorPortalEnabled ? " Invited vendors with email receive a secure link to submit quotes online." : null}
          </p>
        </div>
        <PermissionGate requiredPermissions={[permissions.procurementOneVendorsView]}>
          <Button size="sm" variant="outline" render={<Link href="/procurement/vendors" />}>
            <Building2 className="mr-1.5 h-4 w-4" aria-hidden />
            Vendor registry
          </Button>
        </PermissionGate>
      </div>

      {invitedVendors.length > 0 ? (
        <ul className="mt-4 divide-y divide-border rounded-lg border border-border">
          {invitedVendors.map((vendor) => (
            <li key={vendor.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-sm">
              <div>
                <p className="font-medium text-foreground">{vendor.vendor_name ?? vendor.vendor_code ?? "Vendor"}</p>
                <p className="text-xs text-muted-foreground">
                  {[vendor.vendor_code, vendor.invitation_email].filter(Boolean).join(" · ") || "No email on file"}
                </p>
                {vendor.invitation_sent_at ? (
                  <p className="text-xs text-muted-foreground">
                    Email sent {new Date(vendor.invitation_sent_at).toLocaleString()}
                    {vendor.invitation_opened_at ? " · opened" : ""}
                    {vendor.submitted_via === "portal" ? " · submitted via portal" : ""}
                  </p>
                ) : vendorPortalEnabled ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">No invitation email sent yet</p>
                ) : null}
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs capitalize text-muted-foreground">
                  {vendor.invitation_status.replace(/_/g, " ")}
                </span>
                {vendorPortalEnabled && canInvite ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={resendMutation.isPending}
                      onClick={() => resendMutation.mutate(vendor.vendor_id)}
                    >
                      Resend email
                    </Button>
                  </PermissionGate>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <OperationalAlert
          level="warning"
          className="mt-4"
          title="No vendors invited yet"
          description="Select vendors below, then invite them before publishing this RFQ."
        />
      )}

      {canInvite ? (
        <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
          <div className="mt-4 space-y-3 rounded-lg border border-dashed border-border bg-muted/20 p-4">
            <div className="flex flex-wrap items-end gap-3">
              <div className="min-w-[16rem] flex-1">
                <label htmlFor="rfq_vendor_search" className="text-xs font-medium text-muted-foreground">
                  Search vendors
                </label>
                <Input
                  id="rfq_vendor_search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Company name or vendor code…"
                  className="mt-1"
                />
              </div>
              <Button
                size="sm"
                disabled={selectedIds.length === 0 || inviteMutation.isPending}
                onClick={() => inviteMutation.mutate(selectedIds)}
              >
                <UserPlus className="mr-1.5 h-4 w-4" aria-hidden />
                Invite selected ({selectedIds.length})
              </Button>
            </div>

            {vendorsQuery.isLoading ? (
              <p className="text-sm text-muted-foreground">Loading vendors…</p>
            ) : availableVendors.length > 0 ? (
              <ul className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-border bg-background p-2">
                {availableVendors.map((vendor) => {
                  const checked = selectedIds.includes(vendor.id);

                  return (
                    <li key={vendor.id}>
                      <label
                        className={cn(
                          "flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 text-sm transition-colors",
                          checked ? "bg-primary/5" : "hover:bg-muted/40",
                        )}
                      >
                        <Checkbox
                          className="mt-0.5 size-4"
                          checked={checked}
                          onCheckedChange={() => toggleVendor(vendor.id)}
                        />
                        <span className="min-w-0 flex-1">
                          <span className="block font-medium text-foreground">{vendor.company_name}</span>
                          <span className="block text-xs text-muted-foreground">
                            {vendor.vendor_code}
                            {vendor.accreditation_status_label
                              ? ` · ${vendor.accreditation_status_label}`
                              : null}
                          </span>
                        </span>
                      </label>
                    </li>
                  );
                })}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">
                No active vendors match your search.{" "}
                <Link href="/procurement/vendors/new" className="font-medium text-primary hover:underline">
                  Register a vendor
                </Link>{" "}
                first.
              </p>
            )}
          </div>
        </PermissionGate>
      ) : null}
    </section>
  );
}
