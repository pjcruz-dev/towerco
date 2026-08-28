import type { LiveTourDefinition } from "@/lib/help/e-approval-live-tour";

/** Standalone product tour — MFA first-time setup (not part of E-Approval). */
export const MFA_LIVE_TOUR_ID = "mfa";

export const MFA_TOUR_HELP_PATH = "/help";

export const mfaLiveTour: LiveTourDefinition = {
  id: MFA_LIVE_TOUR_ID,
  title: "MFA setup tour",
  steps: [
    {
      id: "mfa-nav-account",
      path: "/dashboard",
      entryPath: "/dashboard",
      target: "ea-account-menu",
      title: "Open your account menu",
      body: "In the top-right header, open your account menu. Authenticator MFA is under My security — separate from E-Approval.",
      missingHint: "Look for your name or avatar in the top-right corner of the workspace.",
    },
    {
      id: "mfa-nav-security",
      path: "/dashboard",
      entryPath: "/dashboard",
      target: "ea-account-security",
      title: "My security",
      body: "Click My security. Next opens the Authenticator tab where you enroll MFA for the first time.",
      missingHint: "Open the account menu first, then choose My security.",
    },
    {
      id: "mfa-page",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      autoNavFrom: "ea-account-security",
      target: "ea-security-page",
      title: "My security",
      body: "Use this page for sessions, authenticator MFA, and passkeys. First-time MFA setup happens here — or automatically after login when your organization requires it.",
    },
    {
      id: "mfa-login-paths",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-security-page",
      title: "After you sign in",
      body: "Whether you sign in with email & password, Sign in with passkey, or Sign in with Microsoft — if MFA is required and you have not enrolled yet, TowerOS sends you to Set up MFA before the workspace opens.",
    },
    {
      id: "mfa-tab",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-security-tab-mfa",
      title: "Authenticator tab",
      body: "Choose Authenticator (next to Sessions and Passkeys). Admins turn org MFA on under Sign-in & security; the platform master switch TENANT_MFA_REQUIRED must also be on.",
    },
    {
      id: "mfa-start",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-mfa-start",
      title: "Start setup",
      body: "Click Start setup to create a QR code. Use Microsoft Authenticator, Google Authenticator, or 1Password on your phone.",
      missingHint: "If you already enrolled, you may see Re-enroll authenticator instead.",
    },
    {
      id: "mfa-enroll-panel",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-mfa-enroll",
      title: "Scan and verify",
      body: "After Start setup, scan the QR code in your authenticator app, then enter the 6-digit code and choose Verify and enable. On first login, the same steps appear on the Set up MFA screen.",
      missingHint: "Click Start setup first so the QR code and code field appear.",
    },
    {
      id: "mfa-recovery",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-mfa-recovery",
      title: "Save recovery codes",
      body: "After enrollment, store recovery codes in a safe place. You need one if you lose your phone. Password, Microsoft, and passkey remain available — MFA is an extra step after those when the org requires it.",
    },
    {
      id: "mfa-complete",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-mfa-enroll",
      title: "Tour complete",
      body: "You’re finished. Next sign-in (email, Microsoft, or passkey) will ask for a 6-digit authenticator code when MFA is required. Click Finish tour to close.",
    },
  ],
};

/** Start on Dashboard so the tour begins at the account menu. */
export function mfaTourStartHref(stepIndex = 0): string {
  const clamped = Math.max(0, Math.min(stepIndex, mfaLiveTour.steps.length - 1));
  return `/dashboard?tour=${MFA_LIVE_TOUR_ID}&tourStep=${clamped}`;
}

export function isMfaTourId(tourId: string | null | undefined): boolean {
  return tourId === MFA_LIVE_TOUR_ID;
}
