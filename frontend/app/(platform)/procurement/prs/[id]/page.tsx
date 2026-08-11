import { ProcurementPrDetailPageClient } from "./procurement-pr-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementPrDetailPage({ params }: PageProps) {
  const { id } = await params;

  return <ProcurementPrDetailPageClient prId={id} />;
}
