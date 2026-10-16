import { Suspense } from "react";

import { DynamicEntityRecordsPageClient } from "@/app/(platform)/dynamic-entities/[slug]/dynamic-entity-records-page-client";

import { TicketingDynGate } from "./ticketing-dyn-gate";

export default function TicketingTicketsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading tickets…</div>}>
      <TicketingDynGate>
        <DynamicEntityRecordsPageClient slug="helpdesk_ticket" />
      </TicketingDynGate>
    </Suspense>
  );
}
