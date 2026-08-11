"use client";

import Link from "next/link";
import { useRef } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ExternalLink, FileStack, Gavel, Paperclip, Printer } from "lucide-react";

import { ProcurementAttachmentRow } from "@/components/procurement-one/procurement-attachment-row";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { ProcurementPrStatusBadge } from "@/components/procurement-one/procurement-pr-status-badge";
import { ProcurementLifecycleActionButton } from "@/components/procurement-one/procurement-lifecycle-action-button";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import {
  cancelProcurementPr,
  createProcurementRfqFromPr,
  fetchProcurementFormSchema,
  fetchProcurementPr,
  uploadProcurementPrAttachment,
  voidProcurementPr,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { isProcurementPlanFeatureEnabled } from "@/lib/procurement/procurement-plan-features";
import { buildProcurementPrTicketPrefill } from "@/lib/procurement-one/ticket-prefill";
import { permissions } from "@/lib/rbac/permissions";
import { useProcurementPlanFeatures } from "@/hooks/use-procurement-plan-features";
import { missingRequiredAttachmentFieldLabels } from "@/modules/procurement-one/submit-readiness";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = { prId: string };

function formatMoney(value: number, currency = "PHP"): string {
  return `${currency} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function ProcurementPrDetailPageClient({ prId }: Props) {
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const planFeaturesQuery = useProcurementPlanFeatures();

  const prQuery = useQuery({
    queryKey: ["procurement-one", "pr", prId],
    queryFn: () => fetchProcurementPr(prId),
    enabled: Boolean(prId),
  });

  const formSchemaQuery = useQuery({
    queryKey: ["procurement-one", "form-schema", "purchase_requisition", "detail"],
    queryFn: () => fetchProcurementFormSchema("purchase_requisition"),
    enabled: prQuery.data?.status === "draft",
    staleTime: 60_000,
  });

  const cancelMutation = useMutation({
    mutationFn: (reason?: string) => cancelProcurementPr(prId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "pr", prId] });
      pushNotification({ title: "Purchase requisition cancelled", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const voidMutation = useMutation({
    mutationFn: (reason: string) => voidProcurementPr(prId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "pr", prId] });
      pushNotification({ title: "Purchase requisition voided", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadProcurementPrAttachment(prId, file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "pr", prId] });
      pushNotification({ title: "Attachment uploaded", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const rfqMutation = useMutation({
    mutationFn: () => createProcurementRfqFromPr(prId),
    onSuccess: (rfq) => {
      pushNotification({ title: "RFQ created", variant: "success" });
      window.location.href = `/procurement/rfqs/${rfq.id}`;
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const pr = prQuery.data;
  const canEdit = pr?.status === "draft";
  const missingRequiredAttachments =
    pr && formSchemaQuery.data?.fields
      ? missingRequiredAttachmentFieldLabels(
          formSchemaQuery.data.fields as EApprovalFormFieldInput[],
          pr.attachments,
        )
      : [];
  const canCreatePo =
    Boolean(pr) &&
    (pr.status === "approved" || pr.status === "converted") &&
    (pr.open_po_balance == null || pr.open_po_balance > 0);
  const canCreateRfq =
    Boolean(pr) &&
    (pr.status === "approved" || pr.status === "converted") &&
    !pr.active_rfq &&
    isProcurementPlanFeatureEnabled(planFeaturesQuery.data, "rfq_sourcing");
  const printHref = pr?.e_approval_submission_id
    ? `/e-approval/submissions/${pr.e_approval_submission_id}/print`
    : null;

  if (prQuery.isLoading) return <SectionCardSkeleton />;
  if (prQuery.isError) {
    return (
      <p className="text-sm text-destructive">
        Could not load purchase requisition. {getErrorMessage(prQuery.error)}
      </p>
    );
  }
  if (!pr) return <p className="text-sm text-destructive">Purchase requisition not found.</p>;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement/prs" className="hover:text-primary">
              Purchase requisitions
            </Link>
          }
          title={pr.title}
          description={
            <span className="inline-flex flex-wrap items-center gap-2">
              <ProcurementPrStatusBadge status={pr.status} label={pr.status_label} />
              <span className="text-muted-foreground">{pr.document_no ?? "Draft"}</span>
            </span>
          }
          actions={
            <div className="flex flex-wrap gap-2">
              {canEdit ? (
                <Button size="sm" variant="outline" render={<Link href={`/procurement/prs/${pr.id}/edit`} />}>
                  Edit draft
                </Button>
              ) : null}
              {canCreateRfq ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                  <Button size="sm" variant="outline" disabled={rfqMutation.isPending} onClick={() => rfqMutation.mutate()}>
                    <Gavel className="mr-1.5 h-4 w-4" aria-hidden />
                    Create RFQ
                  </Button>
                </PermissionGate>
              ) : null}
              {pr.active_rfq ? (
                <Button
                  size="sm"
                  variant="outline"
                  render={<Link href={`/procurement/rfqs/${pr.active_rfq.id}`} />}
                >
                  <Gavel className="mr-1.5 h-4 w-4" aria-hidden />
                  Open RFQ {pr.active_rfq.document_no ?? pr.active_rfq.status_label}
                </Button>
              ) : null}
              {canCreatePo ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                  <Button size="sm" render={<Link href={`/procurement/prs/${pr.id}/pos/new`} />}>
                    <FileStack className="mr-1.5 h-4 w-4" aria-hidden />
                    Create PO
                  </Button>
                </PermissionGate>
              ) : null}
              {printHref ? (
                <Button size="sm" variant="outline" render={<Link href={printHref} target="_blank" />}>
                  <Printer className="mr-1.5 h-4 w-4" aria-hidden />
                  Print
                </Button>
              ) : null}
              {pr ? <RaiseTicketButton prefill={buildProcurementPrTicketPrefill(pr)} /> : null}
              {pr.status === "draft" || pr.status === "pending_approval" ? (
                <ProcurementLifecycleActionButton
                  action="cancel"
                  label="Cancel"
                  pending={cancelMutation.isPending}
                  onConfirm={(reason) => cancelMutation.mutate(reason || undefined)}
                />
              ) : null}
              {pr.status === "approved" ? (
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

        <TicketingRelatedTickets sourceModule="procurement_one" sourceReferenceId={prId} />

        {canEdit && missingRequiredAttachments.length > 0 ? (
          <OperationalAlert
            level="warning"
            title="Submit blocked until required files are uploaded"
            description={
              <>
                Missing: {missingRequiredAttachments.join(", ")}.{" "}
                <Link href={`/procurement/prs/${pr.id}/edit`} className="font-medium text-primary hover:underline">
                  Continue editing
                </Link>{" "}
                to upload files and submit for approval.
              </>
            }
          />
        ) : null}

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <dl className="grid gap-3 text-sm md:grid-cols-2">
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Estimated total</dt>
              <dd className="mt-1 font-medium tabular-nums">{formatMoney(pr.estimated_total, pr.currency)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Department</dt>
              <dd className="mt-1">{pr.department ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Open PO balance</dt>
              <dd className="mt-1 tabular-nums">
                {pr.open_po_balance != null ? formatMoney(pr.open_po_balance, pr.currency) : "—"}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Requestor</dt>
              <dd className="mt-1">{pr.requestor?.name ?? "—"}</dd>
            </div>
          </dl>
        </section>

        {pr.budget_check.policy_enabled ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Budget check</h2>
            <dl className="mt-3 grid gap-3 text-sm md:grid-cols-3">
              <div>
                <dt className="text-xs text-muted-foreground">Budget total</dt>
                <dd className="mt-1 tabular-nums">{pr.budget_check.budget_total != null ? formatMoney(pr.budget_check.budget_total) : "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">Committed</dt>
                <dd className="mt-1 tabular-nums">{pr.budget_check.committed != null ? formatMoney(pr.budget_check.committed) : "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">Available</dt>
                <dd className="mt-1 tabular-nums">{pr.budget_check.available != null ? formatMoney(pr.budget_check.available) : "—"}</dd>
              </div>
            </dl>
          </section>
        ) : null}

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-base font-medium">Line items</h2>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted-foreground">
                  <th className="py-2 pr-3 font-medium">Description</th>
                  <th className="py-2 pr-3 font-medium">Qty</th>
                  <th className="py-2 pr-3 font-medium">Unit price</th>
                  <th className="py-2 font-medium">Amount</th>
                </tr>
              </thead>
              <tbody>
                {pr.lines.map((line) => (
                  <tr key={line.id ?? `${line.description}-${line.line_order}`} className="border-b border-border/60">
                    <td className="py-2 pr-3">{line.description}</td>
                    <td className="py-2 pr-3 tabular-nums">{line.quantity}</td>
                    <td className="py-2 pr-3 tabular-nums">{formatMoney(line.unit_price, pr.currency)}</td>
                    <td className="py-2 tabular-nums">{formatMoney(line.amount ?? line.quantity * line.unit_price, pr.currency)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {pr.justification ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Justification</h2>
            <p className="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">{pr.justification}</p>
          </section>
        ) : null}

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-base font-medium">Attachments</h2>
            {canEdit ? (
              <>
                <input
                  ref={fileInputRef}
                  type="file"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) uploadMutation.mutate(file);
                    e.target.value = "";
                  }}
                />
                <Button size="sm" variant="outline" type="button" onClick={() => fileInputRef.current?.click()}>
                  <Paperclip className="mr-1.5 h-4 w-4" aria-hidden />
                  Upload quote
                </Button>
              </>
            ) : null}
          </div>
          {pr.attachments.length === 0 ? (
            <p className="mt-3 text-sm text-muted-foreground">No attachments.</p>
          ) : (
            <ul className="mt-3 space-y-2 text-sm">
              {pr.attachments.map((attachment) => (
                <ProcurementAttachmentRow
                  key={attachment.id}
                  fileName={attachment.file_name}
                  fieldName={attachment.field_name}
                  mimeType={attachment.mime_type}
                  eApprovalAttachmentId={attachment.e_approval_attachment_id}
                />
              ))}
            </ul>
          )}
        </section>

        {pr.lifecycle_events && pr.lifecycle_events.length > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Lifecycle audit</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {pr.lifecycle_events.map((event) => (
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

        {pr.e_approval_submission_id ? (
          <Link
            href={`/e-approval/submissions/${pr.e_approval_submission_id}`}
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
