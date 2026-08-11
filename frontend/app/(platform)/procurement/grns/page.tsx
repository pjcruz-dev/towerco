import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementGrnsPageClient } from "./procurement-grns-page-client";

export default function ProcurementGrnsPage() {
  return (
    <ProcurementPlanFeatureGate feature="goods_receipt">
      <ProcurementGrnsPageClient />
    </ProcurementPlanFeatureGate>
  );
}
