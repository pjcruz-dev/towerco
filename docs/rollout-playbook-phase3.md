# Rollout Playbook — Phase 3

Operational polish after Phase 2: platform playbook catalog UI, tenant holiday calendar management, and service integration tests.

## Features

### 1. Platform playbook catalog

Dedicated superadmin page for published rollout playbook versions.

| Route | Purpose |
|-------|---------|
| `/platform/playbooks` | Version catalog, publish v2, version policy notes |

Tenant assignment remains on the **tenant directory** (`/platform`) per tenant row.

### 2. Tenant public holiday calendar (CRUD)

Tenants manage working-day exclusions used in SLA math.

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/project-one/public-holidays?year=2026` | `project_one:view` |
| POST | `/api/v1/project-one/public-holidays` | `project_one:playbook:configure` |
| PATCH | `/api/v1/project-one/public-holidays/{holiday}` | `project_one:playbook:configure` |
| DELETE | `/api/v1/project-one/public-holidays/{holiday}` | `project_one:playbook:configure` |
| POST | `/api/v1/project-one/public-holidays/seed-philippines` | `project_one:playbook:configure` |

Frontend: **Public holiday calendar** panel on `/project-one/public-holidays` (linked from rollout playbook page).

### 3. Integration tests

```bat
cd backend
php artisan test --filter=TenantPublicHolidayService
```

Uses an in-memory SQLite tenant connection to exercise CRUD + PH seed without MySQL.

## Verify locally

1. **Platform** — `http://localhost:3001/platform/playbooks` — catalog + publish v2
2. **Tenant** — `http://alliance.localhost:3001/project-one/rollout-playbook` — holiday table, seed/add/edit/delete as `admin@alliance.localhost`
3. Re-login if `project_one:playbook:configure` was added after your session started

See also: [rollout-playbook-phase2.md](./rollout-playbook-phase2.md)
