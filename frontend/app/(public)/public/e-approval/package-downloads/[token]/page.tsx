import { EApprovalPublicPackageDownloadPageClient } from "@/components/e-approval/e-approval-public-package-download-page-client";

type Props = {
  params: Promise<{ token: string }>;
};

export default async function EApprovalPublicPackageDownloadPage({ params }: Props) {
  const { token } = await params;

  return (
    <main className="min-h-full bg-background px-4 py-8 sm:px-6">
      <EApprovalPublicPackageDownloadPageClient downloadToken={decodeURIComponent(token)} />
    </main>
  );
}
