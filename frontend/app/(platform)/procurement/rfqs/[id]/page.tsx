import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementRfqDetailPageClient } from "./procurement-rfq-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementRfqDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <ProcurementPlanFeatureGate feature="rfq_sourcing">
      <ProcurementRfqDetailPageClient id={id} />
    </ProcurementPlanFeatureGate>
  );
}