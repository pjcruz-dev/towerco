import { EApprovalPublicSharedSubmissionPageClient } from "@/components/e-approval/e-approval-public-shared-submission-page-client";

type Props = {
  params: Promise<{ token: string }>;
};

export default async function EApprovalPublicSharedSubmissionPage({ params }: Props) {
  const { token } = await params;

  return (
    <main className="min-h-full bg-background px-4 py-8 sm:px-6">
      <EApprovalPublicSharedSubmissionPageClient shareToken={decodeURIComponent(token)} />
    </main>
  );
}
