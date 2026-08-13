import type { AuthSession } from "@/types/auth";

/**
 * After password / SSO / passkey login (and after MFA), send the user to the right next step.
 */
export function tenantPostLoginPath(session: Pick<AuthSession, "passkeyEnrollmentRequired">): string {
  if (session.passkeyEnrollmentRequired) {
    return "/account/security?tab=passkeys&required=1";
  }

  return "/dashboard";
}
