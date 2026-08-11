"use client";

import Link from "next/link";
import { useRef } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Camera, PackageCheck, Printer } from "lucide-react";

import { ProcurementAttachmentRow } from "@/components/procurement-one/procurement-attachment-row";
import { ProcurementGrnStatusBadge } from "@/components/procurement-one/procurement-grn-status-badge";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import {
  fetchProcurementGrn,
  postProcurementGrn,
  uploadProcurementGrnAttachment,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import {
  buildProcurementGrnMismatchTicketPrefill,
  buildProcurementGrnTicketPrefill,
  grnHasReceiptMismatches,
} from "@/lib/procurement-one/ticket-prefill";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { grnId: string };

export function ProcurementGrnDetailPageClient({ grnId }: Props) {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);

  const grnQuery = useQuery({
    queryKey: ["procurement-one", "grn", grnId],
    queryFn: () => fetchProcurementGrn(grnId),
    enabled: Boolean(grnId),
  });

  const postMutation = useMutation({
    mutationFn: () => postProcurementGrn(grnId),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "grn", grnId] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "grns"] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "po", result.grn.po_id] });
      if (result.warning) pushNotification({ title: result.warning, variant: "warning" });
      pushNotification({ title: "Goods receipt posted", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadProcurementGrnAttachment(grnId, file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "grn", grnId] });
      pushNotification({ title: "Delivery photo uploaded", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const grn = grnQuery.data;
  const isDraft = grn?.status === "draft";
  const isPosted = grn?.status === "posted";
  const hasMismatches = grn ? grnHasReceiptMismatches(grn) : false;
  const printHref = isPosted ? `/procurement/grns/${grnId}/print` : null;

  if (grnQuery.isLoading) return <SectionCardSkeleton />;
  if (grnQuery.isError) {
    return (
      <p className="text-sm text-destructive">
        Could not load goods receipt. {getErrorMessage(grnQuery.error)}
      </p>
    );
  }
  if (!grn) return <p className="text-sm text-destructive">Goods receipt not found.</p>;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement/grns" className="hover:text-primary">
              Goods receipts
            </Link>
          }
          title={grn.document_no ?? "Draft goods receipt"}
          description={
            <span className="inline-flex flex-wrap items-center gap-2">
              <ProcurementGrnStatusBadge status={grn.status} label={grn.status_label} />
              {grn.po_document_no ? (
                <Link href={`/procurement/pos/${grn.po_id}`} className="text-primary hover:underline">
                  PO {grn.po_document_no}
                </Link>
              ) : null}
            </span>
          }
          actions={
            <div className="flex flex-wrap gap-2">
              {printHref ? (
                <Button size="sm" variant="outline" render={<Link href={printHref} target="_blank" />}>
                  <Printer className="mr-1.5 h-4 w-4" aria-hidden />
                  Print
                </Button>
              ) : null}
              {grn ? <RaiseTicketButton prefill={buildProcurementGrnTicketPrefill(grn)} /> : null}
              {grn && hasMismatches ? (
                <RaiseTicketButton
                  prefill={buildProcurementGrnMismatchTicketPrefill(grn)}
                  variant="default"
                />
              ) : null}
              {isDraft ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
                  <div className="flex flex-wrap gap-2">
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={(event) => {
                      const file = event.target.files?.[0];
                      if (file) uploadMutation.mutate(file);
                      event.target.value = "";
                    }}
                  />
                  <Button
                    size="sm"
                    variant="outline"
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={uploadMutation.isPending}
                  >
                    <Camera className="mr-1.5 h-4 w-4" aria-hidden />
                    Add photo
                  </Button>
                  <Button size="sm" onClick={() => postMutation.mutate()} disabled={postMutation.isPending}>
                    <PackageCheck className="mr-1.5 h-4 w-4" aria-hidden />
                    Post receipt
                  </Button>
                </div>
              </PermissionGate>
              ) : null}
            </div>
          }
        />

        {hasMismatches ? (
          <section className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
            <h2 className="text-base font-medium">Receipt mismatches</h2>
            <p className="mt-1 text-amber-900 dark:text-amber-200">
              Quantities differ from the open PO balance or tolerance policy. Review before closing the delivery.
            </p>
            <ul className="mt-3 space-y-2">
              {grn.receipt_warning ? <li>{grn.receipt_warning}</li> : null}
              {(grn.mismatches ?? []).map((row, index) => (
                <li key={`${row.type}-${index}`}>
                  <span className="font-medium capitalize">{row.severity}</span> — {row.message}
                </li>
              ))}
            </ul>
            {grn && hasMismatches ? (
              <div className="mt-4">
                <RaiseTicketButton prefill={buildProcurementGrnMismatchTicketPrefill(grn)} />
              </div>
            ) : null}
          </section>
        ) : null}

        <TicketingRelatedTickets sourceModule="procurement_one" sourceReferenceId={grnId} />

        {isPosted && (grn.stock_movements?.length ?? 0) > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium text-foreground">Stock received</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Inventory movements recorded when this goods receipt was posted
              {grn.inventory_location ? ` into ${grn.inventory_location.name}` : ""}.
            </p>
            <div className="mt-3 overflow-x-auto">
              <table className="w-full min-w-[520px] text-left text-sm">
                <thead>
                  <tr className="border-b border-border text-xs text-muted-foreground">
                    <th className="py-2 pr-3 font-medium">Item</th>
                    <th className="py-2 pr-3 font-medium">Qty</th>
                    <th className="py-2 font-medium">Location</th>
                  </tr>
                </thead>
                <tbody>
                  {(grn.stock_movements ?? []).map((row) => (
                    <tr key={row.id} className="border-b border-border/70">
                      <td className="py-2 pr-3">{row.description}</td>
                      <td className="py-2 pr-3 tabular-nums">{row.quantity}</td>
                      <td className="py-2">{row.location?.name ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Link href="/procurement/inventory" className="mt-3 inline-block text-sm text-primary hover:underline">
              Open inventory workspace
            </Link>
          </section>
        ) : null}

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <dl className="grid gap-3 text-sm md:grid-cols-2">
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Supplier</dt>
              <dd className="mt-1">{grn.po_supplier ?? grn.purchase_order?.supplier ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Received by</dt>
              <dd className="mt-1">{grn.received_by?.name ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Received at</dt>
              <dd className="mt-1">{grn.received_at ? new Date(grn.received_at).toLocaleString() : "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Posted at</dt>
              <dd className="mt-1">{grn.posted_at ? new Date(grn.posted_at).toLocaleString() : "—"}</dd>
            </div>
            {grn.site_id ? (
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Site</dt>
                <dd className="mt-1 font-mono text-xs">{grn.site_id}</dd>
              </div>
            ) : null}
            {grn.gps_latitude != null && grn.gps_longitude != null ? (
              <div>
                <dt className="text-xs font-medium text-muted-foreground">GPS</dt>
                <dd className="mt-1 font-mono text-xs">
                  {grn.gps_latitude.toFixed(6)}, {grn.gps_longitude.toFixed(6)}
                  {grn.gps_accuracy_meters != null ? ` (±${grn.gps_accuracy_meters}m)` : ""}
                </dd>
              </div>
            ) : null}
          </dl>
          {grn.notes ? <p className="mt-4 text-sm text-muted-foreground">{grn.notes}</p> : null}
        </section>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-base font-medium">Received lines</h2>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted-foreground">
                  <th className="py-2 pr-3 font-medium">Description</th>
                  <th className="py-2 pr-3 font-medium">UOM</th>
                  <th className="py-2 pr-3 font-medium">Ordered</th>
                  <th className="py-2 font-medium">Received</th>
                </tr>
              </thead>
              <tbody>
                {grn.lines.map((line) => (
                  <tr key={line.id ?? line.po_line_id} className="border-b border-border/60">
                    <td className="py-2 pr-3">{line.description}</td>
                    <td className="py-2 pr-3">{line.uom ?? "EA"}</td>
                    <td className="py-2 pr-3 tabular-nums">{line.quantity_ordered}</td>
                    <td className="py-2 tabular-nums">{line.quantity_received}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {grn.purchase_order?.line_receipt_summary && grn.purchase_order.line_receipt_summary.length > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">PO receipt balance</h2>
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
                  {grn.purchase_order.line_receipt_summary.map((row) => (
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

        {grn.attachments.length > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Delivery photos</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {grn.attachments.map((attachment) => (
                <ProcurementAttachmentRow
                  key={attachment.id}
                  fileName={attachment.file_name}
                  fieldName={attachment.field_name}
                  mimeType={attachment.mime_type}
                  grnId={grnId}
                  grnAttachmentId={attachment.id}
                />
              ))}
            </ul>
          </section>
        ) : null}
      </div>
    </PermissionGate>
  );
}
