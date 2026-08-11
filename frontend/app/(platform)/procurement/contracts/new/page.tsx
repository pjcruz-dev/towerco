import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementContractCreatePageClient } from "./procurement-contract-create-page-client";

export default function ProcurementContractCreatePage() {
  return (
    <ProcurementPlanFeatureGate feature="vendor_contracts">
      <ProcurementContractCreatePageClient />
    </ProcurementPlanFeatureGate>
  );
}