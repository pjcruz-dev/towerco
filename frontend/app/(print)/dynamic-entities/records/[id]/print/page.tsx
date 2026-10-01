import { DynRecordPrintPageClient } from "@/components/dynamic-entities/dyn-record-print-page-client";

export default async function DynamicRecordPrintPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ template?: string }>;
}) {
  const { id } = await params;
  const { template } = await searchParams;
  return <DynRecordPrintPageClient recordIds={[id]} templateId={template ?? null} />;
}
