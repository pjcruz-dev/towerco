# Rollout Playbook — Phase 4

Platform operator visibility and tenant holiday UX after Phase 3.

## Features

### 1. Dedicated public holidays page

| Route | Purpose |
|-------|---------|
| `/project-one/public-holidays` | Full holiday calendar CRUD |

Rollout playbook page links here with a summary card. Sidebar: **PROJECT-ONE → Holidays**.

### 2. Platform tenant directory — playbook status

`GET /api/v1/platform/tenants` now includes:

| Field | Description |
|-------|-------------|
| `assigned_playbook_version` | e.g. `1.0.0` |
| `playbook_upgrade_available` | `true` when a newer published version exists |
| `slug`, `brand_domain`, `environment` | Tenant metadata |

Tenant directory UI shows assigned version, upgrade badge, and pre-selects the assign dropdown.

### 3. Create tenant — metadata fields

Platform **Create tenant** form now captures:

- `slug` — URL key (e.g. `atc`)
- `brand_domain` — customer domain (e.g. `alliancetowers.com`)
- `environment` — local / test / staging / production
- `tco_sequence_prefix` — TCO Site ID letter (e.g. `A`)

See [`tenant-domain-slugs.md`](./infrastructure/tenant-domain-slugs.md).

### 4. HTTP feature tests

```bat
cd backend
php artisan test --filter=TenantPublicHolidayApi
```

Exercises list, create, delete, and PH seed endpoints with in-memory SQLite tenant context.

## Verify locally

1. **Holidays page** — `http://alliance.localhost/project-one/public-holidays`
2. **Platform directory** — assigned playbook + upgrade badge on `/platform`
3. **Create tenant** — `/platform/tenants/create` with slug/metadata fields

See also: [rollout-playbook-phase3.md](./rollout-playbook-phase3.md)
