import { Suspense } from "react";

import { TicketingNewTicketPageClient } from "./ticketing-new-ticket-page-client";

export default function TicketingNewTicketPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading form…</div>}>
      <TicketingNewTicketPageClient />
    </Suspense>
  );
}
