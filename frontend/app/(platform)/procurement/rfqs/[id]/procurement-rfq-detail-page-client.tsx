"use client";

import Link from "next/link";
import { useMemo } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { ProcurementPaymentStatusBadge } from "@/components/procurement-one/procurement-payment-status-badge";
import { ProcurementRfqBidVersionsSection } from "@/components/procurement-one/procurement-rfq-bid-versions-section";
import { ProcurementRfqCaptureBidSection } from "@/components/procurement-one/procurement-rfq-capture-bid-section";
import { ProcurementRfqInviteVendorsSection } from "@/components/procurement-one/procurement-rfq-invite-vendors-section";
import { ProcurementRfqLinesSection } from "@/components/procurement-one/procurement-rfq-lines-section";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import {
  awardProcurementRfq,
  closeProcurementRfqBidding,
  createProcurementPoFromRfq,
  fetchProcurementRfq,
  publishProcurementRfq,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { formatMoney } from "@/lib/procurement/quote-basis";
import { permissions } from "@/lib/rbac/permissions";
import type { ProcurementRfqDetail } from "@/modules/procurement-one/types";
import { useNotificationStore } from "@/stores/notification-store";

export function ProcurementRfqDetailPageClient({ id }: { id: string }) {
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);

  const query = useQuery({
    queryKey: ["procurement-one", "rfqs", id],
    queryFn: () => fetchProcurementRfq(id),
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "rfqs", id] });
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "rfqs"] });
  };

  const publishMutation = useMutation({
    mutationFn: () => publishProcurementRfq(id),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "rfqs", id], data);
      invalidate();
      pushNotification({ title: "RFQ published", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const closeMutation = useMutation({
    mutationFn: () => closeProcurementRfqBidding(id),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "rfqs", id], data);
      invalidate();
      pushNotification({ title: "Bidding closed", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const awardMutation = useMutation({
    mutationFn: (bidId: string) => awardProcurementRfq(id, bidId),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "rfqs", id], data);
      invalidate();
      pushNotification({ title: "RFQ awarded", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const poMutation = useMutation({
    mutationFn: () => createProcurementPoFromRfq(id),
    onSuccess: () => {
      invalidate();
      query.refetch();
      pushNotification({ title: "Purchase order created from award", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const rfq = query.data;
  const matrix = rfq?.comparison_matrix;
  const matrixPricing = useMemo(() => {
    const rows = matrix?.rows ?? [];
    return {
      showMonthly: rows.some((row) => row.total_amount_monthly != null && row.total_amount_monthly > 0),
      showYearly: rows.some((row) => row.total_amount_yearly != null && row.total_amount_yearly > 0),
      showNormalizedAnnual: rows.some(
        (row) =>
          row.normalized_annual_amount != null &&
          row.normalized_annual_amount > 0 &&
          row.normalized_annual_amount !== row.total_amount,
      ),
    };
  }, [matrix?.rows]);
  const canPublish = rfq?.status === "draft" && (rfq.invited_vendor_count ?? 0) > 0;

  const handleRfqUpdated = (updated: ProcurementRfqDetail) => {
    queryClient.setQueryData(["procurement-one", "rfqs", id], updated);
    invalidate();
  };

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <>
              <Link href="/procurement" className="hover:text-primary">
                Procurement-One
              </Link>
              <span className="text-muted-foreground"> / </span>
              <Link href="/procurement/rfqs" className="hover:text-primary">
                RFQs
              </Link>
            </>
          }
          title={rfq?.document_no ?? rfq?.title ?? "RFQ"}
          description={rfq?.title ?? "Request for quotation"}
        />

        {query.isLoading ? <SectionCardSkeleton /> : null}
        {query.isError ? <p className="text-sm text-destructive">Could not load RFQ.</p> : null}

        {rfq ? (
          <>
            <section className="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-2 lg:grid-cols-4">
              <div>
                <p className="text-xs text-muted-foreground">Status</p>
                <div className="mt-1">
                  <ProcurementPaymentStatusBadge status={rfq.status} label={rfq.status_label} />
                </div>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Linked PR</p>
                <p className="mt-1 text-sm font-medium">
                  {rfq.pr_id ? (
                    <Link href={`/procurement/prs/${rfq.pr_id}`} className="text-primary hover:underline">
                      {rfq.pr_document_no ?? rfq.pr_id}
                    </Link>
                  ) : (
                    "—"
                  )}
                </p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Invited vendors</p>
                <p className="mt-1 text-sm font-medium">{rfq.invited_vendor_count}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Bids received</p>
                <p className="mt-1 text-sm font-medium">{rfq.bid_count}</p>
              </div>
            </section>

            <ProcurementRfqLinesSection
              lines={rfq.lines}
              currencyCode={rfq.currency_code}
              estimatedTotal={rfq.estimated_total}
              linesSource={rfq.lines_source ?? (rfq.pr_id ? "purchase_requisition" : "rfq")}
            />

            <ProcurementRfqInviteVendorsSection
              rfqId={id}
              status={rfq.status}
              vendorPortalEnabled={rfq.vendor_portal_enabled}
              invitedVendors={rfq.invited_vendors}
              onUpdated={handleRfqUpdated}
            />

            <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
              <ProcurementRfqCaptureBidSection rfqId={id} rfq={rfq} onCaptured={handleRfqUpdated} />
            </PermissionGate>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <h2 className="text-base font-medium">Workflow</h2>
              <div className="mt-4 flex flex-wrap gap-2">
                {rfq.status === "draft" ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                    <Button
                      size="sm"
                      onClick={() => publishMutation.mutate()}
                      disabled={publishMutation.isPending || !canPublish}
                    >
                      Publish RFQ
                    </Button>
                  </PermissionGate>
                ) : null}
                {rfq.status === "open" ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                    <Button size="sm" variant="outline" onClick={() => closeMutation.mutate()} disabled={closeMutation.isPending}>
                      Close bidding
                    </Button>
                  </PermissionGate>
                ) : null}
                {rfq.status === "awarded" && !rfq.purchase_order ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                    <Button size="sm" onClick={() => poMutation.mutate()} disabled={poMutation.isPending}>
                      Create PO from award
                    </Button>
                  </PermissionGate>
                ) : null}
                {rfq.purchase_order ? (
                  <Button
                    size="sm"
                    variant="outline"
                    render={<Link href={`/procurement/pos/${rfq.purchase_order.id}`} />}
                  >
                    View PO {rfq.purchase_order.document_no ?? ""}
                  </Button>
                ) : null}
              </div>
              {rfq.status === "draft" && rfq.invited_vendor_count < 1 ? (
                <OperationalAlert
                  level="info"
                  className="mt-3"
                  title="Invite vendors before publishing"
                  description="Use the Invited vendors section above to add at least one supplier, then publish to open bidding."
                />
              ) : null}
              {rfq.status === "draft" && (rfq.invited_vendor_count ?? 0) > 0 ? (
                <OperationalAlert
                  level="warning"
                  className="mt-3"
                  title="Publish to open vendor quoting"
                  description="Vendors are invited but bidding is not open yet. Click Publish RFQ above to start accepting quotations. Suppliers with email on file receive the quote link when you publish."
                />
              ) : null}
              {rfq.vendor_portal_enabled ? (
                <p className="mt-3 text-xs text-muted-foreground">
                  Vendor portal is enabled — invited suppliers receive email with a secure link to submit quotations. You can still capture quotes manually below.
                </p>
              ) : (
                <p className="mt-3 text-xs text-muted-foreground">
                  Vendor portal is off in Procurement settings — use Capture quotation for offline vendor quotes.
                </p>
              )}
            </section>

            {matrix && matrix.rows.length > 0 ? (
              <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">Bid comparison matrix</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Weights: price {matrix.policy.weight_price}% · lead time {matrix.policy.weight_lead_time}% · accreditation{" "}
                  {matrix.policy.weight_accreditation}% · coverage {matrix.policy.weight_line_coverage}%
                  {matrixPricing.showNormalizedAnnual
                    ? " · price score uses normalized annual amount for subscription lines"
                    : ""}
                </p>
                <div className="mt-4 overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="text-left text-xs text-muted-foreground">
                      <tr>
                        <th className="pb-2 pr-4 font-medium">Rank</th>
                        <th className="pb-2 pr-4 font-medium">Vendor</th>
                        <th className="pb-2 pr-4 font-medium">Total</th>
                        {matrixPricing.showMonthly ? (
                          <th className="pb-2 pr-4 font-medium">Monthly total</th>
                        ) : null}
                        {matrixPricing.showYearly ? (
                          <th className="pb-2 pr-4 font-medium">Yearly total</th>
                        ) : null}
                        {matrixPricing.showNormalizedAnnual ? (
                          <th className="pb-2 pr-4 font-medium">Normalized annual</th>
                        ) : null}
                        <th className="pb-2 pr-4 font-medium">Lead time</th>
                        <th className="pb-2 pr-4 font-medium">Score</th>
                        <th className="pb-2 font-medium">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {matrix.rows.map((row) => (
                        <tr key={row.bid_id} className="border-t border-border">
                          <td className="py-2 pr-4">{row.rank}</td>
                          <td className="py-2 pr-4">{row.vendor_name ?? row.vendor_code}</td>
                          <td className="py-2 pr-4 tabular-nums">
                            {formatMoney(row.total_amount, row.currency_code)}
                          </td>
                          {matrixPricing.showMonthly ? (
                            <td className="py-2 pr-4 tabular-nums text-muted-foreground">
                              {row.total_amount_monthly != null && row.total_amount_monthly > 0
                                ? formatMoney(row.total_amount_monthly, row.currency_code)
                                : "—"}
                            </td>
                          ) : null}
                          {matrixPricing.showYearly ? (
                            <td className="py-2 pr-4 tabular-nums text-muted-foreground">
                              {row.total_amount_yearly != null && row.total_amount_yearly > 0
                                ? formatMoney(row.total_amount_yearly, row.currency_code)
                                : "—"}
                            </td>
                          ) : null}
                          {matrixPricing.showNormalizedAnnual ? (
                            <td className="py-2 pr-4 tabular-nums">
                              {row.normalized_annual_amount != null
                                ? formatMoney(row.normalized_annual_amount, row.currency_code)
                                : "—"}
                            </td>
                          ) : null}
                          <td className="py-2 pr-4">{row.avg_lead_time_days ?? "—"} days</td>
                          <td className="py-2 pr-4 font-medium">{row.scores.weighted_total.toFixed(1)}</td>
                          <td className="py-2">
                            {rfq.status === "closed" && row.status === "submitted" ? (
                              <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                                <Button size="sm" variant="outline" onClick={() => awardMutation.mutate(row.bid_id)} disabled={awardMutation.isPending}>
                                  Award
                                </Button>
                              </PermissionGate>
                            ) : null}
                            {row.bid_id === matrix.recommended_bid_id && rfq.status === "closed" ? (
                              <span className="ml-2 text-xs text-primary">Recommended</span>
                            ) : null}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </section>
            ) : null}

            {rfq.bids
              .filter((bid) => (bid.version_count ?? bid.current_version_no ?? 0) > 1)
              .map((bid) => (
                <ProcurementRfqBidVersionsSection
                  key={bid.id}
                  rfqId={id}
                  bidId={bid.id}
                  vendorName={bid.vendor_name}
                  currencyCode={bid.currency_code}
                />
              ))}
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
