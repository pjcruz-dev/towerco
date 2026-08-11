import { ProcurementPoDetailPageClient } from "./procurement-po-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementPoDetailPage({ params }: PageProps) {
  const { id } = await params;

  return <ProcurementPoDetailPageClient poId={id} />;
}
