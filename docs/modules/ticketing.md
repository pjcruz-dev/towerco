# Ticketing module

Cross-module issue tracking for TowerOS tenants. Users can raise tickets manually or from other modules via API links. Attachments and pictures are supported on Enterprise plans.

## Commercial gating

| Plan | Module entitlement | File uploads |
|------|-------------------|--------------|
| Starter | Disabled | No |
| Professional | Disabled (override via platform) | No |
| Enterprise | Enabled | Yes (up to 10 attachments per ticket) |

Platform operators can also:

- Enable the **ticketing** module per tenant under **Tenants → Modules**
- Override entitlements via `billing_overrides.modules.ticketing` on the tenant record

## Why Ticketing may not appear in the sidebar

The module must be in the **platform catalog** and **effective for the tenant**:

1. `TOWEROS_TENANT_ENABLED_MODULES` must include `ticketing` (default in `config/toweros.php` since TowerOS ships with this module).
2. Tenant `enabled_modules` is null (inherits platform list) **or** explicitly includes `ticketing`.
3. Run RBAC sync after enabling: `php artisan tenants:ensure-rbac`
4. User must log out/in (or refresh `/me`) so `enabled_modules` and `ticketing:*` permissions load.
5. **Enterprise** plan (or `billing_overrides.modules.ticketing`) is required for API access — Starter/Professional return 422 until upgraded.

## Deployment (standard)

TowerOS does **not** support runtime upload of PHP/JS module code. The recommended approach:

1. Deploy a new application release (backend + frontend)
2. Run `php artisan migrate` and `php artisan tenants:migrate`
3. Enable **ticketing** for the tenant (Platform → Tenants → Modules)
4. Ensure plan tier is Enterprise or apply a billing override

**Category packs** and SLA thresholds are configured per tenant under **Ticketing Settings** (data only — not executable code).

## Phase 1 (complete)

| Feature | Behavior |
|---------|----------|
| IT notifications | Per-tenant **IT group mailbox** in Ticketing Settings |
| Requestor visibility | **Own tickets only** (not assignee-based for non-resolvers) |
| Reopen | Requestor can reopen resolved/closed tickets → status **`open`**, clears resolved/closed timestamps; **IT gets email + in-app** |
| Resolve | Resolver must add **resolution comment**; **email + in-app to requestor** |
| Create ticket | **No priority** on create UI; server defaults to `normal`; IT sets priority on triage |
| Attachments | On create; resolvers can view/download |
| In-app | Bell notifications for IT (create/reopen) and requestor (resolve) |
| Settings | `/ticketing/settings` — IT email, toggles, test email |

## Phase 2 (complete)

| Feature | Behavior |
|---------|----------|
| Raise from E-Approval | **Raise ticket** on submission detail; pre-filled source + link |
| Raise from Project-One | **Raise ticket** on rollout and project detail headers |
| Related tickets | List tickets by `source_module` + `source_reference_id` on source records |
| Ticket links | Ticket detail shows deep links back to E-Approval / Project-One |
| Assignee notifications | Email + in-app when assignee is set on create or reassigned |
| Source filter | `GET /ticketing/tickets?source_module=&source_reference_id=` |

## Phase 3 (complete)

| Feature | Behavior |
|---------|----------|
| Raise from Sites | Site detail (`/sites/{id}`) with **Raise ticket** + related tickets |
| Raise from Tower-One | Tower detail (`/tower-one/towers/{id}`) with **Raise ticket** + related tickets |
| Raise from Asset-One | Asset detail (`/asset-one/assets/{id}`) with **Raise ticket** + related tickets |
| Registry links | Site, tower, and asset list rows link to detail pages |
| Internal comments | IT can post **internal notes**; hidden from requestors; badge in UI |
| Show APIs | `GET /sites/{id}`, `GET /tower-one/towers/{id}`, `GET /asset-one/assets/{id}` |

## Phase 4 (complete)

| Feature | Behavior |
|---------|----------|
| Category pack | Tenant-configurable category list in settings; validated on create/update |
| SLA tracking | `sla_due_at` and `sla_status` (`on_track`, `at_risk`, `breached`) on tickets |
| SLA scheduler | `php artisan ticketing:sla-run` every 5 minutes (Laravel schedule) |
| SLA reminders | In-app + optional Teams webhook when response window elapses |
| SLA escalation | In-app + optional Teams webhook when escalation window elapses |
| Teams webhook | Optional Microsoft Teams incoming webhook URL + per-event toggles |
| Settings UI | `/ticketing/settings` — categories, SLA minutes, webhook URL, test webhook |
| Dashboard KPI | **SLA at risk** count for users with `ticketing:tickets:manage` |

After tenant deploy, run `php artisan tenants:migrate` for SLA columns (`sla_due_at`, `sla_reminder_sent_at`, `sla_escalated_at`).

## Future enhancements (not scheduled)

| Item | Scope |
|------|-------|
| Per-category SLA templates | Different response/escalation per category |
| Generic outbound webhooks | Beyond Teams MessageCard format |
| SLA audit history | Timeline of reminders and escalations |
| Auto-assign / triage queues | Rules-based routing to IT groups |

