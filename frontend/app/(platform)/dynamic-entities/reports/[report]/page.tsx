import { notFound } from "next/navigation";

import { DynExtendedReportPageClient } from "../dyn-extended-report-page-client";
import type { DynExtendedReportKey } from "@/lib/api/modules/dynamic-entities-api";

const EXTENDED_REPORTS = new Set<DynExtendedReportKey>([
  "cas-executive-dashboard",
  "ar-ap-aging",
  "stock-on-hand",
  "petty-cash",
  "live-tower-build-forecasts",
  "site-portfolio-status",
  "rfti-pipeline",
  "saq-milestone-aging",
  "permit-status-compliance",
  "energization-power-status",
  "colocation-tenancy",
  "trial-balance",
  "income-statement",
  "balance-sheet",
  "cash-flow",
  "monthly-management-accounts",
  "bir-compliance",
]);

export default async function DynExtendedReportPage({
  params,
}: {
  params: Promise<{ report: string }>;
}) {
  const { report } = await params;
  if (!EXTENDED_REPORTS.has(report as DynExtendedReportKey)) {
    notFound();
  }

  return <DynExtendedReportPageClient report={report as DynExtendedReportKey} />;
}
