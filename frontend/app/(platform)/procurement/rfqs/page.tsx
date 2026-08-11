import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementRfqsPageClient } from "./procurement-rfqs-page-client";

export default function ProcurementRfqsPage() {
  return (
    <ProcurementPlanFeatureGate feature="rfq_sourcing">
      <ProcurementRfqsPageClient />
    </ProcurementPlanFeatureGate>
  );
}
