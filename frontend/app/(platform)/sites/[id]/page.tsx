import { SiteDetailPageClient } from "./site-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function SiteDetailPage({ params }: PageProps) {
  const { id } = await params;
  return <SiteDetailPageClient siteId={id} />;
}
