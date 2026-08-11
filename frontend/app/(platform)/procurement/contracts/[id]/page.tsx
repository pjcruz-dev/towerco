import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementContractDetailPageClient } from "./procurement-contract-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementContractDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <ProcurementPlanFeatureGate feature="vendor_contracts">
      <ProcurementContractDetailPageClient contractId={id} />
    </ProcurementPlanFeatureGate>
  );
}