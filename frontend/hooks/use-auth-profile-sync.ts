"use client";

import { useEffect } from "react";

import { me } from "@/lib/api/modules/auth-api";
import { useAuthStore } from "@/stores/auth-store";

/**
 * Refreshes roles/permissions from GET /me so Team & Access changes apply without re-login.
 */
export function useAuthProfileSync() {
  const accessToken = useAuthStore((state) => state.accessToken);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const setUser = useAuthStore((state) => state.setUser);
  const setPermissionsReady = useAuthStore((state) => state.setPermissionsReady);

  useEffect(() => {
    if (!isHydrated || !accessToken) {
      if (isHydrated) {
        setPermissionsReady(true);
      }
      return;
    }

    let cancelled = false;
    setPermissionsReady(false);

    void me()
      .then((user) => {
        if (!cancelled && user) {
          setUser(user);
        }
      })
      .catch(() => {
        // Ignore — 401 is handled by the API client refresh flow.
      })
      .finally(() => {
        if (!cancelled) {
          setPermissionsReady(true);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [accessToken, isHydrated, setPermissionsReady, setUser]);
}