## API (tenant)

### Ticketing

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/ticketing/dashboard` | `ticketing:view` |
| GET | `/api/v1/ticketing/settings` | `ticketing:settings:manage` |
| PUT | `/api/v1/ticketing/settings` | `ticketing:settings:manage` |
| POST | `/api/v1/ticketing/settings/test-email` | `ticketing:settings:manage` |
| POST | `/api/v1/ticketing/settings/test-webhook` | `ticketing:settings:manage` |
| GET | `/api/v1/ticketing/metadata` | `ticketing:view` |
| GET | `/api/v1/ticketing/tickets` | `ticketing:view` |
| POST | `/api/v1/ticketing/tickets` | `ticketing:tickets:create` |
| GET | `/api/v1/ticketing/tickets/{id}` | `ticketing:view` |
| PATCH | `/api/v1/ticketing/tickets/{id}` | `ticketing:view` (+ manage/reopen rules) |
| POST | `/api/v1/ticketing/tickets/{id}/comments` | `ticketing:view` |
| POST | `/api/v1/ticketing/tickets/{id}/attachments` | `ticketing:tickets:create` |
| GET | `/api/v1/ticketing/attachments/{id}` | `ticketing:view` |

### Infrastructure show (Phase 3)

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/sites/{id}` | `sites:view` |
| GET | `/api/v1/tower-one/towers/{id}` | `tower_one:view` |
| GET | `/api/v1/asset-one/assets/{id}` | `asset_one:view` |

### List filters

| Query param | Description |
|-------------|-------------|
| `status`, `priority`, `assignee_id`, `mine`, `search` | Standard list filters |
| `source_module` | Filter by originating module (e.g. `e_approval`, `project_one`, `sites`) |
| `source_reference_id` | Filter by linked record UUID |

### Raising from another module (dynamic source)

```json
POST /api/v1/ticketing/tickets
{
  "title": "E-Approval submission blocked",
  "description": "...",
  "source_module": "e_approval",
  "source_reference_type": "submission",
  "source_reference_id": "<uuid>",
  "source_label": "Submission #42",
  "links": [
    {
      "link_module": "e_approval",
      "link_type": "submission",
      "link_id": "<uuid>",
      "link_label": "Submission #42"
    }
  ]
}
```

**Sites example:**

```json
{
  "title": "Site access issue",
  "source_module": "sites",
  "source_reference_type": "site",
  "source_reference_id": "<site-uuid>",
  "source_label": "SITE-001"
}
```

Priority is omitted on create and defaults to `normal` server-side.

### Comments

```json
POST /api/v1/ticketing/tickets/{id}/comments
{ "body": "IT triage note.", "is_internal": true }
```

Only users with `ticketing:tickets:manage` may set `is_internal: true`. Requestors only see public comments on ticket detail.

### Reopen (requestor)

```json
PATCH /api/v1/ticketing/tickets/{id}
{ "status": "open" }
```

Only the requester may reopen a **resolved** or **closed** ticket. Status returns to `open`; `resolved_at` and `closed_at` are cleared. IT receives email (if configured) and in-app notification.

### Resolve (IT resolver)

```json
PATCH /api/v1/ticketing/tickets/{id}
{
  "status": "resolved",
  "resolution_comment": "Restarted the integration worker."
}
```

`resolution_comment` is required. A public comment is added automatically. Requester receives email and in-app notification.

## RBAC

- `ticketing:view` — dashboard and ticket visibility
- `ticketing:tickets:create` — raise tickets and upload attachments
- `ticketing:tickets:manage` — assign, change status/priority, internal comments
- `ticketing:settings:manage` — categories, SLA, IT mailbox, email/webhook toggles (tenant admin)

Baseline roles: **viewer** and **manager** receive create/view; **manager** also receives manage; **tenant_admin** receives settings.

**Raise ticket button** requires both source-module view permission and `ticketing:tickets:create`, plus tenant module `ticketing` enabled.

## Notifications

| Event | Email | In-app |
|-------|-------|--------|
| Ticket created | IT group mailbox (toggle) | All `ticketing:tickets:manage` users |
| Ticket reopened | IT group mailbox (toggle) | All `ticketing:tickets:manage` users |
| Ticket resolved | Requester (toggle) | Requester |
| Ticket assigned | Assignee (toggle) | Assignee |
| SLA reminder | — | All `ticketing:tickets:manage` users (+ optional Teams webhook) |
| SLA escalation | — | All `ticketing:tickets:manage` users (+ optional Teams webhook) |
| Ticket created (Teams) | — | Optional Teams webhook (toggle) |

Mailer: `TOWEROS_NOTIFICATIONS_MAIL_MAILER` (`log` in local dev, SMTP/SES in staging/prod).

Scheduler: ensure `php artisan schedule:run` runs in production so `ticketing:sla-run` executes every 5 minutes.

## Storage

Attachments: `{tenantId}/ticketing/{ticketId}/{uuid}.ext` on the tenant files disk (`toweros.tenant_files`).
