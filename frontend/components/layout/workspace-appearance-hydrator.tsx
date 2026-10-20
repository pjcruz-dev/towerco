"use client";

import { useQuery } from "@tanstack/react-query";
import { useTheme } from "next-themes";
import { useEffect } from "react";

import { fetchUserUiPreference } from "@/lib/api/modules/user-ui-preferences-api";
import { parseWorkspaceAppearance, WORKSPACE_APPEARANCE_KEY } from "@/lib/theme/workspace-appearance";
import { useAuthStore } from "@/stores/auth-store";
import { useTenantAppearanceStore } from "@/stores/tenant-appearance-store";

export const workspaceAppearanceQueryKey = (userId: string | undefined) =>
  ["me", "workspace-appearance", userId] as const;

/**
 * Loads the signed-in user's own workspace design. It never writes the tenant theme.
 */
export function WorkspaceAppearanceHydrator() {
  const userId = useAuthStore((state) => state.user?.id);
  const panelOpen = useTenantAppearanceStore((state) => state.open);
  const setSaved = useTenantAppearanceStore((state) => state.setSaved);
  const { setTheme } = useTheme();

  const preferenceQuery = useQuery({
    queryKey: workspaceAppearanceQueryKey(userId),
    queryFn: () => fetchUserUiPreference(WORKSPACE_APPEARANCE_KEY),
    enabled: Boolean(userId),
    staleTime: 30_000,
  });

  useEffect(() => {
    if (!userId) {
      setSaved(null);
      return;
    }
    if (!preferenceQuery.isSuccess || panelOpen) {
      return;
    }
    const appearance = parseWorkspaceAppearance(preferenceQuery.data);
    setSaved(appearance);
    if (appearance?.colorMode) {
      setTheme(appearance.colorMode);
    }
  }, [panelOpen, preferenceQuery.data, preferenceQuery.isSuccess, setSaved, setTheme, userId]);

  return null;
}
