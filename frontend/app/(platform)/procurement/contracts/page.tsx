import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementContractsPageClient } from "./procurement-contracts-page-client";

export default function ProcurementContractsPage() {
  return (
    <ProcurementPlanFeatureGate feature="vendor_contracts">
      <ProcurementContractsPageClient />
    </ProcurementPlanFeatureGate>
  );
}
