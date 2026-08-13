# Phase 4: Tenant passkeys — policy & MFA alignment

| Field | Value |
|--------|--------|
| Status | **Complete** |
| Depends on | [tenant-passkeys-phase-3.md](./tenant-passkeys-phase-3.md) |
| Roadmap | [../roadmaps/tenant-passkeys-roadmap.md](../roadmaps/tenant-passkeys-roadmap.md) |

## Not enable/disable

**Enable / disable passkeys** is **Phase 3** (`passkeys_enabled` + platform kill switch).

Phase 4 adds **how** passkeys interact with MFA and enrollment posture once they are enabled.

## Delivered

### Policy modes (`passkeys_policy`)
| Mode | Behavior |
|------|----------|
| `allow` | Optional (default) |
| `prefer` | Soft nudge on login + My security when user has no passkey |
| `require` | After password/Microsoft (or passkey) login, user must enroll; `auth.passkey` middleware blocks other APIs until enrolled. Break-glass `password_login_exempt` users are exempt. |

### Passkey satisfies MFA (`passkeys_satisfies_mfa`)
- Default **true** (config `TOWEROS_TENANT_PASSKEYS_SATISFIES_MFA`)
- When on: successful **passkey** login skips TOTP challenge and marks session MFA-verified
- Password and Microsoft sign-in still follow existing MFA + device-trust rules

### Admin UI
Administration → Sign-in & security → Passkeys:
- Enable (Phase 3)
- Policy select
- “Passkey sign-in satisfies authenticator MFA”

### Public status
`GET /auth/sso/azure/status` → `passkeys.policy`, `passkeys.satisfies_mfa`

### Middleware
`auth.passkey` (`EnsurePasskeyEnrollment`) on authenticated tenant API group.

## Recovery
Password and Microsoft remain available even under **require**. Require forces enrollment after that first factor; it does not lock users out of recovery methods.
