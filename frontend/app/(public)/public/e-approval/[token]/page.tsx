import { EApprovalPublicSubmitPageClient } from "@/components/e-approval/e-approval-public-submit-page-client";

type Props = {
  params: Promise<{ token: string }>;
};

export default async function EApprovalPublicFormPage({ params }: Props) {
  const { token } = await params;

  return (
    <main className="min-h-full bg-background px-4 py-8 sm:px-6">
      <EApprovalPublicSubmitPageClient accessToken={decodeURIComponent(token)} />
    </main>
  );
}
