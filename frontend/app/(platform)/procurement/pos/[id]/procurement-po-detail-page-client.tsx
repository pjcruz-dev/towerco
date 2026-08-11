"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ExternalLink, Mail, PackageCheck, Printer } from "lucide-react";

import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { ProcurementLifecycleActionButton } from "@/components/procurement-one/procurement-lifecycle-action-button";
import { ProcurementPoStatusBadge } from "@/components/procurement-one/procurement-po-status-badge";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import {
  cancelProcurementPo,
  fetchProcurementFormSchema,
  fetchProcurementPo,
  sendProcurementPoVendorEmail,
  submitProcurementPo,
  updateProcurementPo,
  voidProcurementPo,
} from "@/lib/api/modules/procurement-one-api";
import { fetchEApprovalSubmission } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { financeOneRoutes } from "@/lib/navigation/finance-one-routes";
import { buildProcurementPoTicketPrefill, isPoDeliveryDelayed } from "@/lib/procurement-one/ticket-prefill";
import { permissions } from "@/lib/rbac/permissions";
import { missingRequiredAttachmentFieldLabels } from "@/modules/procurement-one/submit-readiness";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = { poId: string };

function formatMoney(value: number, currency = "PHP"): string {
  return `${currency} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function ProcurementPoDetailPageClient({ poId }: Props) {
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);

  const poQuery = useQuery({
    queryKey: ["procurement-one", "po", poId],
    queryFn: () => fetchProcurementPo(poId),
    enabled: Boolean(poId),
  });

  const formSchemaQuery = useQuery({
    queryKey: ["procurement-one", "form-schema", "purchase_order", "detail"],
    queryFn: () => fetchProcurementFormSchema("purchase_order"),
    enabled: poQuery.data?.status === "draft",
    staleTime: 60_000,
  });

  const submissionQuery = useQuery({
    queryKey: ["e-approval", "submission", poQuery.data?.e_approval_submission_id, "po-detail"],
    queryFn: () => fetchEApprovalSubmission(poQuery.data!.e_approval_submission_id!),
    enabled: poQuery.data?.status === "draft" && !!poQuery.data?.e_approval_submission_id,
    staleTime: 30_000,
  });

  const submitMutation = useMutation({
    mutationFn: () => submitProcurementPo(poId),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "po", poId] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "pos"] });
      if (result.warning) pushNotification({ title: result.warning, variant: "warning" });
      pushNotification({ title: "Purchase order submitted for approval", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const cancelMutation = useMutation({
    mutationFn: (reason?: string) => cancelProcurementPo(poId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "po", poId] });
      pushNotification({ title: "Purchase order cancelled", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const voidMutation = useMutation({
    mutationFn: (reason: string) => voidProcurementPo(poId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "po", poId] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "prs"] });
      pushNotification({ title: "Purchase order voided — PR balance released", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const vendorEmailMutation = useMutation({
    mutationFn: () => sendProcurementPoVendorEmail(poId, "po_sent"),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "po", poId] });
      pushNotification({ title: "Vendor email queued", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const markSentMutation = useMutation({
    mutationFn: () => updateProcurementPo(poId, { status: "sent" }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "po", poId] });
      pushNotification({ title: "Marked as sent to vendor", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const po = poQuery.data;
  const canEdit = po?.status === "draft";
  const submissionAttachments =
    submissionQuery.data?.attachments.map((attachment) => ({
      field_name: attachment.field_name ?? "",
    })) ?? [];
  const attachmentsReady = !po?.e_approval_submission_id || !submissionQuery.isLoading;
  const missingRequiredAttachments =
    po && formSchemaQuery.data?.fields && attachmentsReady
      ? missingRequiredAttachmentFieldLabels(
          formSchemaQuery.data.fields as EApprovalFormFieldInput[],
          submissionAttachments,
        )
      : [];
  const printHref = po?.e_approval_submission_id
    ? `/e-approval/submissions/${po.e_approval_submission_id}/print`
    : null;
  const deliveryDelayed = po ? isPoDeliveryDelayed(po) : false;

  if (poQuery.isLoading) return <SectionCardSkeleton />;
  if (poQuery.isError) {
    return (
      <p className="text-sm text-destructive">
        Could not load purchase order. {getErrorMessage(poQuery.error)}
      </p>
    );
  }
  if (!po) return <p className="text-sm text-destructive">Purchase order not found.</p>;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement/pos" className="hover:text-primary">
              Purchase orders
            </Link>
          }
          title={po.supplier ?? po.vendor_name ?? "Purchase order"}
          description={
            <span className="inline-flex flex-wrap items-center gap-2">
              <ProcurementPoStatusBadge status={po.status} label={po.status_label} />
              <span className="text-muted-foreground">{po.document_no ?? "Draft"}</span>
            </span>
          }
          actions={
            <div className="flex flex-wrap gap-2">
              {canEdit ? (
                <Button size="sm" variant="outline" render={<Link href={`/procurement/pos/${po.id}/edit`} />}>
                  Edit draft
                </Button>
              ) : null}
              {canEdit ? (
                <Button size="sm" onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending}>
                  Submit for approval
                </Button>
              ) : null}
              {printHref ? (
                <Button size="sm" variant="outline" render={<Link href={printHref} target="_blank" />}>
                  <Printer className="mr-1.5 h-4 w-4" aria-hidden />
                  Print
                </Button>
              ) : null}
              {po.status === "approved" || po.status === "sent" ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => vendorEmailMutation.mutate()}
                    disabled={vendorEmailMutation.isPending}
                  >
                    <Mail className="mr-1.5 h-4 w-4" aria-hidden />
                    Email vendor
                  </Button>
                </PermissionGate>
              ) : null}
              {po.status === "approved" ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                  <Button size="sm" variant="outline" onClick={() => markSentMutation.mutate()} disabled={markSentMutation.isPending}>
                    Mark sent
                  </Button>
                </PermissionGate>
              ) : null}
              {po.status === "approved" || po.status === "sent" || po.status === "partially_received" || po.status === "received" ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                  <Button size="sm" variant="outline" render={<Link href={`/procurement/pos/${po.id}/ap-invoices/new`} />}>
                    New AP invoice
                  </Button>
                </PermissionGate>
              ) : null}
              {po.status === "approved" || po.status === "sent" || po.status === "partially_received" ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                  <Button size="sm" render={<Link href={`/procurement/pos/${po.id}/grns/new`} />}>
                    <PackageCheck className="mr-1.5 h-4 w-4" aria-hidden />
                    Receive goods
                  </Button>
                </PermissionGate>
              ) : null}
              {po ? (
                <RaiseTicketButton
                  prefill={buildProcurementPoTicketPrefill(po, { deliveryDelay: deliveryDelayed })}
                  variant={deliveryDelayed ? "default" : "outline"}
                />
              ) : null}
              {po.status === "draft" || po.status === "pending_approval" ? (
                <ProcurementLifecycleActionButton
                  action="cancel"
                  label="Cancel"
                  pending={cancelMutation.isPending}
                  onConfirm={(reason) => cancelMutation.mutate(reason || undefined)}
                />
              ) : null}
              {po.status === "approved" || po.status === "sent" ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                  <ProcurementLifecycleActionButton
                    action="void"
                    label="Void"
                    pending={voidMutation.isPending}
                    onConfirm={(reason) => voidMutation.mutate(reason)}
                  />
                </PermissionGate>
              ) : null}
            </div>
          }
        />

        {deliveryDelayed ? (
          <section className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-base font-medium text-foreground">Delivery delay</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Expected delivery was {po.delivery_date}. Raise a service desk ticket to follow up with the vendor.
                </p>
              </div>
              <RaiseTicketButton prefill={buildProcurementPoTicketPrefill(po, { deliveryDelay: true })} />
            </div>
          </section>
        ) : null}

        <TicketingRelatedTickets
          sourceModule="procurement_one"
          sourceReferenceId={poId}
          linkedModule="procurement_one"
          title="Related tickets (PO, receipts & invoices)"
        />

        {canEdit && missingRequiredAttachments.length > 0 ? (
          <OperationalAlert
            level="warning"
            title="Submit blocked until required files are uploaded"
            description={
              <>
                Missing: {missingRequiredAttachments.join(", ")}.{" "}
                <Link href={`/procurement/pos/${po.id}/edit`} className="font-medium text-primary hover:underline">
                  Continue editing
                </Link>{" "}
                to upload files and submit for approval.
              </>
            }
          />
        ) : null}

        {canEdit && (!po.delivery_date || !po.delivery_location) ? (
          <OperationalAlert
            level="warning"
            title="Delivery details required"
            description={
              <>
                Set required delivery date and delivery location before submitting.{" "}
                <Link href={`/procurement/pos/${po.id}/edit`} className="font-medium text-primary hover:underline">
                  Edit draft
                </Link>
              </>
            }
          />
        ) : null}

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <dl className="grid gap-3 text-sm md:grid-cols-2">
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Grand total (VAT incl.)</dt>
              <dd className="mt-1 font-medium tabular-nums">{formatMoney(po.grand_total, po.currency_code)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Vendor</dt>
              <dd className="mt-1">{po.vendor_name ?? po.vendor_code ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Vatable amount</dt>
              <dd className="mt-1 tabular-nums">{formatMoney(po.vatable_amount, po.currency_code)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">VAT ({po.vat_rate}%)</dt>
              <dd className="mt-1 tabular-nums">{formatMoney(po.vat_amount, po.currency_code)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Requestor</dt>
              <dd className="mt-1">{po.requestor?.name ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Delivery date</dt>
              <dd className="mt-1">{po.delivery_date ?? "—"}</dd>
            </div>
          </dl>
        </section>

        {po.contract ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Vendor contract</h2>
            <dl className="mt-3 grid gap-3 text-sm md:grid-cols-2">
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Contract</dt>
                <dd className="mt-1">
                  <Link href={financeOneRoutes.contract(po.contract.id)} className="font-medium text-primary hover:underline">
                    {po.contract.document_no ?? po.contract.title}
                  </Link>
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Spend ceiling</dt>
                <dd className="mt-1 tabular-nums">{formatMoney(po.contract.spend_ceiling ?? 0, po.currency_code)}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Committed</dt>
                <dd className="mt-1 tabular-nums">{formatMoney(po.contract.committed_po_amount, po.currency_code)}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Available</dt>
                <dd className="mt-1 tabular-nums">
                  {po.contract.available_spend !== null ? formatMoney(po.contract.available_spend, po.currency_code) : "—"}
                </dd>
              </div>
            </dl>
          </section>
        ) : null}

        {po.purchase_requisitions.length > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Linked purchase requisitions</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {po.purchase_requisitions.map((pr) => (
                <li key={pr.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                  <Link href={`/procurement/prs/${pr.id}`} className="font-medium text-primary hover:underline">
                    {pr.document_no ?? pr.title ?? pr.id}
                  </Link>
                  <span className="tabular-nums text-muted-foreground">{formatMoney(pr.allocated_amount, po.currency_code)}</span>
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        {po.line_receipt_summary && po.line_receipt_summary.length > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-base font-medium">Receipt summary</h2>
              {(po.goods_receipt_count ?? 0) > 0 ? (
                <Link href="/procurement/grns" className="text-sm text-primary hover:underline">
                  View goods receipts ({po.goods_receipt_count})
                </Link>
              ) : null}
            </div>
            <div className="mt-3 overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-muted-foreground">
                    <th className="py-2 pr-3 font-medium">Line</th>
                    <th className="py-2 pr-3 font-medium">Ordered</th>
                    <th className="py-2 pr-3 font-medium">Received</th>
                    <th className="py-2 font-medium">Remaining</th>
                  </tr>
                </thead>
                <tbody>
                  {po.line_receipt_summary.map((row) => (
                    <tr key={row.po_line_id} className="border-b border-border/60">
                      <td className="py-2 pr-3">{row.description}</td>
                      <td className="py-2 pr-3 tabular-nums">{row.quantity_ordered}</td>
                      <td className="py-2 pr-3 tabular-nums">{row.quantity_received}</td>
                      <td className="py-2 tabular-nums">{row.quantity_remaining}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        ) : null}

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-base font-medium">Line items</h2>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted-foreground">
                  <th className="py-2 pr-3 font-medium">Item</th>
                  <th className="py-2 pr-3 font-medium">Description</th>
                  <th className="py-2 pr-3 font-medium">UOM</th>
                  <th className="py-2 pr-3 font-medium">Qty</th>
                  <th className="py-2 pr-3 font-medium">Unit price</th>
                  <th className="py-2 pr-3 font-medium">Discount</th>
                  <th className="py-2 font-medium">Amount</th>
                </tr>
              </thead>
              <tbody>
                {po.lines.map((line) => (
                  <tr key={line.id ?? `${line.description}-${line.line_order}`} className="border-b border-border/60">
                    <td className="py-2 pr-3">{line.item ?? "—"}</td>
                    <td className="py-2 pr-3">{line.description}</td>
                    <td className="py-2 pr-3">{line.uom ?? "EA"}</td>
                    <td className="py-2 pr-3 tabular-nums">{line.quantity}</td>
                    <td className="py-2 pr-3 tabular-nums">{formatMoney(line.unit_price, po.currency_code)}</td>
                    <td className="py-2 pr-3 tabular-nums">{formatMoney(line.discount ?? 0, po.currency_code)}</td>
                    <td className="py-2 tabular-nums">
                      {formatMoney(line.amount ?? line.quantity * line.unit_price - (line.discount ?? 0), po.currency_code)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-base font-medium">Tax summary</h2>
          <dl className="mt-3 grid gap-2 text-sm md:grid-cols-2">
            <div className="flex justify-between gap-4">
              <dt className="text-muted-foreground">Vatable sales</dt>
              <dd className="tabular-nums">{formatMoney(po.vatable_amount, po.currency_code)}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-muted-foreground">VAT amount</dt>
              <dd className="tabular-nums">{formatMoney(po.vat_amount, po.currency_code)}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-muted-foreground">Total VAT inclusive</dt>
              <dd className="tabular-nums">{formatMoney(po.total_vat_inclusive, po.currency_code)}</dd>
            </div>
            <div className="flex justify-between gap-4 font-medium">
              <dt>Grand total</dt>
              <dd className="tabular-nums">{formatMoney(po.grand_total, po.currency_code)}</dd>
            </div>
          </dl>
        </section>

        {po.lifecycle_events && po.lifecycle_events.length > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Lifecycle audit</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {po.lifecycle_events.map((event) => (
                <li key={event.id} className="rounded-lg border border-border px-3 py-2">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-medium capitalize">{event.action.replaceAll("_", " ")}</span>
                    <span className="text-xs text-muted-foreground">{event.created_at ?? ""}</span>
                  </div>
                  {event.reason ? <p className="mt-1 text-muted-foreground">{event.reason}</p> : null}
                  {event.actor ? <p className="mt-1 text-xs text-muted-foreground">By {event.actor.name}</p> : null}
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        {po.e_approval_submission_id ? (
          <Link
            href={`/e-approval/submissions/${po.e_approval_submission_id}`}
            className={cn(buttonVariants({ variant: "link", size: "sm" }), "inline-flex items-center gap-1 px-0")}
          >
            View E-Approval submission
            <ExternalLink className="h-3.5 w-3.5" aria-hidden />
          </Link>
        ) : null}
      </div>
    </PermissionGate>
  );
}
