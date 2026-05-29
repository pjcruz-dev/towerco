# Rollout Playbook — Phase 1 (PROJECT-ONE)

Aligned to `Rules/TowerCo_Rollout_Playbook_v1.docx`.

## Key decisions (locked)

| Topic | Decision |
|-------|----------|
| SLA counting | **Working days only** (Mon–Fri; PH holidays hook reserved) |
| Unit of work | **1 endorsement → 1 rollout → 1 site** (batch = schema-only `parent_rollout_id`) |
| SAQ | ≥3 candidates, rejected kept for audit + **daily hunting log** |
| CME | Daily report, **internal CME PM only** |
| Profitability | Manual entry; **tiered RBAC** (discipline vs full) |
| Playbook | **Superadmin assigns version** per tenant; v2 shows as upgrade banner |
| TCO Site ID | Global format `{Region}-{MNO}{YY}-{TenantSeq}{###}` e.g. `NS-GLO26-A042` |

## SLA working days by project type

| Type | Working days (v1) | Working days (v2) | Day-1 trigger |
|------|-------------------|-------------------|----------------|
| BTS | 120 | **115** | TSSR approved |
| RTB | 85 | 85 | DOA execution + 15 WD |
| Colocation | 30 | 30 | Site license executed |

Each project type has its own **timeline template** and **milestone cycle list** in the playbook snapshot (`timeline_templates`, `milestone_cycle_targets`). RTB post–Day-1 phases are scaled to the 85 WD SLA budget; colocation uses a short 2-phase timeline and 3 milestone checkpoints.

## Tenant admin customization

May override **working day end counts per phase** only (`day_overrides` JSON). Cannot add/remove phases. Overrides are keyed by template:

```json
{
  "bts": { "site_hunting": { "working_day_end": 7 } },
  "rtb": { "construction": { "working_day_end": 85 } },
  "colocation": { "implementation": { "working_day_end": 28 } }
}
```

Saved overrides apply to **new rollouts** created after save (existing rollout timelines are unchanged).

## API (tenant)

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/project-one/rollout-playbook` | `project_one:view` |
| PATCH | `/api/v1/project-one/rollout-playbook` | `project_one:playbook:configure` |
| GET | `/api/v1/project-one/rollouts` | `project_one:rollout:view` |
| POST | `/api/v1/project-one/rollouts` | `project_one:rollout:manage` |
| GET | `/api/v1/project-one/rollouts/{rollout}` | `project_one:rollout:view` |
| POST | `/api/v1/project-one/rollouts/{rollout}/tssr-approved` | `project_one:rollout:manage` |
| POST | `/api/v1/project-one/rollouts/{rollout}/delivery-period-start` | `project_one:rollout:manage` |
| POST | `/api/v1/project-one/rollouts/{rollout}/candidates` | `project_one:saq:manage` |
| PATCH | `/api/v1/project-one/candidates/{candidate}` | `project_one:saq:manage` |
| POST | `/api/v1/project-one/candidates/{candidate}/reject` | `project_one:saq:manage` |
| POST | `/api/v1/project-one/candidates/{candidate}/select` | `project_one:saq:manage` |
| POST | `/api/v1/project-one/rollouts/{rollout}/hunting-logs` | `project_one:saq:manage` |
| POST | `/api/v1/project-one/rollouts/{rollout}/cme-reports` | `project_one:cme:manage` |
| GET | `/api/v1/project-one/rollouts/{rollout}/profitability` | `project_one:rollout:view` + finance tiers |
| PATCH | `/api/v1/project-one/rollouts/{rollout}/profitability` | `project_one:finance:edit` |

`POST .../delivery-period-start` accepts trigger-specific fields based on rollout `project_type`:

- **BTS:** `tssr_approved_date`
- **RTB:** `doa_execution_date` (delivery start = DOA + 15 WD)
- **Colocation:** `site_license_executed_date`

## API (platform)

| Method | Path |
|--------|------|
| GET | `/api/v1/platform/rollout-playbooks` |
| POST | `/api/v1/platform/tenants/{tenant}/playbook` |

## Commands

```bat
cd backend
php artisan db:seed
php artisan tenants:migrate --force
php artisan tenants:sync-playbook --domain=alliance.localhost --with-rbac
php artisan tenants:seed-demo --domain=alliance.localhost
```

Do **not** run `TenantRbacBaselineService` from central `tinker` without `$tenant->run()` — tenant models use the `tenant` connection.

## Playbook versioning

1. Superadmin publishes `1.0.0`, `2.0.0`, … on platform.
2. Each tenant has **one assigned version** (`tenant_playbook_bindings`).
3. Tenant DB stores snapshot in `tenant_rollout_playbook_config`.
4. When platform publishes v2, tenant sees `upgrade_available: true` but **live rollouts stay on assigned version**.
5. Superadmin reassigns version → only **new rollouts** adopt v2 by default (`new_rollouts_only` policy).

## Demo logins (Alliance)

| Email | Role |
|-------|------|
| admin@alliance.localhost | tenant_admin |
| manager@alliance.localhost | manager |
| project.lead@alliance.localhost | manager |
| finance@alliance.localhost | finance |
| ops.viewer@alliance.localhost | viewer |

Password: `password`

See also: [tenant-domain-slugs.md](./infrastructure/tenant-domain-slugs.md)
