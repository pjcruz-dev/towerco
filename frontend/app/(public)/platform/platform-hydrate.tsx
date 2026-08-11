"use client";

import { useEffect } from "react";

import { usePlatformAuthStore } from "@/stores/platform-auth-store";

export function PlatformHydrate() {
  useEffect(() => {
    usePlatformAuthStore.getState().hydrate();
  }, []);

  return null;
}
