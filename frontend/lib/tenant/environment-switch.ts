import type { WorkspaceEnvironmentLink } from "@/lib/api/modules/workspace-environments-api";

export const ENV_SWITCH_ACTOR_EMAIL_KEY = "toweros.env_switch_actor_email";

export function rememberEnvSwitchActorEmail(email: string | null | undefined): void {
  const normalized = email?.trim().toLowerCase() ?? "";
  if (!normalized) {
    return;
  }

  try {
    sessionStorage.setItem(ENV_SWITCH_ACTOR_EMAIL_KEY, normalized);
  } catch {
    // private mode / blocked storage
  }
}

export function readEnvSwitchActorEmail(): string | null {
  try {
    return sessionStorage.getItem(ENV_SWITCH_ACTOR_EMAIL_KEY);
  } catch {
    return null;
  }
}

export function clearEnvSwitchActorEmail(): void {
  try {
    sessionStorage.removeItem(ENV_SWITCH_ACTOR_EMAIL_KEY);
  } catch {
    // ignore
  }
}

/**
 * Build a fallback URL when seamless handoff is unavailable.
 * Prefer Microsoft SSO soft-switch when the target env has it; otherwise login with the actor email.
 */
export function buildEnvironmentFallbackUrl(
  env: WorkspaceEnvironmentLink,
  actorEmail?: string | null,
): string {
  if (env.sso_enabled) {
    return env.switch_url ?? env.login_url;
  }

  const email = actorEmail?.trim() ?? "";
  if (!email) {
    return env.login_url;
  }

  try {
    const url = new URL(env.login_url);
    url.searchParams.set("email", email);
    return url.toString();
  } catch {
    const separator = env.login_url.includes("?") ? "&" : "?";
    return `${env.login_url}${separator}email=${encodeURIComponent(email)}`;
  }
}

/**
 * Prefer the remembered corporate email. Only remap *@*.localhost bootstrap admins
 * onto the target hostname (admin@staging… → admin@app…).
 */
export function resolveEnvSwitchLoginEmail(
  failureDetail: string,
  browserHostname: string,
): string {
  const remembered = readEnvSwitchActorEmail();
  if (remembered) {
    return remembered;
  }

  const match = failureDetail.match(/[\w.+-]+@[\w.-]+/);
  if (!match) {
    return "";
  }

  const email = match[0].toLowerCase();
  if (!email.endsWith(".localhost") || !browserHostname) {
    return email;
  }

  const local = email.split("@")[0] ?? "";
  if (!local) {
    return email;
  }

  return `${local}@${browserHostname.toLowerCase()}`;
}
