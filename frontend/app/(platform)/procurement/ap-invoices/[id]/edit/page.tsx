import { ProcurementComposePageClient } from "@/components/procurement-one/procurement-compose-page-client";
import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementApInvoiceEditPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <ProcurementPlanFeatureGate feature="ap_invoices">
      <ProcurementComposePageClient kind="ap_invoice" mode="edit" documentId={id} />
    </ProcurementPlanFeatureGate>
  );
}