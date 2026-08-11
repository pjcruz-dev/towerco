import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementGrnFromPoComposePageClient } from "./procurement-grn-from-po-compose-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementGrnFromPoComposePage({ params }: PageProps) {
  const { id } = await params;

  return (
    <ProcurementPlanFeatureGate feature="goods_receipt">
      <ProcurementGrnFromPoComposePageClient poId={id} />
    </ProcurementPlanFeatureGate>
  );
}