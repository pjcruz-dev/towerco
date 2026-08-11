"use client";

import { create } from "zustand";

export type PlatformUser = {
  id: string;
  name: string;
  email: string;
  is_platform_admin: boolean;
  platform_role?: string;
  platform_permissions?: string[];
  platform_mfa_enrolled?: boolean;
  platform_mfa_required?: boolean;
};

type PlatformAuthState = {
  accessToken: string | null;
  user: PlatformUser | null;
  isHydrated: boolean;
  setSession: (payload: { access_token: string; user: PlatformUser }) => void;
  clearSession: () => void;
  hydrate: () => void;
};

const storageKey = "toweros.platform.session";

export const usePlatformAuthStore = create<PlatformAuthState>()((set) => ({
  accessToken: null,
  user: null,
  isHydrated: false,
  setSession: ({ access_token, user }) => {
    if (typeof window !== "undefined") {
      localStorage.setItem(storageKey, JSON.stringify({ access_token, user }));
    }
    set({ accessToken: access_token, user, isHydrated: true });
  },
  clearSession: () => {
    if (typeof window !== "undefined") {
      localStorage.removeItem(storageKey);
    }
    set({ accessToken: null, user: null, isHydrated: true });
  },
  hydrate: () => {
    if (typeof window === "undefined") {
      set({ isHydrated: true });
      return;
    }
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) {
        set({ isHydrated: true });
        return;
      }
      const parsed = JSON.parse(raw) as { access_token?: string; user?: PlatformUser };
      if (parsed.access_token && parsed.user) {
        set({
          accessToken: parsed.access_token,
          user: parsed.user,
          isHydrated: true,
        });
        return;
      }
    } catch {
      localStorage.removeItem(storageKey);
    }
    set({ isHydrated: true });
  },
}));
