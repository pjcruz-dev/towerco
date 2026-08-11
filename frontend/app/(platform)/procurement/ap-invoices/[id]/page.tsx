import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementApInvoiceDetailPageClient } from "./procurement-ap-invoice-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementApInvoiceDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <ProcurementPlanFeatureGate feature="ap_invoices">
      <ProcurementApInvoiceDetailPageClient id={id} />
    </ProcurementPlanFeatureGate>
  );
}