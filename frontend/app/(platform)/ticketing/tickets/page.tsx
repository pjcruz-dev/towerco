import { Suspense } from "react";

import { TicketingTicketsPageClient } from "./ticketing-tickets-page-client";

export default function TicketingTicketsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading tickets…</div>}>
      <TicketingTicketsPageClient />
    </Suspense>
  );
}
