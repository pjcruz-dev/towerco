import { DynRecordPrintPageClient } from "@/components/dynamic-entities/dyn-record-print-page-client";

export default async function DynamicRecordsBulkPrintPage({
  searchParams,
}: {
  searchParams: Promise<{ ids?: string; template?: string }>;
}) {
  const params = await searchParams;
  const ids = (params.ids ?? "")
    .split(",")
    .map((id) => id.trim())
    .filter(Boolean);

  return <DynRecordPrintPageClient recordIds={ids} templateId={params.template ?? null} />;
}
