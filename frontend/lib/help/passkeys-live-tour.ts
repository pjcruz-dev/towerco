import type { LiveTourDefinition, LiveTourStep } from "@/lib/help/e-approval-live-tour";

/** Standalone product tour — not part of E-Forms. */
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
      body: "In the top-right header, open your account menu (name / avatar). Passkeys are under My profile → Security — not under E-Forms.",
      missingHint: "Look for your name or avatar in the top-right corner of the workspace.",
    },
    {
      id: "passkeys-nav-security",
      path: "/dashboard",
      entryPath: "/dashboard",
      target: "ea-account-security",
      title: "My profile",
      body: "Open My profile. Next opens the Security section.",
      missingHint: "Open the account menu first, then choose My profile.",
    },
    {
      id: "passkeys-profile-security",
      path: "/account/profile",
      entryPath: "/account/profile",
      query: { section: "security" },
      autoNavFrom: "ea-account-security",
      target: "ea-profile-passkeys",
      title: "Security",
      body: "Open Passkeys. Authenticator and active sessions are on this same list.",
      missingHint: "Choose Security on My profile, then Passkeys.",
    },
    {
      id: "passkeys-page",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      autoNavFrom: "ea-profile-passkeys",
      target: "ea-security-page",
      title: "Passkeys",
      body: "This page manages sessions, authenticator MFA, and passkeys (fingerprint / Face ID / Windows Hello).",
    },
    {
      id: "passkeys-tab",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      target: "ea-security-tab-passkeys",
      title: "Passkeys tab",
      body: "Stay on Passkeys (Sessions and Authenticator are the other tabs). Organization admins enable passkeys from My profile → Security → Sign-in & security, or Settings in the sidebar.",
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
      body: "Enrolled devices appear here with device type (phone / computer / security key) and whether the passkey uses fingerprint, Face ID, or an external authenticator. Each laptop or phone needs its own passkey. Staging and production hosts are separate — enroll on the host you will use to sign in.",
    },
    {
      id: "passkeys-complete",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "passkeys" },
      target: "ea-passkey-list",
      title: "Tour complete",
      body: "You’re finished. After you enroll, use Sign in with passkey on the login page for this same host (password and Microsoft stay available as backup). Click Finish tour to close.",
    },
  ],
};

/** Start on Dashboard so the tour begins at the account menu (same pattern as E-Forms sidebar chapters). */
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
