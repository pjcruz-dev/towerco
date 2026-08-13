# Phase 3: Tenant passkeys — hardening & ops

| Field | Value |
|--------|--------|
| Status | **Complete** |
| Depends on | [tenant-passkeys-phase-2.md](./tenant-passkeys-phase-2.md) |
| Roadmap | [../roadmaps/tenant-passkeys-roadmap.md](../roadmaps/tenant-passkeys-roadmap.md) |

## Delivered

### Per-tenant feature flag
- Platform kill switch: `TOWEROS_TENANT_PASSKEYS_ENABLED` (`toweros.tenant_passkeys.enabled`)
- Default when tenant unset: `TOWEROS_TENANT_PASSKEYS_DEFAULT_ENABLED` (true for `local`/`testing`, false otherwise)
- Tenant toggle: Admin → **Sign-in & security** → Passkeys (`passkeys_enabled` on tenant `data` JSON)
- Public status: `GET /api/v1/auth/sso/azure/status` includes `passkeys.enabled`
- Login UI and enroll APIs honor the flag; list/self-revoke remain available for cleanup

### Admin revoke-all
- `POST /api/v1/admin/users/{user}/revoke-passkeys` (`user:manage`)
- Team & Access user drawer → Activity → **Revoke passkeys**
- Audit: `auth.admin.webauthn_revoked`

### Origin / RP hardening
- Challenge stores and re-checks `rp_id`
- `clientData.origin` must match `WebAuthnRelyingParty::allowedOrigins()` (tenant host + optional `TOWEROS_WEBAUTHN_EXTRA_ORIGINS`)
- Production hosts continue to use HTTPS via `FrontendDevUrl::schemeForTenantHost`

### Rate limits
| Route | Limit |
|-------|--------|
| Login options | 20/min |
| Login verify | 15/min |
| Register options/verify | 10/min |
| Self revoke | 20/min |
| Admin revoke-passkeys | 20/min |

### Recovery (product rule)
Password and/or Microsoft SSO remain available in this phase. Passkeys are optional convenience, not the only recovery path.

## Staging checklist

See [../ops/tenant-passkeys-staging-checklist.md](../ops/tenant-passkeys-staging-checklist.md).

## Admin / end-user notes

**Admins**
1. Enable passkeys under Administration → Sign-in & security when the org is ready.
2. Users enroll after first password or Microsoft sign-in (My security → Passkeys).
3. Lost device: revoke that user’s passkeys from Team & Access → Activity (or ask the user to remove the passkey).
4. Disable the org flag to hide login CTA and block new enrollments without deleting existing credentials.

**End users**
1. Sign in with password or Microsoft once.
2. Open My security → Passkeys → Add passkey (fingerprint / Face ID / Windows Hello).
3. Next visit: Sign in with passkey on the organization login URL.
4. If the passkey is missing or the org disabled passkeys, use password or Microsoft.

## Not in Phase 3
- Passkey satisfying MFA / skip TOTP (Phase 4)
- Require-passkey policy (Phase 4)
