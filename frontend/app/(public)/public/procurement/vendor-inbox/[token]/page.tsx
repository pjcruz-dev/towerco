import { ProcurementVendorInboxPageClient } from "@/components/procurement-one/procurement-vendor-inbox-page-client";

type Props = {
  params: Promise<{ token: string }>;
};

export default async function ProcurementVendorInboxPage({ params }: Props) {
  const { token } = await params;

  return (
    <main className="min-h-full bg-background px-4 py-8 sm:px-6">
      <ProcurementVendorInboxPageClient accessToken={decodeURIComponent(token)} />
    </main>
  );
}
