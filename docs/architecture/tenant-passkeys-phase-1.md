# Phase 1: Tenant passkeys — backend foundation

| Field | Value |
|--------|--------|
| Status | **Complete** (APIs; no UI) |
| Depends on | [tenant-passkeys-phase-0.md](./tenant-passkeys-phase-0.md) |
| Roadmap | [../roadmaps/tenant-passkeys-roadmap.md](../roadmaps/tenant-passkeys-roadmap.md) |

## Delivered

### Data
- Tenant migration `webauthn_credentials` (`database/migrations/tenant/2026_08_12_140000_create_webauthn_credentials_table.php`)
- Model `App\Modules\Identity\Models\WebAuthnCredential`

### Library
- Vendored MIT `lbuchs/webauthn` under `backend/packages/lbuchs-webauthn` (Packagist was unreachable during install)
- PSR-4 autoload in `composer.json`; local `*.localhost` HTTP origins allowed for RP checks

### Services
- `WebAuthnRelyingParty` — RP ID = tenant hostname
- `WebAuthnChallengeStore` — cache-backed ceremony challenges (5 min TTL)
- `WebAuthnPasskeyService` — register/login options + verify, list, revoke + audit events

### HTTP API (tenant)

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/v1/auth/webauthn/login/options` | Public | Login challenge (optional `email`) |
| POST | `/api/v1/auth/webauthn/login/verify` | Public | Verify assertion → Sanctum session |
| GET | `/api/v1/auth/webauthn/credentials` | Session | List passkeys |
| POST | `/api/v1/auth/webauthn/register/options` | Session | Registration challenge |
| POST | `/api/v1/auth/webauthn/register/verify` | Session | Store new passkey |
| DELETE | `/api/v1/auth/webauthn/credentials/{id}` | Session | Revoke own passkey |

### Audit events
- `auth.webauthn.register` / `auth.webauthn.register.failed`
- `auth.webauthn.login` / `auth.webauthn.login.failed`
- `auth.webauthn.revoke`

### MFA
Passkey login still runs existing TOTP MFA gate (Phase 0 decision). Phase 4 may treat passkey as MFA.

## Tests
- `tests/Unit/Identity/WebAuthnRelyingPartyTest.php`
- `tests/Feature/Identity/TenantWebAuthnApiTest.php`

## Not in Phase 1
- Frontend enroll / login UI (Phase 2)
- Per-tenant feature flag, admin revoke-all, production checklist (Phase 3)

## Next
Phase 3 — Hardening & ops (feature flag, admin revoke-all, staging checklist).
