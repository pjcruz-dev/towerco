import type { AuthImpersonator, AuthSession, AuthUser, TenantAccess, UserRole } from "@/types/auth";

type UnknownRecord = Record<string, unknown>;

function asString(value: unknown, fallback = ""): string {
  return typeof value === "string" ? value : fallback;
}

function asStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) return [];
  return value.filter((item): item is string => typeof item === "string");
}

function asRoles(value: unknown): UserRole[] {
  return asStringArray(value);
}

function parseTenantAccesses(value: unknown): TenantAccess[] {
  if (!Array.isArray(value)) return [];

  return value
    .map((item) => {
      if (!item || typeof item !== "object") return null;
      const record = item as UnknownRecord;
      const tenantId = asString(record.tenant_id ?? record.tenantId);
      const tenantDomain = asString(record.tenant_domain ?? record.tenantDomain);
      const tenantName = asString(record.tenant_name ?? record.tenantName, tenantDomain || tenantId);
      if (!tenantId) return null;

      const enabledModules = asStringArray(record.enabled_modules ?? record.enabledModules);

      return {
        tenantId,
        tenantName,
        ...(tenantDomain ? { tenantDomain } : {}),
        roles: asRoles(record.roles),
        permissions: asStringArray(record.permissions),
        ...(enabledModules.length > 0 ? { enabledModules } : {}),
      } satisfies TenantAccess;
    })
    .filter((item): item is TenantAccess => item !== null);
}

export function normalizeAuthSession(payload: unknown): AuthSession {
  const record = (payload ?? {}) as UnknownRecord;
  const userData = (record.user ?? null) as UnknownRecord | null;

  const tenantAccesses = parseTenantAccesses(userData?.tenant_accesses ?? userData?.tenantAccesses);
  const enabledModules = asStringArray(userData?.enabled_modules ?? userData?.enabledModules);

  const impersonatorRaw = userData?.impersonator;
  let impersonator: AuthImpersonator | undefined;
  if (impersonatorRaw && typeof impersonatorRaw === "object") {
    const imp = impersonatorRaw as UnknownRecord;
    const impId = asString(imp.id);
    if (impId) {
      const sourceRaw = asString(imp.source);
      impersonator = {
        id: impId,
        name: asString(imp.name),
        email: asString(imp.email),
        ...(sourceRaw === "platform" || sourceRaw === "tenant" ? { source: sourceRaw } : {}),
      };
    }
  }

  const isImpersonating = Boolean(
    userData?.is_impersonating ?? userData?.isImpersonating ?? impersonator,
  );

  const user: AuthUser | null = userData
    ? {
        id: asString(userData.id),
        name: asString(userData.name),
        email: asString(userData.email),
        tenantId: asString(userData.tenant_id ?? userData.tenantId) || null,
        ...(asString(userData.tenant_domain ?? userData.tenantDomain)
          ? { tenantDomain: asString(userData.tenant_domain ?? userData.tenantDomain) }
          : {}),
        roles: asRoles(userData.roles),
        permissions: asStringArray(userData.permissions),
        ...(enabledModules.length > 0 ? { enabledModules } : {}),
        tenantAccesses,
        ...(isImpersonating ? { isImpersonating: true } : {}),
        ...(impersonator ? { impersonator } : {}),
      }
    : null;

  return {
    accessToken: asString(record.access_token ?? record.accessToken) || null,
    refreshToken: asString(record.refresh_token ?? record.refreshToken) || null,
    sessionId: asString(record.session_id ?? record.sessionId) || null,
    mfaRequired: Boolean(record.mfa_required ?? record.mfaRequired),
    mfaEnrollmentRequired: Boolean(
      record.mfa_enrollment_required ?? record.mfaEnrollmentRequired,
    ),
    mfaChallenge:
      record.mfa_challenge && typeof record.mfa_challenge === "object"
        ? {
            id: asString((record.mfa_challenge as UnknownRecord).id),
            expires_at: asString((record.mfa_challenge as UnknownRecord).expires_at),
          }
        : null,
    user,
  };
}
