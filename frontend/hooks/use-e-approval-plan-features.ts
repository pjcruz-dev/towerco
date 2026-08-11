"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchEApprovalMetadata } from "@/lib/api/modules/e-approval-api";

export type EApprovalPlanFeatures = {
  plan_tier: string;
  file_uploads: boolean;
  max_file_fields: number | null;
};

const DEFAULT_FEATURES: EApprovalPlanFeatures = {
  plan_tier: "starter",
  file_uploads: false,
  max_file_fields: 0,
};

export function useEApprovalPlanFeatures() {
  const query = useQuery({
    queryKey: ["e-approval", "metadata", "plan-features"],
    queryFn: fetchEApprovalMetadata,
    staleTime: 60_000,
  });

  const features = query.data?.plan_features ?? DEFAULT_FEATURES;

  return {
    ...features,
    fileUploadsAllowed: features.file_uploads,
    planTier: features.plan_tier,
    maxFileFields: features.max_file_fields,
    isLoading: query.isLoading,
  };
}
