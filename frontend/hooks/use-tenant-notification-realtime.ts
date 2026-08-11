"use client";

import { useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";

import { disconnectEcho, getEcho, isEchoEnabled } from "@/lib/socket/echo-client";
import { useAuthStore } from "@/stores/auth-store";

type TenantNotificationRealtimePayload = {
  notification_id?: string;
  module?: string;
  category?: string;
};

/**
 * Subscribes to per-user tenant notification broadcasts and refreshes React Query caches.
 * No-op when NEXT_PUBLIC_SOCKET_ENABLED is not "true".
 */
export function useTenantNotificationRealtime(enabled = true): void {
  const queryClient = useQueryClient();
  const token = useAuthStore((state) => state.accessToken);
  const userId = useAuthStore((state) => state.user?.id);
  const tenantId = useAuthStore((state) => state.activeTenantId);

  useEffect(() => {
    if (!enabled || !isEchoEnabled() || !token || !userId || !tenantId) {
      return undefined;
    }

    const echo = getEcho(token);
    const channelName = `tenant.${tenantId}.user.${userId}.notifications`;
    const channel = echo.private(channelName);

    const invalidate = (_payload?: TenantNotificationRealtimePayload) => {
      queryClient.invalidateQueries({ queryKey: ["tenant", "notifications"] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "notifications"] });
      queryClient.invalidateQueries({ queryKey: ["workspace", "dashboard"] });
    };

    channel.listen(".TenantNotificationCreated", invalidate);

    return () => {
      channel.stopListening(".TenantNotificationCreated");
      echo.leave(channelName);
      disconnectEcho();
    };
  }, [enabled, queryClient, tenantId, token, userId]);
}
