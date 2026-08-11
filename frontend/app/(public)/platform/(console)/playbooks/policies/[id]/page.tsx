import { PlatformRolloutPolicyPageClient } from "./platform-rollout-policy-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function PlatformRolloutPolicyPage({ params }: PageProps) {
  const { id } = await params;
  return <PlatformRolloutPolicyPageClient policyId={id} />;
}
