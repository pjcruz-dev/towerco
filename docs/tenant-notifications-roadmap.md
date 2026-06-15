# Tenant notification center — roadmap

Unified in-app notifications across tenant modules (E-Approval, PROJECT-ONE gate approvals, future modules).

## Phase A — Rich payloads + bell UI ✅

| Deliverable | Status |
|-------------|--------|
| Enriched `e_approval_notifications` columns | Done |
| Category (`action` / `update`) + deep links | Done |
| Header bell with tabs, avatars, previews | Done |

## Phase B — Comment notifications ✅

| Deliverable | Status |
|-------------|--------|
| Notify on comment / reply | Done |
| Comments tab deep links | Done |

## Phase C — Full page + pagination ✅

| Deliverable | Status |
|-------------|--------|
| Paginated notifications API | Done |
| `/e-approval/notifications` page | Done (later moved to `/notifications`) |

## Phase D — Tenant-wide store ✅

| Deliverable | Status |
|-------------|--------|
| `tenant_notifications` table + backfill | Done |
| `GET /notifications` with module filter | Done |
| E-Approval + PROJECT-ONE gate in-app events | Done |
| Unified bell + `/notifications` page | Done |

## Phase E — Realtime refresh ✅

| Deliverable | Status |
|-------------|--------|
| `TenantNotificationCreated` broadcast (per-user private channel) | Done |
| Channel auth `tenant.{id}.user.{userId}.notifications` | Done |
| `useTenantNotificationRealtime` in workspace shell | Done |
| Bell + sidebar badge refresh without polling | Done |

### Enable realtime (local)

```env
# backend
BROADCAST_CONNECTION=pusher

# frontend
NEXT_PUBLIC_SOCKET_ENABLED=true
NEXT_PUBLIC_PUSHER_APP_KEY=toweros-local
NEXT_PUBLIC_PUSHER_HOST=127.0.0.1
NEXT_PUBLIC_PUSHER_PORT=6001
NEXT_PUBLIC_PUSHER_SCHEME=http
```

When sockets are disabled, notifications still work via 60s polling on the unread-count query.

---

## Verification

```bash
cd backend
php artisan test --filter=TenantNotificationBroadcastTest
php artisan tenants:migrate --force
```

Create a gate approval or E-Approval action in one browser tab; the bell unread count in another tab should update within a second when Echo is enabled.
