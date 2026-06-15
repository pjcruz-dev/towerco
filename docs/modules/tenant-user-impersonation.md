# Tenant user impersonation (Team & Access)

Tenant administrators with permission `user:impersonate` can start a **time-limited session** as another active user in the same tenant. This is intended for support and workflow debugging—not silent permanent access.

## Who can impersonate

| Role | Default |
|------|---------|
| `tenant_admin` | Yes (`user:impersonate` is in the enabled permission catalog and synced to this role) |
| `manager` | No (has `user:manage` only) |
| Custom roles | Grant `user:impersonate` explicitly in Team & Access → Roles |

## Rules

- Cannot impersonate yourself
- Cannot impersonate another `tenant_admin`
- Cannot impersonate deactivated users
- Cannot nest impersonation (end current session before starting another)
- Requires a **reason** (3–500 characters), stored in `auth_audit_logs`
- Token TTL: `TOWEROS_TENANT_IMPERSONATION_TTL_MINUTES` (default 30)
- Master switch: `TOWEROS_TENANT_IMPERSONATION_ENABLED` (default true)

## API

### Start

`POST /api/v1/admin/users/{userId}/impersonate`

```json
{ "reason": "Ticket INC-1234 — reproduce approval inbox" }
```

Response (same shape as login):

- `access_token`, `refresh_token`, `session_id`
- `user` with `is_impersonating: true` and `impersonator: { id, name, email }`

**TowerOS UI (Team & Access → Users):** Click **View as user** on an eligible row, enter a reason, then **Start session**. A warning banner appears on every page until **End session**. Parent admin tokens are stored in `sessionStorage` automatically.

**Manual API client:** Before swapping tokens, store the administrator’s `access_token`, `refresh_token`, and `session_id`. Then apply the impersonation tokens and reload `/me`.

### End

`POST /api/v1/auth/impersonation/stop`

Uses the **impersonation** bearer token. Revokes the impersonation session and deletes the token.

**Client:** Restore `impersonation_parent_*` credentials and clear impersonation storage.

### User list hint

`GET /api/v1/admin/users` includes `can_impersonate: boolean` per row for the current viewer.

## Audit events

| Event | Risk |
|-------|------|
| `auth.impersonation.started` | high |
| `auth.impersonation.stopped` | high |

Context includes `target_user_id`, `target_email`, and `reason` on start.

## RBAC after deploy

Run tenant RBAC sync so existing tenants receive the new permission:

```bash
php artisan tenants:ensure-rbac --domain=alliance.localhost
```

(see `EnsureTenantRbacBaseline` command).
