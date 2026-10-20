import { notFound } from "next/navigation";

import { EApprovalFinanceTourDetailPageClient } from "@/components/help/e-approval-finance-tour-screens";
import { financeTourFamilyFromSlug } from "@/lib/help/e-approval-finance-tour";

type PageProps = {
  params: Promise<{ family: string }>;
};

export default async function EApprovalFinanceTourDetailPage({ params }: PageProps) {
  const { family: slug } = await params;
  const family = financeTourFamilyFromSlug(slug);
  if (!family) notFound();
  return <EApprovalFinanceTourDetailPageClient family={family} />;
}
