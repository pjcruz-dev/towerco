import type { AuthSession } from "@/types/auth";

/**
 * Accept only a same-app path. Reject protocol-relative and off-site values.
 */
export function safeInternalNext(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }
  let decoded = value.trim();
  try {
    decoded = decodeURIComponent(decoded);
  } catch {
    return null;
  }
  if (!decoded.startsWith("/") || decoded.startsWith("//") || decoded.startsWith("/login")) {
    return null;
  }
  if (decoded.includes("\\") || decoded.includes("://") || decoded.includes("@")) {
    return null;
  }
  if (!/^\/[A-Za-z0-9/_\-.]*(\?[A-Za-z0-9/_\-.=&%]*)?$/.test(decoded)) {
    return null;
  }
  return decoded;
}

export function loginPathWithNext(target: string, next: string | null | undefined): string {
  const safe = safeInternalNext(next);
  if (!safe) {
    return target;
  }
  const joiner = target.includes("?") ? "&" : "?";
  return `${target}${joiner}next=${encodeURIComponent(safe)}`;
}

/**
 * After password / SSO / passkey login (and after MFA), send the user to the right next step.
 * `next` is the module path they opened before sign-in (for example /e-approval).
 */
export function tenantPostLoginPath(
  session: Pick<AuthSession, "passkeyEnrollmentRequired">,
  next?: string | null,
): string {
  if (session.passkeyEnrollmentRequired) {
    return "/account/security?tab=passkeys&required=1";
  }

  return safeInternalNext(next) ?? "/dashboard";
}
