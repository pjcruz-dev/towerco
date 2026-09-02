import { DynamicEntityNewRecordPageClient } from "./dynamic-entity-new-record-page-client";

export default async function DynamicEntityNewRecordPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  return <DynamicEntityNewRecordPageClient slug={slug} />;
}
