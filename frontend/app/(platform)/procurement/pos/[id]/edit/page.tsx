import { ProcurementComposePageClient } from "@/components/procurement-one/procurement-compose-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementPoEditPage({ params }: PageProps) {
  const { id } = await params;

  return <ProcurementComposePageClient kind="purchase_order" mode="edit" documentId={id} />;
}
