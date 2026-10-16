import { Suspense } from "react";

import { DynamicEntityNewRecordPageClient } from "@/app/(platform)/dynamic-entities/[slug]/new/dynamic-entity-new-record-page-client";

import { TicketingDynGate } from "../ticketing-dyn-gate";

export default function TicketingNewTicketPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading form…</div>}>
      <TicketingDynGate>
        <DynamicEntityNewRecordPageClient slug="helpdesk_ticket" />
      </TicketingDynGate>
    </Suspense>
  );
}
