# ADR / Phase 0: Tenant passkeys (WebAuthn) for sign-in

| Field | Value |
|--------|--------|
| Status | **Accepted** |
| Date | 2026-08-12 |
| Roadmap | [../roadmaps/tenant-passkeys-roadmap.md](../roadmaps/tenant-passkeys-roadmap.md) |
| Scope | Tenant workspace auth only (not platform console) |

## Context

Operators asked whether tenants can sign in with **fingerprint**. TowerOS today supports:

- Email + password
- Microsoft Entra ID SSO (when enabled)
- TOTP MFA + browser **device trust** (`device_fingerprint_hash` on `auth_devices` — not biometric)

There is no WebAuthn / passkey / biometric login path yet. Device biometrics must be delivered as **passkeys**, not as a proprietary fingerprint store.

## Decision

### 1. Technology: passkeys (WebAuthn), not raw fingerprint APIs

- Use platform authenticators (Windows Hello, Touch ID, Face ID, Android biometrics) via **WebAuthn**.
- TowerOS stores **public credentials** only (credential id, public key, sign count, label). Fingerprint templates never leave the device / OS.

### 2. Audience and rollout posture

- **Tenant users only** (Sanctum tenant session), not central platform console in this program.
- **Opt-in** for Phase 1–3: users may enroll; admins do not require passkeys yet.
- Optional **per-tenant feature flag** from Phase 3 so orgs can enable when ready.

### 3. Relationship to existing auth

| Method | Role |
|--------|------|
| Password | Remains available (unless tenant already disables password when SSO-only) |
| Microsoft SSO | Remains available when configured |
| Passkey | **Additional** sign-in option after enrollment |
| TOTP MFA | **Unchanged** through Phase 3 |

Phase 4 may allow “passkey satisfies MFA”; that is **out of scope** for Phases 1–3.

### 4. Enrollment and account binding

1. User must **sign in first** (password or Microsoft) to create a session.
2. User enrolls a passkey under Security / profile settings while authenticated.
3. TowerOS binds `credential → user_id` in the **tenant** database.
4. Later logins: “Sign in with passkey” verifies the assertion and opens a normal tenant session.

Fingerprint alone never invents an account; it only unlocks a credential already linked to a user.

### 5. Multi-device and multi-tenant

- **New laptop:** user signs in with password/SSO, then enrolls a new passkey on that device (unless the OS syncs passkeys — browser/OS dependent; not TowerOS-managed).
- **RP ID / origin:** Relying Party ID must match the **tenant hostname** (e.g. `atc.example.com`). Credentials are not portable across tenant domains.
- No cross-tenant credential reuse.

### 6. Login UX (Phase 2 target)

Prefer supporting both:

- **Discoverable credentials** (button-only: OS picker → fingerprint)
- **Email-assisted** (type email, then passkey) as fallback for browsers/devices that need it

### 7. Recovery (Phases 1–3)

If the user loses the device or passkey:

- Use **password** and/or **Microsoft SSO**
- Optionally enroll a replacement passkey
- Admin may revoke credentials (Phase 3)

Do **not** block account recovery behind passkey-only until Phase 4 policy explicitly requires it.

### 8. Security & compliance baseline

- HTTPS (or secure local tenant hosts) required for WebAuthn.
- Audit: `auth.webauthn.register`, `auth.webauthn.login`, `auth.webauthn.revoke` (exact event names may follow existing audit conventions).
- Rate-limit challenge/verify endpoints (Phase 3).
- Align with existing session creation (`AuthSessionService`) and MFA gates without bypassing tenant RBAC.

## Consequences

**Positive**

- Clear phased path; product can ship opt-in passkeys without changing MFA policy yet.
- Matches enterprise expectations (Azure/Atlassian-style passkeys).
- Fits multi-tenant host model via per-domain RP ID.

**Tradeoffs**

- Users must complete a first password/SSO login before fingerprint works.
- Each physical device typically needs its own enrollment.
- Phase 3 ops work (RP ID, flags, admin revoke) is required before broad production enablement.

## Out of scope (Phase 0)

- Platform console passkeys
- Replacing Microsoft SSO or password entirely
- Storing or matching fingerprint biometrics server-side
- Mobile-native SDKs beyond browser WebAuthn
- Phase 4 MFA substitution / “require passkey” tenant policy

## Exit criteria for Phase 0

- [x] Full phase list published on the roadmap
- [x] Decisions above accepted for implementation
- [ ] Product/ops acknowledge RP ID = tenant hostname and opt-in posture (implicit by starting Phase 1)

## Next

**Phase 1 complete** — see [tenant-passkeys-phase-1.md](./tenant-passkeys-phase-1.md).  
**Phase 2 complete** — see [tenant-passkeys-phase-2.md](./tenant-passkeys-phase-2.md).  
Proceed to **Phase 3 — Hardening & ops** when ready for production enablement.

## References (current auth)

- `backend/app/Modules/Identity/Http/Controllers/V1/TenantAuthController.php` — password login
- `backend/app/Modules/Identity/Http/Controllers/V1/TenantSsoController.php` — Microsoft SSO
- `backend/app/Modules/Identity/Services/MfaService.php` — TOTP + device trust (`currentDeviceFingerprint`)
- `frontend/app/(public)/login/login-page-client.tsx` — tenant login UI
