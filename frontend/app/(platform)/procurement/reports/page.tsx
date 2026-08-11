import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementReportsPageClient } from "./procurement-reports-page-client";

export default function ProcurementReportsPage() {
  return (
    <ProcurementPlanFeatureGate feature="reporting_exports">
      <ProcurementReportsPageClient />
    </ProcurementPlanFeatureGate>
  );
}
