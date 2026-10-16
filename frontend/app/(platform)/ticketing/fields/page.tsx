import { Suspense } from "react";

import { TicketingFieldsPageClient } from "./ticketing-fields-page-client";

export default function TicketingFieldsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading fields…</div>}>
      <TicketingFieldsPageClient />
    </Suspense>
  );
}
