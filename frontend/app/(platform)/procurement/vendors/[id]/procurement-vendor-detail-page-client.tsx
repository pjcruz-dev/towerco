"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { PermissionGate } from "@/components/layout/permission-gate";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { fetchProcurementVendor } from "@/lib/api/modules/procurement-one-api";
import { buildProcurementVendorTicketPrefill } from "@/lib/procurement-one/ticket-prefill";
import { permissions } from "@/lib/rbac/permissions";

type Props = { vendorId: string };

export function ProcurementVendorDetailPageClient({ vendorId }: Props) {
  const vendorQuery = useQuery({
    queryKey: ["procurement-one", "vendor", vendorId],
    queryFn: () => fetchProcurementVendor(vendorId),
    enabled: Boolean(vendorId),
  });

  const vendor = vendorQuery.data;

  if (vendorQuery.isLoading) return <SectionCardSkeleton />;
  if (!vendor) return <p className="text-sm text-destructive">Could not load vendor.</p>;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneVendorsView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement/vendors" className="hover:text-primary">
              Vendors
            </Link>
          }
          title={vendor.company_name}
          description={
            <span className="inline-flex flex-wrap items-center gap-2">
              <span>{vendor.vendor_code}</span>
              <span className="text-muted-foreground">·</span>
              <span className="capitalize">{vendor.accreditation_status_label ?? vendor.accreditation_status}</span>
            </span>
          }
          actions={<RaiseTicketButton prefill={buildProcurementVendorTicketPrefill(vendor)} />}
        />

        <TicketingRelatedTickets sourceModule="procurement_one" sourceReferenceId={vendorId} />

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <dl className="grid gap-3 text-sm md:grid-cols-2">
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Tax ID</dt>
              <dd className="mt-1">{vendor.tax_id ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Category</dt>
              <dd className="mt-1">{vendor.category ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Contact email</dt>
              <dd className="mt-1">{vendor.contact?.email ?? vendor.contact?.contact_email ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Accreditation expires</dt>
              <dd className="mt-1">{vendor.accreditation_expires_at ?? "—"}</dd>
            </div>
          </dl>
        </section>

        {vendor.documents.length > 0 ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Vendor documents</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {vendor.documents.map((document) => (
                <li key={document.id} className="rounded-lg border border-border px-3 py-2">
                  {document.label || document.file_name || document.document_kind}
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </div>
    </PermissionGate>
  );
}
