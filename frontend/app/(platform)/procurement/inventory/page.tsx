import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementInventoryPageClient } from "./procurement-inventory-page-client";

export default function ProcurementInventoryPage() {
  return (
    <ProcurementPlanFeatureGate feature="inventory">
      <ProcurementInventoryPageClient />
    </ProcurementPlanFeatureGate>
  );
}
