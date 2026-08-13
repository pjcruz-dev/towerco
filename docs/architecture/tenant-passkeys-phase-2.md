# Phase 2: Tenant passkeys — minimal UI

| Field | Value |
|--------|--------|
| Status | **Complete** |
| Depends on | [tenant-passkeys-phase-1.md](./tenant-passkeys-phase-1.md) |
| Roadmap | [../roadmaps/tenant-passkeys-roadmap.md](../roadmaps/tenant-passkeys-roadmap.md) |

## Delivered

### My security — Passkeys tab
- Path: `/account/security?tab=passkeys` (also `/settings/security/passkeys` redirect)
- Add passkey (device label + browser prompt)
- List registered passkeys
- Remove passkey with confirm
- Empty / unsupported-browser messaging

### Login
- **Sign in with passkey** on tenant login (password and Microsoft-primary layouts)
- Optional email from the form scopes `allowCredentials`; empty email uses discoverable credentials
- Cancelled / unsupported errors surfaced via login notice
- Post-login MFA gate unchanged (Phase 0)

### Frontend modules
- `frontend/lib/webauthn/browser.ts` — ArrayBuffer ↔ base64url, create/get serialization
- `frontend/lib/api/modules/auth-api.ts` — WebAuthn API client helpers
- `frontend/components/auth/passkeys-settings-panel.tsx`

## How to try
1. Migrate tenant DB (Phase 1 table).
2. Sign in with password or Microsoft.
3. Open **profile → My security → Passkeys** → **Add passkey**.
4. Sign out → **Sign in with passkey** on `/login`.

## Not in Phase 2
- Feature flag / admin revoke-all (Phase 3)
- Passkey satisfying MFA (Phase 4)
