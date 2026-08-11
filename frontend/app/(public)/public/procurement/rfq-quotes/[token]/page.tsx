import { ProcurementRfqPublicQuotePageClient } from "@/components/procurement-one/procurement-rfq-public-quote-page-client";

type Props = {
  params: Promise<{ token: string }>;
};

export default async function ProcurementRfqPublicQuotePage({ params }: Props) {
  const { token } = await params;

  return (
    <main className="min-h-full bg-background px-4 py-8 sm:px-6">
      <ProcurementRfqPublicQuotePageClient accessToken={decodeURIComponent(token)} />
    </main>
  );
}
