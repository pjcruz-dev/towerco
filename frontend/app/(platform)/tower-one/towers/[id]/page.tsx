import { TowerDetailPageClient } from "./tower-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function TowerDetailPage({ params }: PageProps) {
  const { id } = await params;
  return <TowerDetailPageClient towerId={id} />;
}
