/** Spatie role name from the tenant guard (baseline or custom). */
export type UserRole = string;

export type TenantAccess = {
  tenantId: string;
  tenantName: string;
  tenantDomain?: string;
  roles: UserRole[];
  permissions: string[];
  enabledModules?: string[];
};

export type AuthImpersonator = {
  id: string;
  name: string;
  email: string;
  source?: "platform" | "tenant";
};

export type AuthUser = {
  id: string;
  name: string;
  email: string;
  tenantId: string | null;
  tenantDomain?: string;
  roles: UserRole[];
  permissions: string[];
  enabledModules?: string[];
  tenantAccesses: TenantAccess[];
  isImpersonating?: boolean;
  impersonator?: AuthImpersonator;
};

export type AuthSession = {
  accessToken: string | null;
  refreshToken: string | null;
  sessionId?: string | null;
  mfaRequired?: boolean;
  mfaEnrollmentRequired?: boolean;
  /** Phase 4: org requires passkey enrollment before full workspace access. */
  passkeyEnrollmentRequired?: boolean;
  mfaChallenge?: {
    id: string;
    expires_at: string;
  } | null;
  user: AuthUser | null;
};
