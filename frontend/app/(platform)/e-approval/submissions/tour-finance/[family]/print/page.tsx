import { notFound } from "next/navigation";

import { EApprovalFinanceTourPrintPageClient } from "@/components/help/e-approval-finance-tour-screens";
import { financeTourFamilyFromSlug } from "@/lib/help/e-approval-finance-tour";

type PageProps = {
  params: Promise<{ family: string }>;
};

export default async function EApprovalFinanceTourPrintPage({ params }: PageProps) {
  const { family: slug } = await params;
  const family = financeTourFamilyFromSlug(slug);
  if (!family) notFound();
  return <EApprovalFinanceTourPrintPageClient family={family} />;
}
