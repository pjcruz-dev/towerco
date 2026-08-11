"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { MapPin, PackageCheck } from "lucide-react";

import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { ProcurementPoStatusBadge } from "@/components/procurement-one/procurement-po-status-badge";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { createProcurementGrnFromPo, fetchProcurementPo } from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { poId: string };

type LineDraft = {
  po_line_id: string;
  description: string;
  uom: string;
  quantity_ordered: number;
  quantity_remaining: number;
  quantity_received: number;
};

export function ProcurementGrnFromPoComposePageClient({ poId }: Props) {
  const router = useRouter();
  const pushNotification = useNotificationStore((s) => s.push);
  const [notes, setNotes] = useState("");
  const [postNow, setPostNow] = useState(true);
  const [gpsLatitude, setGpsLatitude] = useState<number | null>(null);
  const [gpsLongitude, setGpsLongitude] = useState<number | null>(null);
  const [lines, setLines] = useState<LineDraft[]>([]);

  const poQuery = useQuery({
    queryKey: ["procurement-one", "po", poId],
    queryFn: () => fetchProcurementPo(poId),
  });

  useEffect(() => {
    const po = poQuery.data;
    if (!po?.line_receipt_summary) return;
    setLines(
      po.line_receipt_summary
        .filter((row) => row.quantity_remaining > 0)
        .map((row) => ({
          po_line_id: row.po_line_id,
          description: row.description,
          uom: "EA",
          quantity_ordered: row.quantity_ordered,
          quantity_remaining: row.quantity_remaining,
          quantity_received: row.quantity_remaining,
        })),
    );
  }, [poQuery.data]);

  const createMutation = useMutation({
    mutationFn: () =>
      createProcurementGrnFromPo(poId, {
        post: postNow,
        notes: notes.trim() || undefined,
        gps_latitude: gpsLatitude,
        gps_longitude: gpsLongitude,
        lines: lines
          .filter((line) => line.quantity_received > 0)
          .map((line) => ({
            po_line_id: line.po_line_id,
            quantity_received: line.quantity_received,
          })),
      }),
    onSuccess: (result) => {
      if (result.warning) pushNotification({ title: result.warning, variant: "warning" });
      pushNotification({
        title: postNow ? "Goods receipt posted" : "Goods receipt draft saved",
        variant: "success",
      });
      if (postNow && result.grn.mismatches && result.grn.mismatches.length > 0) {
        pushNotification({
          title: "Receipt mismatch detected — review or raise a ticket from the GRN detail page.",
          variant: "warning",
        });
      }
      router.push(`/procurement/grns/${result.grn.id}`);
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const captureGps = () => {
    if (!navigator.geolocation) {
      pushNotification({ title: "Geolocation is not available in this browser.", variant: "destructive" });
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setGpsLatitude(position.coords.latitude);
        setGpsLongitude(position.coords.longitude);
        pushNotification({ title: "GPS location captured", variant: "success" });
      },
      () => pushNotification({ title: "Could not capture GPS location.", variant: "destructive" }),
      { enableHighAccuracy: true, timeout: 10000 },
    );
  };

  const canReceive = useMemo(() => {
    const po = poQuery.data;
    return (
      po &&
      ["approved", "sent", "partially_received"].includes(po.status) &&
      lines.some((line) => line.quantity_received > 0)
    );
  }, [poQuery.data, lines]);

  if (poQuery.isLoading) return <SectionCardSkeleton />;
  const po = poQuery.data;
  if (!po) return <p className="text-sm text-destructive">Could not load purchase order.</p>;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href={`/procurement/pos/${po.id}`} className="hover:text-primary">
              {po.document_no ?? "Purchase order"}
            </Link>
          }
          title="Receive goods"
          description={
            <span className="inline-flex flex-wrap items-center gap-2">
              <ProcurementPoStatusBadge status={po.status} label={po.status_label} />
              <span>{po.supplier ?? po.vendor_name ?? ""}</span>
            </span>
          }
        />

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-base font-medium">Delivery evidence</h2>
              <p className="mt-1 text-sm text-muted-foreground">Optional site GPS and notes for field delivery.</p>
            </div>
            <Button size="sm" variant="outline" type="button" onClick={captureGps}>
              <MapPin className="mr-1.5 h-4 w-4" aria-hidden />
              Capture GPS
            </Button>
          </div>
          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="gps_lat">Latitude</Label>
              <Input
                id="gps_lat"
                type="number"
                step="any"
                value={gpsLatitude ?? ""}
                onChange={(e) => setGpsLatitude(e.target.value === "" ? null : Number(e.target.value))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="gps_lng">Longitude</Label>
              <Input
                id="gps_lng"
                type="number"
                step="any"
                value={gpsLongitude ?? ""}
                onChange={(e) => setGpsLongitude(e.target.value === "" ? null : Number(e.target.value))}
              />
            </div>
          </div>
          <div className="mt-4 space-y-2">
            <Label htmlFor="grn_notes">Notes</Label>
            <Textarea
              id="grn_notes"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Delivery condition, gate pass, receiver name…"
              className="min-h-20"
            />
          </div>
        </section>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-base font-medium">Quantities to receive</h2>
          {lines.length === 0 ? (
            <p className="mt-3 text-sm text-muted-foreground">All PO lines are fully received.</p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-muted-foreground">
                    <th className="py-2 pr-3 font-medium">Description</th>
                    <th className="py-2 pr-3 font-medium">Remaining</th>
                    <th className="py-2 font-medium">Receive now</th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((line, index) => (
                    <tr key={line.po_line_id} className="border-b border-border/60">
                      <td className="py-2 pr-3">{line.description}</td>
                      <td className="py-2 pr-3 tabular-nums">{line.quantity_remaining}</td>
                      <td className="py-2">
                        <Input
                          type="number"
                          min={0}
                          step="any"
                          value={line.quantity_received}
                          onChange={(e) => {
                            const value = Number(e.target.value);
                            setLines((current) =>
                              current.map((row, i) => (i === index ? { ...row, quantity_received: value } : row)),
                            );
                          }}
                          className="max-w-[8rem]"
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <label className="inline-flex items-center gap-2 text-sm">
            <Checkbox
              checked={postNow}
              onCheckedChange={(v) => setPostNow(v === true)}
              className="size-4"
            />
            Post immediately (assign GRN number and update PO status)
          </label>
          <Button
            onClick={() => createMutation.mutate()}
            disabled={!canReceive || createMutation.isPending}
          >
            <PackageCheck className="mr-1.5 h-4 w-4" aria-hidden />
            {postNow ? "Post goods receipt" : "Save draft"}
          </Button>
        </div>
      </div>
    </PermissionGate>
  );
}
