# Rollout Playbook — Phase 12 (Map & QMS depth)

Map-first operational visibility and richer approvals for PROJECT-ONE.

**Status: ✅ Complete**

## Goals

- Dashboard and SAQ workflows are **map-first** (candidate pins, selected site)
- Approvals support **attachments** and link to rollouts/projects
- Dashboard KPIs reflect **live rollout metrics** (not static demo only)

## Backend

### 1. Map data APIs

| Deliverable | Implementation |
|-------------|----------------|
| Dashboard `map_pins[]` | `ProjectOneMapDataService` — sites, rollout linked sites, candidates with coords |
| Rollout detail site coords | `RolloutProgramPresenter` site block includes `latitude` / `longitude` |
| GeoJSON endpoint | `GET /api/v1/project-one/rollouts/{rollout}/map` → `RolloutMapService` FeatureCollection |

### 2. Approvals enhancements

| Change | Detail |
|--------|--------|
| Migration | `rollout_program_id` nullable FK, `attachment_file_ids` JSON |
| File context | `approval_attachment` in `RolloutFileContext` (`project_one:manage`) |
| Store API | Accept rollout link + `attachment_file_ids[]`; index enriches attachment URLs |

### 3. Dashboard KPI refresh

`ProjectOneDashboardController` merges from `RolloutDashboardMetricsService`:

- Active rollouts, awaiting Day-1, SLA at-risk (existing)
- **Pending gates** — timeline phases with `gate_status = pending`
- **Open SAQ programs** — `status = saq` with fewer than 3 candidates

## Frontend

| Surface | Change |
|---------|--------|
| `OperationalMap` | Shared MapLibre component (`components/maps/operational-map.tsx`) |
| Dashboard `MapPanel` | MapLibre pins; click rollout pin → detail |
| SAQ tab | Split view: candidate table + map; draggable pin on create/edit |
| Approvals create | Project + rollout dropdowns; PDF/image attachments via rollout file upload |
| Approvals list | Rollout ref column |

## Tests

```bat
php artisan test --filter=ProjectOneDashboardRolloutKpis
php artisan test --filter=ProjectApprovalRolloutLink
```

## Verify locally

1. Run tenant migrations: `php artisan tenants:migrate`
2. Dashboard map shows Alliance demo sites + rollout candidates (`map_pins`)
3. Create approval linked to rollout with PDF attachment
4. KPI strip shows rollout at-risk count > 0 when SLA breached; pending gates / open SAQ when seeded

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase11.md](./rollout-playbook-phase11.md) · Phase 7 (files)
