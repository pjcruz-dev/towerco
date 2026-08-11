import { ProcurementComposePageClient } from "@/components/procurement-one/procurement-compose-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementPrEditPage({ params }: PageProps) {
  const { id } = await params;

  return <ProcurementComposePageClient kind="purchase_requisition" mode="edit" documentId={id} />;
}
