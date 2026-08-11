"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementApInvoiceStatusBadge } from "@/components/procurement-one/procurement-ap-invoice-status-badge";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { useFinanceModulePaths } from "@/hooks/use-finance-module-paths";
import {
  approveProcurementCreditNote,
  createProcurementCreditNote,
  createProcurementPaymentRequestFromApInvoice,
  fetchProcurementApInvoice,
  fetchProcurementFormSchema,
  submitProcurementApInvoice,
  submitProcurementPaymentRequest,
} from "@/lib/api/modules/procurement-one-api";
import { fetchEApprovalSubmission } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { buildProcurementApInvoiceTicketPrefill } from "@/lib/procurement-one/ticket-prefill";
import { permissions } from "@/lib/rbac/permissions";
import { missingRequiredAttachmentFieldLabels } from "@/modules/procurement-one/submit-readiness";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";

export function ProcurementApInvoiceDetailPageClient({ id }: { id: string }) {
  const financePaths = useFinanceModulePaths();
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);
  const [creditAmount, setCreditAmount] = useState("");
  const [creditReason, setCreditReason] = useState("");

  const query = useQuery({
    queryKey: ["procurement-one", "ap-invoices", id],
    queryFn: () => fetchProcurementApInvoice(id),
  });

  const formSchemaQuery = useQuery({
    queryKey: ["procurement-one", "form-schema", "ap_invoice", "detail"],
    queryFn: () => fetchProcurementFormSchema("ap_invoice"),
    enabled: query.data?.status === "draft",
    staleTime: 60_000,
  });

  const submissionQuery = useQuery({
    queryKey: ["e-approval", "submission", query.data?.e_approval_submission_id, "ap-detail"],
    queryFn: () => fetchEApprovalSubmission(query.data!.e_approval_submission_id!),
    enabled: query.data?.status === "draft" && !!query.data?.e_approval_submission_id,
    staleTime: 30_000,
  });

  const submitMutation = useMutation({
    mutationFn: () => submitProcurementApInvoice(id),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "ap-invoices", id], data.invoice);
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "ap-invoices"] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "dashboard"] });
      pushNotification({
        title: data.warning ? `Submitted with warning: ${data.warning}` : "AP invoice submitted for approval",
        variant: data.warning ? "default" : "success",
      });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const creditMutation = useMutation({
    mutationFn: () =>
      createProcurementCreditNote({
        po_id: invoice!.po_id,
        ap_invoice_id: id,
        amount: Number(creditAmount),
        reason: creditReason || undefined,
      }),
    onSuccess: async (note) => {
      await approveProcurementCreditNote(note.id);
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "ap-invoices", id] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "ap-aging"] });
      setCreditAmount("");
      setCreditReason("");
      pushNotification({ title: "Credit note approved", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const paymentMutation = useMutation({
    mutationFn: () => createProcurementPaymentRequestFromApInvoice(id),
    onSuccess: async (request) => {
      await submitProcurementPaymentRequest(request.id);
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "ap-invoices", id] });
      pushNotification({ title: "Payment request submitted for approval", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const invoice = query.data;
  const canEdit = invoice?.status === "draft";
  const submissionAttachments =
    submissionQuery.data?.attachments.map((attachment) => ({
      field_name: attachment.field_name ?? "",
    })) ?? [];
  const attachmentsReady = !invoice?.e_approval_submission_id || !submissionQuery.isLoading;
  const missingRequiredAttachments =
    invoice && formSchemaQuery.data?.fields && attachmentsReady
      ? missingRequiredAttachmentFieldLabels(
          formSchemaQuery.data.fields as EApprovalFormFieldInput[],
          submissionAttachments,
        )
      : [];

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <>
              <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
              <span className="text-muted-foreground"> / </span>
              <Link href={financePaths.apInvoices} className="hover:text-primary">
                AP invoices
              </Link>
            </>
          }
          title={invoice?.document_no ?? "AP invoice"}
          description={invoice?.vendor_invoice_no ? `Vendor invoice ${invoice.vendor_invoice_no}` : "Supplier invoice detail"}
          actions={
            invoice ? (
              <div className="flex flex-wrap gap-2">
                {invoice.status === "draft" ? (
                  <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                    <Button size="sm" variant="outline" render={<Link href={financePaths.apInvoiceEdit(id)} />}>
                      Edit draft
                    </Button>
                    <Button size="sm" onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending}>
                      {submitMutation.isPending ? "Submitting…" : "Submit for approval"}
                    </Button>
                  </PermissionGate>
                ) : null}
                <RaiseTicketButton prefill={buildProcurementApInvoiceTicketPrefill(invoice)} />
              </div>
            ) : null
          }
        />

        {query.isLoading ? <SectionCardSkeleton /> : null}
        {query.isError ? (
          <p className="text-sm text-destructive">
            Could not load AP invoice. {getErrorMessage(query.error)}
          </p>
        ) : null}
        {!query.isLoading && !query.isError && !invoice ? (
          <p className="text-sm text-destructive">AP invoice not found.</p>
        ) : null}

        {invoice ? (
          <>
            {canEdit && missingRequiredAttachments.length > 0 ? (
              <OperationalAlert
                level="warning"
                title="Submit blocked until required files are uploaded"
                description={
                  <>
                    Missing: {missingRequiredAttachments.join(", ")}.{" "}
                    <Link href={`/procurement/ap-invoices/${id}/edit`} className="font-medium text-primary hover:underline">
                      Continue editing
                    </Link>{" "}
                    to upload files and submit for approval.
                  </>
                }
              />
            ) : null}

            <TicketingRelatedTickets sourceModule="procurement_one" sourceReferenceId={id} />

            <section className="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-2 lg:grid-cols-4">
              <div>
                <p className="text-xs text-muted-foreground">Status</p>
                <div className="mt-1">
                  <ProcurementApInvoiceStatusBadge status={invoice.status} label={invoice.status_label} />
                </div>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Match</p>
                <p className="mt-1 text-sm font-medium">
                  {invoice.match_mode_label} · {invoice.match_status}
                </p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Grand total</p>
                <p className="mt-1 text-sm font-medium">
                  {invoice.grand_total.toLocaleString(undefined, { minimumFractionDigits: 2 })} {invoice.currency_code}
                </p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Due date</p>
                <p className="mt-1 text-sm font-medium">{invoice.due_date ?? "—"}</p>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <h2 className="text-base font-medium">Linked documents</h2>
              <dl className="mt-3 grid gap-2 text-sm md:grid-cols-2">
                <div>
                  <dt className="text-muted-foreground">Purchase order</dt>
                  <dd>
                    {invoice.purchase_order ? (
                      <Link href={`/procurement/pos/${invoice.purchase_order.id}`} className="text-primary hover:underline">
                        {invoice.purchase_order.document_no}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Goods receipt</dt>
                  <dd>
                    {invoice.goods_receipt ? (
                      <Link href={`/procurement/grns/${invoice.goods_receipt.id}`} className="text-primary hover:underline">
                        {invoice.goods_receipt.document_no}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </dd>
                </div>
              </dl>
              {invoice.e_approval_submission_id ? (
                <p className="mt-3 text-sm">
                  <Link href={`/e-approval/submissions/${invoice.e_approval_submission_id}`} className="text-primary hover:underline">
                    View E-Approval submission
                  </Link>
                </p>
              ) : null}
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <h2 className="text-base font-medium">Invoice lines</h2>
              <div className="mt-3 overflow-x-auto">
                <table className="min-w-full text-sm">
                  <thead className="text-left text-xs text-muted-foreground">
                    <tr>
                      <th className="pb-2 pr-4 font-medium">Description</th>
                      <th className="pb-2 pr-4 font-medium">Qty</th>
                      <th className="pb-2 pr-4 font-medium">Unit price</th>
                      <th className="pb-2 font-medium">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {invoice.lines.map((line) => (
                      <tr key={line.id} className="border-t border-border">
                        <td className="py-2 pr-4">{line.description}</td>
                        <td className="py-2 pr-4">{line.quantity_invoiced}</td>
                        <td className="py-2 pr-4">{line.unit_price.toLocaleString()}</td>
                        <td className="py-2">{line.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            {invoice.status === "approved" ? (
              <>
                <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                  <h2 className="text-base font-medium">Payment</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Open payable:{" "}
                    {(invoice.payment_balance?.open_payable ?? invoice.grand_total).toLocaleString(undefined, {
                      minimumFractionDigits: 2,
                    })}{" "}
                    {invoice.currency_code}
                  </p>
                  {(invoice.payment_balance?.open_payable ?? invoice.grand_total) > 0.01 ? (
                    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                      <Button className="mt-4" size="sm" disabled={paymentMutation.isPending} onClick={() => paymentMutation.mutate()}>
                        Request payment
                      </Button>
                    </PermissionGate>
                  ) : null}
                  {(invoice.payment_requests?.length ?? 0) > 0 ? (
                    <ul className="mt-4 space-y-2 text-sm">
                      {invoice.payment_requests!.map((request) => (
                        <li key={request.id}>
                          <Link href={`/procurement/payments/${request.id}`} className="text-primary hover:underline">
                            {request.document_no ?? "Draft"}
                          </Link>
                          {" · "}
                          {request.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })} · {request.status_label}
                        </li>
                      ))}
                    </ul>
                  ) : null}
                </section>

                <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                  <h2 className="text-base font-medium">Credit note</h2>
                <p className="mt-1 text-sm text-muted-foreground">Issue a credit note against this approved invoice.</p>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                  <div>
                    <Label htmlFor="credit_amount">Amount</Label>
                    <input
                      id="credit_amount"
                      type="number"
                      min={0}
                      step="0.01"
                      value={creditAmount}
                      onChange={(e) => setCreditAmount(e.target.value)}
                      className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    />
                  </div>
                  <div>
                    <Label htmlFor="credit_reason">Reason</Label>
                    <input
                      id="credit_reason"
                      value={creditReason}
                      onChange={(e) => setCreditReason(e.target.value)}
                      className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    />
                  </div>
                </div>
                <Button
                  className="mt-4"
                  size="sm"
                  disabled={creditMutation.isPending || !creditAmount}
                  onClick={() => creditMutation.mutate()}
                >
                  Create & approve credit note
                </Button>
                {(invoice.credit_notes?.length ?? 0) > 0 ? (
                  <ul className="mt-4 space-y-2 text-sm text-muted-foreground">
                    {invoice.credit_notes.map((note) => (
                      <li key={note.id}>
                        {note.document_no ?? note.id} — {note.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </li>
                    ))}
                  </ul>
                ) : null}
              </section>
              </>
            ) : null}
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
