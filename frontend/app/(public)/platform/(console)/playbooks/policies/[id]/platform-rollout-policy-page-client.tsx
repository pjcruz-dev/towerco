"use client";

import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { RolloutPolicyEditor } from "@/components/platform/rollout-policy-editor";
import { getErrorMessage } from "@/lib/api/error";
import { platformFetchRolloutPolicy } from "@/lib/api/modules/platform-api";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  policyId: string;
};

export function PlatformRolloutPolicyPageClient({ policyId }: Props) {
  const router = useRouter();
  const notify = useNotificationStore((state) => state.push);
  const accessToken = usePlatformAuthStore((state) => state.accessToken);
  const isHydrated = usePlatformAuthStore((state) => state.isHydrated);

  useEffect(() => {
    if (!isHydrated) return;
    if (!accessToken) {
      router.replace("/platform/login");
    }
  }, [accessToken, isHydrated, router]);

  const policyQuery = useQuery({
    queryKey: ["platform", "rollout-policy", policyId],
    queryFn: () => platformFetchRolloutPolicy(policyId),
    enabled: Boolean(isHydrated && accessToken && policyId),
    retry: 1,
  });

  useEffect(() => {
    if (!policyQuery.isError) return;
    notify({
      level: "error",
      title: "Could not load policy",
      message: getErrorMessage(policyQuery.error),
    });
  }, [notify, policyQuery.error, policyQuery.isError]);

  if (!isHydrated || !accessToken) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Loading…
      </div>
    );
  }

  if (policyQuery.isLoading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Loading rollout policy…
      </div>
    );
  }

  if (policyQuery.isError) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Could not load rollout policy.
      </div>
    );
  }

  if (!policyQuery.data) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Policy not found.
      </div>
    );
  }

  return <RolloutPolicyEditor policy={policyQuery.data} />;
}
