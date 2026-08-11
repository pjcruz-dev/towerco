"use client";

import { useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";

import { disconnectEcho, getEcho, isEchoEnabled } from "@/lib/socket/echo-client";
import { useAuthStore } from "@/stores/auth-store";

type RolloutRealtimePayload = {
  rollout_id?: string;
  rollout_ref?: string;
  reason?: string;
};

/**
 * Subscribes to tenant rollout broadcasts and invalidates React Query caches.
 * No-op when NEXT_PUBLIC_SOCKET_ENABLED is not "true".
 */
export function useRolloutRealtime(rolloutId?: string): void {
  const queryClient = useQueryClient();
  const token = useAuthStore((state) => state.accessToken);
  const tenantId = useAuthStore((state) => state.activeTenantId);

  useEffect(() => {
    if (!isEchoEnabled() || !token || !tenantId) {
      return undefined;
    }

    const echo = getEcho(token);
    const channelName = `tenant.${tenantId}.rollouts`;
    const channel = echo.private(channelName);

    const invalidateForRollout = (payload: RolloutRealtimePayload) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard", "map"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "gate-approvals"] });
      queryClient.invalidateQueries({ queryKey: ["tenant", "notifications"] });

      const targetId = payload.rollout_id ?? rolloutId;
      if (targetId) {
        queryClient.invalidateQueries({
          queryKey: ["project-one", "rollouts", "detail", targetId],
        });
      }
    };

    channel.listen(".RolloutUpdated", invalidateForRollout);
    channel.listen(".RolloutCandidateSelected", invalidateForRollout);

    return () => {
      channel.stopListening(".RolloutUpdated");
      channel.stopListening(".RolloutCandidateSelected");
      echo.leave(channelName);
      disconnectEcho();
    };
  }, [queryClient, rolloutId, tenantId, token]);
}
