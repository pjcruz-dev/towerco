import { Suspense } from "react";

import { DynamicRecordDetailPageClient } from "@/app/(platform)/dynamic-entities/records/[id]/dynamic-record-detail-page-client";

import { TicketingDynGate } from "../ticketing-dyn-gate";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function TicketingTicketDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading ticket…</p>}>
      <TicketingDynGate>
        <DynamicRecordDetailPageClient recordId={id} accessSlug="helpdesk_ticket" />
      </TicketingDynGate>
    </Suspense>
  );
}
