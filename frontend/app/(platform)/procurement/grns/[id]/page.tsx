import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementGrnDetailPageClient } from "./procurement-grn-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementGrnDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <ProcurementPlanFeatureGate feature="goods_receipt">
      <ProcurementGrnDetailPageClient grnId={id} />
    </ProcurementPlanFeatureGate>
  );
}