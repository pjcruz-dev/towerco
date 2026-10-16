import { Suspense } from "react";

import { TicketingTicketDetailPageClient } from "./ticketing-ticket-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function TicketingTicketDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading ticket…</p>}>
      <TicketingTicketDetailPageClient ticketId={id} />
    </Suspense>
  );
}
