import type { AuthSession } from "@/types/auth";

/**
 * After password / SSO / passkey login (and after MFA), send the user to the right next step.
 */
export function tenantPostLoginPath(
  session: Pick<AuthSession, "passkeyEnrollmentRequired" | "user">,
): string {
  if (session.passkeyEnrollmentRequired) {
    return "/account/security?tab=passkeys&required=1";
  }

  const landing = session.user?.defaultLandingHref?.trim();
  if (landing && landing.startsWith("/") && !landing.startsWith("//")) {
    return landing;
  }

  return "/dashboard";
}
