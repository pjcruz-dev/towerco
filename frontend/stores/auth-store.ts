"use client";

import { create, type StoreApi, type UseBoundStore } from "zustand";

import { clearSessionCookie, setSessionCookie } from "@/lib/auth/session-cookie";
import {
  inferTenantDomainFromEmail,
  resolveSessionTenantDomainForStorage,
  sessionMatchesCurrentTenant,
} from "@/lib/auth/tenant-session";
import {
  resolveTenantDomainFromUser,
  syncTenantAuthContextFromUser,
} from "@/lib/tenant/sync-tenant-auth-context";
import { tenantDomainFromBrowserHostname } from "@/lib/tenant/resolve-tenant-domain";
import type { AuthSession, AuthUser } from "@/types/auth";

export type AuthState = AuthSession & {
  tenantDomain: string | null;
  activeTenantId: string | null;
  pendingMfa: AuthSession | null;
  isHydrated: boolean;
  permissionsReady: boolean;
  setSession: (payload: AuthSession) => void;
  beginMfaLogin: (payload: AuthSession) => void;
  setPendingMfa: (payload: AuthSession | null) => void;
  clearSession: () => void;
  setUser: (user: AuthUser | null) => void;
  setActiveTenantId: (tenantId: string | null) => void;
  syncTenantContext: () => void;
  effectivePermissions: () => string[];
  setPermissionsReady: (ready: boolean) => void;
  hydrate: () => void;
};

const storageKey = "toweros.auth.session";
const pendingMfaStorageKey = "toweros.auth.pending_mfa";

function resolveActiveTenantId(user: AuthUser | null | undefined): string | null {
  return user?.tenantId ?? user?.tenantAccesses[0]?.tenantId ?? null;
}

function resolveTenantDomainForSession(user: AuthUser | null | undefined): string | null {
  if (typeof window !== "undefined") {
    const fromBrowser = tenantDomainFromBrowserHostname(window.location.hostname);
    if (fromBrowser) {
      return fromBrowser;
    }
  }

  const activeTenantId = resolveActiveTenantId(user);
  const fromUser = resolveTenantDomainFromUser(user, activeTenantId);
  if (fromUser) {
    return fromUser;
  }

  const fromEmail = inferTenantDomainFromEmail(user?.email);
  if (fromEmail) {
    return fromEmail;
  }

  return resolveSessionTenantDomainForStorage();
}

function persistPendingMfa(payload: AuthSession | null) {
  if (typeof window === "undefined") {
    return;
  }
  if (payload) {
    sessionStorage.setItem(pendingMfaStorageKey, JSON.stringify(payload));
  } else {
    sessionStorage.removeItem(pendingMfaStorageKey);
  }
}

