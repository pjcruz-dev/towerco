import { ProcurementGrnPrintPageClient } from "./procurement-grn-print-page-client";

type Props = { params: Promise<{ id: string }> };

export default async function ProcurementGrnPrintPage({ params }: Props) {
  const { id } = await params;

  return <ProcurementGrnPrintPageClient grnId={id} />;
}
