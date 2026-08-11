"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementPaymentStatusBadge } from "@/components/procurement-one/procurement-payment-status-badge";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { useFinanceModulePaths } from "@/hooks/use-finance-module-paths";
import {
  approveProcurementPaymentRequest,
  createProcurementPaymentBatch,
  fetchProcurementPaymentRequest,
  markProcurementPaymentRequestPaid,
  markProcurementPaymentRequestReconciled,
  scheduleProcurementPaymentRequest,
  submitProcurementPaymentRequest,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

export function ProcurementPaymentDetailPageClient({ id }: { id: string }) {
  const financePaths = useFinanceModulePaths();
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);
  const [paymentReference, setPaymentReference] = useState("");
  const [scheduledDate, setScheduledDate] = useState("");

  const query = useQuery({
    queryKey: ["procurement-one", "payment-requests", id],
    queryFn: () => fetchProcurementPaymentRequest(id),
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "payment-requests", id] });
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "payment-requests"] });
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "payment-batches"] });
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "ap-invoices"] });
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "ap-aging"] });
    queryClient.invalidateQueries({ queryKey: ["procurement-one", "dashboard"] });
  };

  const submitMutation = useMutation({
    mutationFn: () => submitProcurementPaymentRequest(id),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "payment-requests", id], data);
      invalidate();
      pushNotification({ title: "Payment request submitted", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const approveMutation = useMutation({
    mutationFn: () => approveProcurementPaymentRequest(id),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "payment-requests", id], data);
      invalidate();
      pushNotification({ title: "Payment request approved", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const scheduleMutation = useMutation({
    mutationFn: () => scheduleProcurementPaymentRequest(id, scheduledDate ? { scheduled_date: scheduledDate } : undefined),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "payment-requests", id], data);
      invalidate();
      pushNotification({ title: "Payment scheduled", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const batchMutation = useMutation({
    mutationFn: () =>
      createProcurementPaymentBatch({
        payment_request_ids: [id],
        scheduled_date: scheduledDate || undefined,
      }),
    onSuccess: () => {
      invalidate();
      pushNotification({ title: "Payment batch created", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const paidMutation = useMutation({
    mutationFn: () => markProcurementPaymentRequestPaid(id, paymentReference ? { payment_reference: paymentReference } : undefined),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "payment-requests", id], data);
      invalidate();
      pushNotification({ title: "Payment marked paid", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const reconciledMutation = useMutation({
    mutationFn: () => markProcurementPaymentRequestReconciled(id),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "payment-requests", id], data);
      invalidate();
      pushNotification({ title: "Payment reconciled", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const payment = query.data;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <>
              <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
              <span className="text-muted-foreground"> / </span>
              <Link href={financePaths.payments} className="hover:text-primary">
                Payments
              </Link>
            </>
          }
          title={payment?.document_no ?? "Payment request"}
          description={payment?.vendor_name ? `Pay ${payment.vendor_name}` : "Vendor payment request"}
        />

        {query.isLoading ? <SectionCardSkeleton /> : null}
        {query.isError ? <p className="text-sm text-destructive">Could not load payment request.</p> : null}

        {payment ? (
          <>
            <section className="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-2 lg:grid-cols-4">
              <div>
                <p className="text-xs text-muted-foreground">Status</p>
                <div className="mt-1">
                  <ProcurementPaymentStatusBadge status={payment.status} label={payment.status_label} />
                </div>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Amount</p>
                <p className="mt-1 text-sm font-medium">
                  {payment.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })} {payment.currency_code}
                </p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">AP invoice</p>
                <p className="mt-1 text-sm font-medium">
                  <Link href={financePaths.apInvoice(payment.ap_invoice_id)} className="text-primary hover:underline">
                    {payment.ap_invoice_document_no ?? payment.ap_vendor_invoice_no ?? payment.ap_invoice_id}
                  </Link>
                </p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Payment reference</p>
                <p className="mt-1 text-sm font-medium">{payment.payment_reference ?? "—"}</p>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <h2 className="text-base font-medium">Workflow</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Track payment status from approval through scheduling, paid, and reconciled. No bank execution in TowerOS.
              </p>
              <div className="mt-4 flex flex-wrap gap-2">
                {payment.status === "draft" ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                    <Button size="sm" onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending}>
                      Submit for approval
                    </Button>
                  </PermissionGate>
                ) : null}
                {payment.status === "pending_approval" ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                    <Button size="sm" onClick={() => approveMutation.mutate()} disabled={approveMutation.isPending}>
                      Approve
                    </Button>
                  </PermissionGate>
                ) : null}
                {payment.status === "approved" && !payment.payment_batch_id ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                    <div className="flex flex-wrap items-end gap-3">
                      <div>
                        <Label htmlFor="scheduled_date">Scheduled date</Label>
                        <DatePicker
                          id="scheduled_date"
                          value={scheduledDate}
                          onChange={setScheduledDate}
                          className="mt-1 w-44"
                        />
                      </div>
                      <Button size="sm" variant="outline" onClick={() => scheduleMutation.mutate()} disabled={scheduleMutation.isPending}>
                        Schedule only
                      </Button>
                      <Button size="sm" onClick={() => batchMutation.mutate()} disabled={batchMutation.isPending}>
                        Create payment batch
                      </Button>
                    </div>
                  </PermissionGate>
                ) : null}
                {payment.status === "scheduled" ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                    <div className="flex flex-wrap items-end gap-3">
                      <div>
                        <Label htmlFor="payment_reference">Bank reference</Label>
                        <Input
                          id="payment_reference"
                          value={paymentReference}
                          onChange={(e) => setPaymentReference(e.target.value)}
                          className="mt-1 w-56"
                          placeholder="Transfer ref / check no."
                        />
                      </div>
                      <Button size="sm" onClick={() => paidMutation.mutate()} disabled={paidMutation.isPending}>
                        Mark paid
                      </Button>
                    </div>
                  </PermissionGate>
                ) : null}
                {payment.status === "paid" ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                    <Button size="sm" onClick={() => reconciledMutation.mutate()} disabled={reconciledMutation.isPending}>
                      Mark reconciled
                    </Button>
                  </PermissionGate>
                ) : null}
              </div>
              {payment.payment_batch_document_no ? (
                <p className="mt-3 text-sm text-muted-foreground">Batch: {payment.payment_batch_document_no}</p>
              ) : null}
            </section>

            {(payment.audit_trail?.length ?? 0) > 0 ? (
              <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">Audit trail</h2>
                <ul className="mt-3 space-y-2 text-sm">
                  {payment.audit_trail.map((event) => (
                    <li key={event.id} className="flex flex-wrap justify-between gap-2 border-t border-border pt-2 first:border-0 first:pt-0">
                      <span>
                        <span className="font-medium">{event.action.replaceAll("_", " ")}</span>
                        {event.actor ? <span className="text-muted-foreground"> · {event.actor.name}</span> : null}
                      </span>
                      <span className="text-muted-foreground">{event.created_at ? new Date(event.created_at).toLocaleString() : "—"}</span>
                    </li>
                  ))}
                </ul>
              </section>
            ) : null}
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
