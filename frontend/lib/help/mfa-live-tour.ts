import type { LiveTourDefinition } from "@/lib/help/e-approval-live-tour";

/** Standalone product tour — MFA first-time setup under My security (workspace). */
export const MFA_LIVE_TOUR_ID = "mfa";

/** Coach marks on the first-login Set up MFA screen (`/login/mfa/enroll`). */
export const MFA_LOGIN_LIVE_TOUR_ID = "mfa-login";

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
      body: "If MFA is required and you have not enrolled yet, TowerOS opens the split-screen Set up MFA page after Sign in with Microsoft (and after email & password). Passkey may skip authenticator MFA when org policy allows. That screen has its own short guided tour before the workspace opens.",
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
      title: "Step 1 — Start setup",
      body: "Click Start setup first. That creates your personal QR code. The gray sample above is only a preview — do not scan it.",
      missingHint: "If you already enrolled, you may see Re-enroll authenticator instead.",
    },
    {
      id: "mfa-enroll-panel",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-mfa-sample-qr",
      title: "Step 2 — Scan your real QR",
      body: "Before Start setup you see a labeled sample. After Start setup, the real QR appears here — scan that with Microsoft Authenticator (or similar), enter the 6-digit code, then Verify and enable. First login uses the same flow on Set up MFA.",
      missingHint: "Stay on the Authenticator tab. Click Start setup if you still only see the sample preview.",
    },
    {
      id: "mfa-recovery",
      path: "/account/security",
      entryPath: "/account/security",
      query: { tab: "mfa" },
      target: "ea-mfa-recovery",
      title: "Step 3 — Save recovery codes",
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

/** First-login Set up MFA screen — short coach marks (no workspace chrome). */
export const mfaLoginEnrollLiveTour: LiveTourDefinition = {
  id: MFA_LOGIN_LIVE_TOUR_ID,
  title: "First-login MFA",
  steps: [
    {
      id: "mfa-login-why",
      path: "/login/mfa/enroll",
      entryPath: "/login/mfa/enroll",
      target: "ea-mfa-login-enroll",
      title: "Why this screen",
      body: "Your organization requires an authenticator app before the workspace opens. This split-screen Set up MFA page is not only for email & password — it also appears after Sign in with Microsoft when you have not enrolled yet. Passkey sign-in may skip this step if your org treats passkeys as MFA.",
    },
    {
      id: "mfa-login-qr",
      path: "/login/mfa/enroll",
      entryPath: "/login/mfa/enroll",
      target: "ea-mfa-login-qr",
      title: "Scan this QR",
      body: "Open Microsoft Authenticator, Google Authenticator, or 1Password on your phone. Add an account and scan this QR. This is your real enrollment code — not a sample.",
      missingHint: "Wait a moment for Preparing enrollment… to finish, then continue.",
    },
    {
      id: "mfa-login-manual",
      path: "/login/mfa/enroll",
      entryPath: "/login/mfa/enroll",
      target: "ea-mfa-login-manual",
      title: "Can’t scan?",
      body: "Expand Can’t scan? Enter key manually and type the secret into your authenticator app instead of using the camera.",
      missingHint: "Wait for the QR to appear, then expand the manual entry section.",
    },
    {
      id: "mfa-login-code",
      path: "/login/mfa/enroll",
      entryPath: "/login/mfa/enroll",
      target: "ea-mfa-login-code",
      title: "Enter the 6-digit code",
      body: "Your app shows a rotating 6-digit code. Type it here, then choose Verify and continue.",
      missingHint: "Wait for the enrollment form to load.",
    },
    {
      id: "mfa-login-verify",
      path: "/login/mfa/enroll",
      entryPath: "/login/mfa/enroll",
      target: "ea-mfa-login-verify",
      title: "Verify and continue",
      body: "After a successful code, recovery codes appear. Save them, then Continue into the workspace. Skip tour anytime if you already know these steps.",
      missingHint: "Wait for the Verify button to appear after the QR loads.",
    },
    {
      id: "mfa-login-recovery",
      path: "/login/mfa/enroll",
      entryPath: "/login/mfa/enroll",
      target: "ea-mfa-login-recovery",
      title: "Save recovery codes",
      body: "After verify, store these one-time codes somewhere safe. You need one if you lose your phone. Then click Continue.",
      missingHint: "Complete Verify and continue first — recovery codes appear only after enrollment succeeds.",
    },
  ],
};

/** Start on Dashboard so the tour begins at the account menu. */
export function mfaTourStartHref(stepIndex = 0): string {
  const clamped = Math.max(0, Math.min(stepIndex, mfaLiveTour.steps.length - 1));
  return `/dashboard?tour=${MFA_LIVE_TOUR_ID}&tourStep=${clamped}`;
}

export function mfaLoginTourStartHref(stepIndex = 0): string {
  const clamped = Math.max(0, Math.min(stepIndex, mfaLoginEnrollLiveTour.steps.length - 1));
  return `/login/mfa/enroll?tour=${MFA_LOGIN_LIVE_TOUR_ID}&tourStep=${clamped}`;
}

export function isMfaTourId(tourId: string | null | undefined): boolean {
  return tourId === MFA_LIVE_TOUR_ID;
}

export function isMfaLoginTourId(tourId: string | null | undefined): boolean {
  return tourId === MFA_LOGIN_LIVE_TOUR_ID;
}

export function isAnyMfaTourId(tourId: string | null | undefined): boolean {
  return isMfaTourId(tourId) || isMfaLoginTourId(tourId);
}
