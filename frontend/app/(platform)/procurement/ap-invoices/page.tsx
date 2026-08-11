import { ProcurementPlanFeatureGate } from "@/components/procurement-one/procurement-plan-feature-gate";
import { ProcurementApInvoicesPageClient } from "./procurement-ap-invoices-page-client";

export default function ProcurementApInvoicesPage() {
  return (
    <ProcurementPlanFeatureGate feature="ap_invoices">
      <ProcurementApInvoicesPageClient />
    </ProcurementPlanFeatureGate>
  );
}
