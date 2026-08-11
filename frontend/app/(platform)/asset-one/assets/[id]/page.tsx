import { AssetDetailPageClient } from "./asset-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function AssetDetailPage({ params }: PageProps) {
  const { id } = await params;
  return <AssetDetailPageClient assetId={id} />;
}
