import type { LiveTourDefinition, LiveTourStep } from "@/lib/help/e-approval-live-tour";

/** Standalone product tour — not part of E-Approval. */
export const PASSKEYS_LIVE_TOUR_ID = "passkeys";

export const PASSKEYS_TOUR_HELP_PATH = "/help";

export const passkeysLiveTour: LiveTourDefinition = {
  id: PASSKEYS_LIVE_TOUR_ID,
  title: "Passkeys tour",
  steps: [
    {
      id: "passkeys-nav-account",
      path: "/dashboard",
      entryPath: "/dashboard",
      target: "ea-account-menu",
      title: "Open your account menu",
      body: "In the top-right header, open your account menu (name / avatar). Passkeys are under personal security — not under E-Approval.",
      missingHint: "Look for your name or avatar in the top-right corner of the workspace.",
    },
    {
      id: "passkeys-nav-security",
      path: "/dashboard",
      entryPath: "/dashboard",
      target: "ea-account-security",
      title: "My security",
      body: "Click My security. Next opens that page — the tour selects the Passkeys tab for you.",
      missingHint: "Open the account menu first, then choose My security.",
    },
    {
      id: "passkeys-page",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      autoNavFrom: "ea-account-security",
      target: "ea-security-page",
      title: "My security",
      body: "This is where you manage sessions, authenticator MFA, and passkeys (fingerprint / Face ID / Windows Hello).",
    },
    {
      id: "passkeys-tab",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      target: "ea-security-tab-passkeys",
      title: "Passkeys tab",
      body: "Choose Passkeys (next to Sessions and Authenticator). Organization admins enable passkeys under Administration → Sign-in & security.",
    },
    {
      id: "passkeys-label",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      target: "ea-passkey-label",
      title: "Name this device",
      body: "Give the passkey a short label (for example Work laptop) so you can tell devices apart later.",
    },
    {
      id: "passkeys-add",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      target: "ea-passkey-add",
      title: "Add passkey",
      body: "Click Add passkey. Your browser asks for fingerprint, Face ID, or Windows Hello PIN. Use https:// on this organization host — passkeys need a secure connection.",
      missingHint: "If Add passkey is disabled, passkeys may be off for the org, or this page is not on HTTPS.",
    },
    {
      id: "passkeys-list",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      target: "ea-passkey-list",
      title: "Registered passkeys",
      body: "Enrolled devices appear here. Each laptop or phone needs its own passkey. Staging and production hosts are separate — enroll on the host you will use to sign in. Password and Microsoft stay available as backup.",
    },
    {
      id: "passkeys-complete",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      target: "ea-passkey-list",
      title: "Tour complete",
      body: "You’re finished. After you enroll, use Sign in with passkey on the login page for this same host. Click Finish tour to close.",
    },
  ],
};

/** Start on Dashboard so the tour begins at the account menu (same pattern as E-Approval sidebar chapters). */
export function passkeysTourStartHref(stepIndex = 0): string {
  const clamped = Math.max(0, Math.min(stepIndex, passkeysLiveTour.steps.length - 1));
  return `/dashboard?tour=${PASSKEYS_LIVE_TOUR_ID}&tourStep=${clamped}`;
}

export function isPasskeysTourId(tourId: string | null | undefined): boolean {
  return tourId === PASSKEYS_LIVE_TOUR_ID;
}

export function isPasskeysTourStep(step: LiveTourStep | null | undefined): boolean {
  return Boolean(step?.id.startsWith("passkeys-"));
}
