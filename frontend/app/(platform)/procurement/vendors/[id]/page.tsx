import { ProcurementVendorDetailPageClient } from "./procurement-vendor-detail-page-client";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function ProcurementVendorDetailPage({ params }: Props) {
  const { id } = await params;
  return <ProcurementVendorDetailPageClient vendorId={id} />;
}
