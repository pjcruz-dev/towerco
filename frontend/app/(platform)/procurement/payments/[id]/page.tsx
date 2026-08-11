import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementPaymentDetailPageClient } from "./procurement-payment-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementPaymentDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <ProcurementPlanFeatureGate feature="payment_tracking">
      <ProcurementPaymentDetailPageClient id={id} />
    </ProcurementPlanFeatureGate>
  );
}