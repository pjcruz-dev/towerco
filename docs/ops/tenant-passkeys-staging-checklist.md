# Staging checklist — tenant passkeys (Phase 3)

Use on a staging tenant with a real HTTPS hostname (not only `*.localhost`).

## Preconditions
- [ ] Tenant DB migrated (`webauthn_credentials` present)
- [ ] API container has WebAuthn autoload (`composer dump-autoload -o` if needed)
- [ ] `TOWEROS_TENANT_PASSKEYS_ENABLED=true`
- [ ] Staging default: set `TOWEROS_TENANT_PASSKEYS_DEFAULT_ENABLED=false` then enable per tenant in UI, **or** enable the tenant toggle after deploy
- [ ] Browser supports WebAuthn + platform authenticator (or security key)

## Feature flag
- [ ] With passkeys **off**: login page has no “Sign in with passkey”; `POST .../webauthn/login/options` returns 403
- [ ] With passkeys **on** (Admin → Sign-in & security): login CTA appears; enroll works under My security → Passkeys
- [ ] Platform kill switch off (`TOWEROS_TENANT_PASSKEYS_ENABLED=false`): all tenants denied even if org toggle is on

## Enroll & login
- [ ] Enroll after password or Microsoft session
- [ ] Sign out → Sign in with passkey succeeds on the **same** tenant hostname
- [ ] Wrong tenant host / spoofed origin fails verification
- [ ] After passkey login, existing TOTP MFA challenge still applies when MFA policy is active

## Recovery & admin
- [ ] Password and/or Microsoft still work with passkeys enrolled
- [ ] User can remove own passkey
- [ ] Admin **Revoke passkeys** clears credentials; user must re-enroll
- [ ] Audit events: `auth.webauthn.register`, `auth.webauthn.login`, `auth.webauthn.revoke`, `auth.admin.webauthn_revoked`

## Rate limits / abuse
- [ ] Rapid login verify attempts return 429 after limit (15/min)
- [ ] Rapid register attempts return 429 after limit (10/min)

## Sign-off
- [ ] Staging owner: __________________ date: __________
- [ ] Ready for production org opt-in
