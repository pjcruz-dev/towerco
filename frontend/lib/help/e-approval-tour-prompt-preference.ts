/** Per-user, per-tenant dismissal of the E-Approval live-tour soft prompt. */

const STORAGE_PREFIX = "toweros.help.liveTourPrompt.dismissed";

export const E_APPROVAL_LIVE_TOUR_ID = "e-approval";

function storageKey(tourId: string, userId: string, tenantId: string | null): string {
  const tenant = tenantId && tenantId.length > 0 ? tenantId : "none";
  return `${STORAGE_PREFIX}:${tourId}:${tenant}:${userId}`;
}

export function hasDismissedLiveTourPrompt(
  tourId: string,
  userId: string | null | undefined,
  tenantId: string | null | undefined,
): boolean {
  if (typeof window === "undefined" || !userId) {
    return true;
  }
  try {
    return window.localStorage.getItem(storageKey(tourId, userId, tenantId ?? null)) === "1";
  } catch {
    return true;
  }
}

export function dismissLiveTourPrompt(
  tourId: string,
  userId: string | null | undefined,
  tenantId: string | null | undefined,
): void {
  if (typeof window === "undefined" || !userId) {
    return;
  }
  try {
    window.localStorage.setItem(storageKey(tourId, userId, tenantId ?? null), "1");
  } catch {
    // ignore quota / private mode
  }
}

export function hasDismissedEApprovalTourPrompt(
  userId: string | null | undefined,
  tenantId: string | null | undefined,
): boolean {
  return hasDismissedLiveTourPrompt(E_APPROVAL_LIVE_TOUR_ID, userId, tenantId);
}

export function dismissEApprovalTourPrompt(
  userId: string | null | undefined,
  tenantId: string | null | undefined,
): void {
  dismissLiveTourPrompt(E_APPROVAL_LIVE_TOUR_ID, userId, tenantId);
}
