import { apiClient } from "@/lib/api/client";
import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";
import type { AuthSession } from "@/types/auth";

type LoginPayload = {
  email: string;
  password: string;
};

export async function login(payload: LoginPayload): Promise<AuthSession> {
  const response = await apiClient.post<{ data: unknown }>(
    process.env.NEXT_PUBLIC_AUTH_LOGIN_PATH ?? "/auth/login",
    payload,
    { timeout: 60_000 },
  );
  return normalizeAuthSession(response.data.data);
}

export async function me(): Promise<AuthSession["user"]> {
  const response = await apiClient.get<{ data: unknown }>(
    process.env.NEXT_PUBLIC_AUTH_ME_PATH ?? "/me",
  );
  return normalizeAuthSession({ user: response.data.data }).user;
}

export async function refresh(refreshToken: string): Promise<AuthSession> {
  const response = await apiClient.post<{ data: unknown }>(
    process.env.NEXT_PUBLIC_AUTH_REFRESH_PATH ?? "/auth/refresh",
    { refresh_token: refreshToken },
  );
  return normalizeAuthSession(response.data.data);
}

export async function logout(): Promise<void> {
  await apiClient.post(process.env.NEXT_PUBLIC_AUTH_LOGOUT_PATH ?? "/auth/logout");
}

export async function logoutAll(): Promise<void> {
  await apiClient.post(
    process.env.NEXT_PUBLIC_AUTH_LOGOUT_ALL_PATH ?? "/auth/logout-all",
  );
}

export async function fetchSessions(): Promise<
  Array<{
    id: string;
    auth_method: string;
    state: string;
    ip_address: string | null;
    last_seen_at: string | null;
    created_at: string;
    mfa_verified_at: string | null;
    device_name: string | null;
    trust_level: string | null;
  }>
> {
  const response = await apiClient.get<{ data: Array<Record<string, unknown>> }>(
    process.env.NEXT_PUBLIC_AUTH_SESSIONS_PATH ?? "/auth/sessions",
  );
  return response.data.data as Array<{
    id: string;
    auth_method: string;
    state: string;
    ip_address: string | null;
    last_seen_at: string | null;
    created_at: string;
    mfa_verified_at: string | null;
    device_name: string | null;
    trust_level: string | null;
  }>;
}

export async function revokeSession(sessionId: string): Promise<void> {
  await apiClient.delete(
    `${process.env.NEXT_PUBLIC_AUTH_SESSIONS_PATH ?? "/auth/sessions"}/${sessionId}`,
  );
}

export async function requestMfaChallenge(sessionId: string): Promise<{ id: string; expires_at: string }> {
  const response = await apiClient.post<{ data: { id: string; expires_at: string } }>(
    process.env.NEXT_PUBLIC_AUTH_MFA_CHALLENGE_PATH ?? "/auth/mfa/challenge",
    { session_id: sessionId },
  );
  return response.data.data;
}

export async function verifyMfaChallenge(payload: {
  challengeId: string;
  code: string;
  sessionId: string;
}): Promise<void> {
  await apiClient.post(
    process.env.NEXT_PUBLIC_AUTH_MFA_VERIFY_PATH ?? "/auth/mfa/verify",
    {
      challenge_id: payload.challengeId,
      code: payload.code,
      session_id: payload.sessionId,
    },
  );
}

export async function verifyMfaRecoveryCode(payload: {
  sessionId: string;
  recoveryCode: string;
}): Promise<void> {
  await apiClient.post(
    process.env.NEXT_PUBLIC_AUTH_MFA_RECOVERY_PATH ?? "/auth/mfa/recovery",
    {
      session_id: payload.sessionId,
      recovery_code: payload.recoveryCode,
    },
  );
}

export async function startMfaEnrollment(): Promise<{ secret: string; otpauth_uri: string }> {
  const response = await apiClient.post<{ data: { secret: string; otpauth_uri: string } }>(
    process.env.NEXT_PUBLIC_AUTH_MFA_ENROLL_START_PATH ?? "/auth/mfa/enroll/start",
  );
  return response.data.data;
}

export async function completeMfaEnrollment(code: string): Promise<{ recovery_codes: string[] }> {
  const response = await apiClient.post<{ data: { recovery_codes: string[] } }>(
    process.env.NEXT_PUBLIC_AUTH_MFA_ENROLL_COMPLETE_PATH ?? "/auth/mfa/enroll/complete",
    { code },
  );
  return response.data.data;
}

export async function regenerateRecoveryCodes(): Promise<{ recovery_codes: string[] }> {
  const response = await apiClient.post<{ data: { recovery_codes: string[] } }>(
    process.env.NEXT_PUBLIC_AUTH_MFA_RECOVERY_REGENERATE_PATH ??
      "/auth/mfa/recovery-codes/regenerate",
  );
  return response.data.data;
}

export type WebAuthnCredentialRow = {
  id: string;
  label: string | null;
  transports: string[] | null;
  attestation_format: string | null;
  last_used_at: string | null;
  created_at: string | null;
};

export type WebAuthnCeremonyOptions = {
  challenge_id: string;
  publicKey: Record<string, unknown>;
  rp_id: string;
};

export async function fetchWebAuthnCredentials(): Promise<{
  enabled: boolean;
  policy: "allow" | "prefer" | "require";
  satisfies_mfa: boolean;
  enrollment_required: boolean;
  credentials: WebAuthnCredentialRow[];
  rp_id: string;
}> {
  const response = await apiClient.get<{
    data: {
      enabled: boolean;
      policy: "allow" | "prefer" | "require";
      satisfies_mfa: boolean;
      enrollment_required: boolean;
      credentials: WebAuthnCredentialRow[];
      rp_id: string;
    };
  }>("/auth/webauthn/credentials");
  return response.data.data;
}

export async function webAuthnRegisterOptions(label?: string): Promise<WebAuthnCeremonyOptions> {
  const response = await apiClient.post<{ data: WebAuthnCeremonyOptions }>(
    "/auth/webauthn/register/options",
    label ? { label } : {},
  );
  return response.data.data;
}

export async function webAuthnRegisterVerify(payload: {
  challengeId: string;
  credential: Record<string, unknown>;
  label?: string;
}): Promise<WebAuthnCredentialRow> {
  const response = await apiClient.post<{ data: { credential: WebAuthnCredentialRow } }>(
    "/auth/webauthn/register/verify",
    {
      challenge_id: payload.challengeId,
      credential: payload.credential,
      ...(payload.label ? { label: payload.label } : {}),
    },
  );
  return response.data.data.credential;
}

export async function revokeWebAuthnCredential(id: string): Promise<void> {
  await apiClient.delete(`/auth/webauthn/credentials/${id}`);
}

export async function webAuthnLoginOptions(email?: string): Promise<WebAuthnCeremonyOptions> {
  const response = await apiClient.post<{ data: WebAuthnCeremonyOptions }>(
    "/auth/webauthn/login/options",
    email ? { email } : {},
    { timeout: 60_000 },
  );
  return response.data.data;
}

export async function webAuthnLoginVerify(payload: {
  challengeId: string;
  credential: Record<string, unknown>;
}): Promise<AuthSession> {
  const response = await apiClient.post<{ data: unknown }>(
    "/auth/webauthn/login/verify",
    {
      challenge_id: payload.challengeId,
      credential: payload.credential,
    },
    { timeout: 60_000 },
  );
  return normalizeAuthSession(response.data.data);
}
