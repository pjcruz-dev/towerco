import { notFound } from "next/navigation";

import { EApprovalFinanceTourComposePageClient } from "@/components/help/e-approval-finance-tour-screens";
import { financeTourFamilyFromSlug } from "@/lib/help/e-approval-finance-tour";

type PageProps = {
  params: Promise<{ family: string }>;
};

export default async function EApprovalFinanceTourComposePage({ params }: PageProps) {
  const { family: slug } = await params;
  const family = financeTourFamilyFromSlug(slug);
  if (!family) notFound();
  return <EApprovalFinanceTourComposePageClient family={family} />;
}
