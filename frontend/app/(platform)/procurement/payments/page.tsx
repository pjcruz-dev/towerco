import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementPaymentsPageClient } from "./procurement-payments-page-client";

export default function ProcurementPaymentsPage() {
  return (
    <ProcurementPlanFeatureGate feature="payment_tracking">
      <ProcurementPaymentsPageClient />
    </ProcurementPlanFeatureGate>
  );
}
