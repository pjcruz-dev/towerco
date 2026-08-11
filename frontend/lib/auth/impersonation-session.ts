import { getErrorMessage } from "@/lib/api/error";
import { impersonateAdminUser, stopImpersonation } from "@/lib/api/modules/admin-users-api";
import {
  clearImpersonationParentSession,
  loadImpersonationParentSession,
  saveImpersonationParentSession,
} from "@/lib/auth/impersonation-storage";
import { useAuthStore } from "@/stores/auth-store";

export async function startUserImpersonation(userId: string, reason: string): Promise<void> {
  const state = useAuthStore.getState();
  if (state.user?.isImpersonating) {
    throw new Error("End the current impersonation session before starting another.");
  }
  if (!state.accessToken || !state.refreshToken) {
    throw new Error("You must be signed in to impersonate a user.");
  }

  saveImpersonationParentSession({
    accessToken: state.accessToken,
    refreshToken: state.refreshToken,
    sessionId: state.sessionId ?? null,
    user: state.user,
  });

  try {
    const session = await impersonateAdminUser(userId, reason);
    useAuthStore.getState().setSession(session);
  } catch (error) {
    clearImpersonationParentSession();
    throw error;
  }

  if (typeof window !== "undefined") {
    window.location.assign("/");
  }
}

export async function endUserImpersonation(): Promise<void> {
  const parent = loadImpersonationParentSession();

  try {
    await stopImpersonation();
  } catch (error) {
    const message = getErrorMessage(error);
    if (!message.toLowerCase().includes("not in an impersonation")) {
      throw error;
    }
  }

  clearImpersonationParentSession();

  if (parent?.accessToken && parent.refreshToken) {
    useAuthStore.getState().setSession({
      accessToken: parent.accessToken,
      refreshToken: parent.refreshToken,
      sessionId: parent.sessionId ?? null,
      mfaRequired: false,
      mfaEnrollmentRequired: false,
      mfaChallenge: null,
      user: parent.user,
    });
  } else {
    useAuthStore.getState().clearSession();
  }

  if (typeof window !== "undefined") {
    window.location.assign("/users");
  }
}
