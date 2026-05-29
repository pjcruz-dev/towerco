# Rollout Playbook — Phase 10 (Rollout registry & audit) ✅

Enterprise list operations and audit trail for rollout lifecycle events.

## Delivered

- **Paginated index API** — `search`, `status`, `mno`, `project_type`, `region`, `sort`, `{ data, meta }`
- **PATCH** `/api/v1/project-one/rollouts/{rollout}` — PMO metadata edit
- **POST** `/api/v1/project-one/rollouts/{rollout}/cancel` — cancel with reason
- **GET** `/api/v1/project-one/rollouts/export` — CSV export (respects filters)
- **`RolloutAuditLogger`** — Spatie `activity_log` events for create, Day-1, gates, RFI, candidate select, metadata, cancel
- **Frontend** — registry search/filters/pagination/sort, CSV export, edit metadata sheet, cancel sheet
- **Tests** — `RolloutIndexApiTest`, `RolloutCancelApiTest`, `RolloutAuditLogTest`

## Goals

- Rollout index scales to hundreds of programs (search, filter, pagination, export)
- Support **cancel** and limited **metadata edit** without breaking playbook integrity
- Critical actions write to **audit log**

## Backend

### 1. Rollout index API v2

Replace or extend flat index:

| Query params | `search`, `status`, `mno`, `project_type`, `region`, `page`, `per_page`, `sort` |
| Response | Paginated `{ data, meta }` matching project/approval list pattern |

### 2. Lifecycle endpoints

| Method | Path | Permission | Behavior |
|--------|------|------------|----------|
| PATCH | `/api/v1/project-one/rollouts/{rollout}` | `rollout:manage` | Edit: `search_ring_name`, `region`, `territory`, `endorsement_ref`, owner IDs |
| POST | `/api/v1/project-one/rollouts/{rollout}/cancel` | `rollout:manage` | Set `status=cancelled`; require `cancellation_reason`; block if `completed` |

Rules:

- Cannot change `mno`, `project_type`, `playbook_version`, or SLA after create
- Cancelled rollouts excluded from SLA recalc (already in service)

### 3. Export

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/project-one/rollouts/export` | `rollout:view` |

CSV columns: ref, MNO, type, status, region, TCO site ID, Day-1, target RFI, actual RFI, SLA variance, candidate count.

### 4. Audit log integration

Use existing audit module (or add `RolloutAuditLogger`) for:

| Event | Payload |
|-------|---------|
| `rollout.created` | ref, mno, type |
| `rollout.day_one_set` | date, trigger type |
| `rollout.gate_updated` | phase_key, gate_status |
| `rollout.rfi_recorded` | actual_rfi_date, variance |
| `rollout.candidate_selected` | candidate_number, tco_site_id |
| `rollout.cancelled` | reason |

Permission: `audit:view` for read (future UI).

## Frontend

| Surface | Change |
|---------|--------|
| Rollouts list | Search bar, status/MNO/type filters, pagination, sort |
| Rollouts list | Export CSV button |
| Rollout detail | Cancel rollout action (confirm dialog + reason) |
| Rollout detail | Edit metadata drawer (PMO fields only) |

## Tests

```bat
php artisan test --filter=RolloutIndexApi
php artisan test --filter=RolloutCancelApi
php artisan test --filter=RolloutAuditLog
```

## Verify locally

1. Filter rollouts by `status=permitting`
2. Export CSV — open in Excel
3. Cancel a test rollout — disappears from active SLA KPIs
4. Audit entries visible in tenant audit log (or API if UI pending)

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase9.md](./rollout-playbook-phase9.md)
