# Tenant passkeys (fingerprint / Windows Hello) — roadmap

Phased delivery of **WebAuthn passkeys** for tenant workspace sign-in. Device biometrics (fingerprint, Face ID, Windows Hello) unlock a passkey; TowerOS never stores fingerprint images.

| Phase | Status | Doc |
|-------|--------|-----|
| **0** — Scope & decisions | **Accepted** | [architecture/tenant-passkeys-phase-0.md](../architecture/tenant-passkeys-phase-0.md) |
| **1** — Backend foundation | **Complete** | [architecture/tenant-passkeys-phase-1.md](../architecture/tenant-passkeys-phase-1.md) |
| **2** — Minimal tenant UI | **Complete** | [architecture/tenant-passkeys-phase-2.md](../architecture/tenant-passkeys-phase-2.md) |
| **3** — Hardening & ops | **Complete** | [architecture/tenant-passkeys-phase-3.md](../architecture/tenant-passkeys-phase-3.md) |
| **4** — Policy & MFA alignment | **Complete** | [architecture/tenant-passkeys-phase-4.md](../architecture/tenant-passkeys-phase-4.md) |

---

## Phase summary

### Phase 0 — Scope & decisions (done)
Lock product rules: passkeys only, opt-in, keep password + Microsoft SSO, enrollment after first sign-in, TOTP MFA unchanged until Phase 4.

### Phase 1 — Backend foundation (done)
- Tenant migration: `webauthn_credentials` (+ challenge storage or cache)
- APIs: register options/verify (authenticated), login options/verify
- Relying Party ID = tenant hostname; tenant isolation + audit events
- Details: [../architecture/tenant-passkeys-phase-1.md](../architecture/tenant-passkeys-phase-1.md)

### Phase 2 — Minimal tenant UI (done)
- Security settings: add / list / revoke passkeys
- Login page: “Sign in with passkey”
- Details: [../architecture/tenant-passkeys-phase-2.md](../architecture/tenant-passkeys-phase-2.md)

### Phase 3 — Hardening & ops (done) — **enable / disable**
- Origins / RP ID checks; rate limits; admin revoke-all
- **Per-tenant feature flag** + platform kill switch; staging checklist
- Details: [../architecture/tenant-passkeys-phase-3.md](../architecture/tenant-passkeys-phase-3.md)

### Phase 4 — Policy & MFA alignment (done) — **not** enable/disable
- Policy: allow / prefer / require
- Passkey login can satisfy MFA (skip TOTP)
- Details: [../architecture/tenant-passkeys-phase-4.md](../architecture/tenant-passkeys-phase-4.md)

---

## Cross-cutting rules (from Phase 0)

- **Not** raw fingerprint APIs — use **passkeys (WebAuthn)**.
- First enrollment requires an existing session (password or Microsoft), unless already using a passkey.
- Passkeys are **per tenant**; never shared across tenants.
- Password and/or Microsoft SSO remain recovery paths (even under require).

## Ops reminder

Run [../ops/tenant-passkeys-staging-checklist.md](../ops/tenant-passkeys-staging-checklist.md) and verify Phase 4 MFA skip + require enrollment on HTTPS staging.