export const useAuthStore = create<AuthState>()((set) => ({
  accessToken: null,
  refreshToken: null,
  sessionId: null,
  mfaRequired: false,
  mfaEnrollmentRequired: false,
  passkeyEnrollmentRequired: false,
  mfaChallenge: null,
  user: null,
  tenantDomain: null,
  activeTenantId: null,
  pendingMfa: null,
  isHydrated: false,
  permissionsReady: false,
  setSession: ({
    accessToken,
    refreshToken,
    sessionId,
    mfaRequired,
    mfaEnrollmentRequired,
    passkeyEnrollmentRequired,
    mfaChallenge,
    user,
  }) => {
    const activeTenantId = resolveActiveTenantId(user);
    const tenantDomain = resolveTenantDomainForSession(user);

    if (typeof window !== "undefined") {
      sessionStorage.removeItem(pendingMfaStorageKey);
      localStorage.setItem(
        storageKey,
        JSON.stringify({
          accessToken,
          refreshToken,
          sessionId: sessionId ?? null,
          mfaRequired: mfaRequired ?? false,
          mfaEnrollmentRequired: mfaEnrollmentRequired ?? false,
          passkeyEnrollmentRequired: passkeyEnrollmentRequired ?? false,
          mfaChallenge: mfaChallenge ?? null,
          user,
          tenantDomain,
          activeTenantId,
        }),
      );
    }
    set({
      accessToken,
      refreshToken,
      sessionId: sessionId ?? null,
      mfaRequired: mfaRequired ?? false,
      mfaEnrollmentRequired: mfaEnrollmentRequired ?? false,
      passkeyEnrollmentRequired: passkeyEnrollmentRequired ?? false,
      mfaChallenge: mfaChallenge ?? null,
      user,
      tenantDomain,
      activeTenantId,
      pendingMfa: null,
      isHydrated: true,
      permissionsReady: true,
    });
    if (typeof window !== "undefined" && accessToken) {
      setSessionCookie();
      syncTenantAuthContextFromUser(user);
    }
  },
  syncTenantContext: () => {
    const user = useAuthStore.getState().user;
    syncTenantAuthContextFromUser(user);
    if (!user) {
      return;
    }
    const activeTenantId = resolveActiveTenantId(user);
    const tenantDomain = resolveTenantDomainForSession(user);
    set({ activeTenantId, tenantDomain });
  },
  beginMfaLogin: (payload) => {
    const activeTenantId = resolveActiveTenantId(payload.user);
    const tenantDomain = resolveTenantDomainForSession(payload.user);
    const pendingPayload = { ...payload, tenantDomain };

    if (typeof window !== "undefined") {
      localStorage.removeItem(storageKey);
      clearSessionCookie();
      persistPendingMfa(pendingPayload);
    }

    set({
      accessToken: null,
      refreshToken: null,
      sessionId: null,
      mfaRequired: false,
      mfaEnrollmentRequired: false,
      passkeyEnrollmentRequired: false,
      mfaChallenge: null,
      user: null,
      tenantDomain,
      activeTenantId,
      pendingMfa: pendingPayload,
    });
  },
  setPendingMfa: (payload) => {
    persistPendingMfa(payload);
    set({ pendingMfa: payload });
  },
  clearSession: () => {
    if (typeof window !== "undefined") {
      localStorage.removeItem(storageKey);
      sessionStorage.removeItem(pendingMfaStorageKey);
      clearSessionCookie();
    }
    set({
      accessToken: null,
      refreshToken: null,
      sessionId: null,
      mfaRequired: false,
      mfaEnrollmentRequired: false,
      passkeyEnrollmentRequired: false,
      mfaChallenge: null,
      user: null,
      tenantDomain: null,
      activeTenantId: null,
      pendingMfa: null,
      permissionsReady: true,
    });
  },
  setPermissionsReady: (ready) => set({ permissionsReady: ready }),
  setUser: (user) =>
    set((state) => {
      if (typeof window !== "undefined" && user) {
        localStorage.setItem(
          storageKey,
          JSON.stringify({
            accessToken: state.accessToken,
            refreshToken: state.refreshToken,
            sessionId: state.sessionId,
            mfaRequired: state.mfaRequired,
            mfaEnrollmentRequired: state.mfaEnrollmentRequired,
            passkeyEnrollmentRequired: state.passkeyEnrollmentRequired,
            mfaChallenge: state.mfaChallenge,
            user,
            tenantDomain: state.tenantDomain,
            activeTenantId: state.activeTenantId,
          }),
        );
      }

      syncTenantAuthContextFromUser(user);

      return { user };
    }),
  setActiveTenantId: (tenantId) =>
    set((state) => {
      if (typeof window !== "undefined") {
        localStorage.setItem(
          storageKey,
          JSON.stringify({
            accessToken: state.accessToken,
            refreshToken: state.refreshToken,
            sessionId: state.sessionId,
            user: state.user,
            tenantDomain: state.tenantDomain,
            activeTenantId: tenantId,
          }),
        );
      }

      return { activeTenantId: tenantId };
    }),
  effectivePermissions: () => {
    const state = useAuthStore.getState();
    if (!state.user) return [];

    const tenant = state.user.tenantAccesses.find(
      (item) => item.tenantId === state.activeTenantId,
    );

    if (tenant && tenant.permissions.length > 0) {
      return tenant.permissions;
    }

    return state.user.permissions;
  },
  hydrate: () => {
    if (typeof window === "undefined") {
      set({ isHydrated: true });
      return;
    }

    const pendingRaw = sessionStorage.getItem(pendingMfaStorageKey);
    if (pendingRaw) {
      try {
        const pending = JSON.parse(pendingRaw) as AuthSession & { tenantDomain?: string | null };
        if (!sessionMatchesCurrentTenant(pending)) {
          sessionStorage.removeItem(pendingMfaStorageKey);
        } else {
          set({
            accessToken: null,
            refreshToken: null,
            sessionId: null,
            mfaRequired: false,
            mfaEnrollmentRequired: false,
            mfaChallenge: null,
            user: null,
            tenantDomain: pending.tenantDomain ?? null,
            activeTenantId: resolveActiveTenantId(pending.user),
            pendingMfa: pending,
            isHydrated: true,
            permissionsReady: true,
          });
          return;
        }
      } catch {
        sessionStorage.removeItem(pendingMfaStorageKey);
      }
    }

    const raw = localStorage.getItem(storageKey);
    if (raw) {
      try {
        const parsed = JSON.parse(raw) as AuthSession & {
          activeTenantId?: string | null;
          tenantDomain?: string | null;
        };

        if (!sessionMatchesCurrentTenant(parsed)) {
          localStorage.removeItem(storageKey);
          clearSessionCookie();
        } else {
          set({
            accessToken: parsed.accessToken,
            refreshToken: parsed.refreshToken ?? null,
            sessionId: parsed.sessionId ?? null,
            mfaRequired: parsed.mfaRequired ?? false,
            mfaEnrollmentRequired: parsed.mfaEnrollmentRequired ?? false,
            passkeyEnrollmentRequired: parsed.passkeyEnrollmentRequired ?? false,
            mfaChallenge: parsed.mfaChallenge ?? null,
            user: parsed.user,
            tenantDomain: parsed.tenantDomain ?? null,
            activeTenantId:
              parsed.activeTenantId ??
              resolveActiveTenantId(parsed.user),
            pendingMfa: null,
            isHydrated: true,
            permissionsReady: false,
          });
          if (parsed.accessToken) {
            setSessionCookie();
          }
          syncTenantAuthContextFromUser(parsed.user);
          return;
        }
      } catch {
        localStorage.removeItem(storageKey);
        clearSessionCookie();
      }
    }

    set({ isHydrated: true, permissionsReady: true });
  },
})) as UseBoundStore<StoreApi<AuthState>>;

export function resolveAuthAccessToken(): string | null {
  const state = useAuthStore.getState();
  return state.accessToken ?? state.pendingMfa?.accessToken ?? null;
}

export function resolveAuthRefreshToken(): string | null {
  const state = useAuthStore.getState();
  return state.refreshToken ?? state.pendingMfa?.refreshToken ?? null;
}

export function resolveAuthSessionId(): string | null {
  const state = useAuthStore.getState();
  return state.sessionId ?? state.pendingMfa?.sessionId ?? null;
}
