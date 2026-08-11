import { TicketingTicketDetailPageClient } from "./ticketing-ticket-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function TicketingTicketDetailPage({ params }: PageProps) {
  const { id } = await params;

  return <TicketingTicketDetailPageClient ticketId={id} />;
}
