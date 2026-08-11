import { EApprovalPublicRevisePageClient } from "@/components/e-approval/e-approval-public-revise-page-client";

type Props = {
  params: Promise<{ submissionId: string }>;
  searchParams: Promise<{ resubmit_token?: string }>;
};

export default async function EApprovalPublicRevisePage({ params, searchParams }: Props) {
  const { submissionId } = await params;
  const query = await searchParams;

  return (
    <main className="min-h-full bg-background px-4 py-8 sm:px-6">
      <EApprovalPublicRevisePageClient
        submissionId={decodeURIComponent(submissionId)}
        resubmitToken={query.resubmit_token ?? ""}
      />
    </main>
  );
}
