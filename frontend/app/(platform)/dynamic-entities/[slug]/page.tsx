import { DynamicEntityRecordsPageClient } from "./dynamic-entity-records-page-client";

export default async function DynamicEntityRecordsPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  return <DynamicEntityRecordsPageClient slug={slug} />;
}
