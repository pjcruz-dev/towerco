import type { AuthSession } from "@/types/auth";

const PARENT_SESSION_KEY = "toweros.auth.impersonation.parent";

export type ImpersonationParentSession = Pick<
  AuthSession,
  "accessToken" | "refreshToken" | "sessionId" | "user"
>;

export function saveImpersonationParentSession(session: ImpersonationParentSession): void {
  if (typeof window === "undefined") {
    return;
  }
  sessionStorage.setItem(PARENT_SESSION_KEY, JSON.stringify(session));
}

export function loadImpersonationParentSession(): ImpersonationParentSession | null {
  if (typeof window === "undefined") {
    return null;
  }
  const raw = sessionStorage.getItem(PARENT_SESSION_KEY);
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw) as ImpersonationParentSession;
  } catch {
    sessionStorage.removeItem(PARENT_SESSION_KEY);
    return null;
  }
}

export function clearImpersonationParentSession(): void {
  if (typeof window === "undefined") {
    return;
  }
  sessionStorage.removeItem(PARENT_SESSION_KEY);
}

export function hasImpersonationParentSession(): boolean {
  return loadImpersonationParentSession() !== null;
}
