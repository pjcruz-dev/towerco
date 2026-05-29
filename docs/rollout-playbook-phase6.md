# Rollout Playbook — Phase 6 (Operational closure)

Gate workflow, RFI completion, and holiday-driven SLA recalculation.

## Features

### 1. Timeline gate status

Update control gate outcomes on phases that have a playbook `gate_label`.

| Method | Path | Permission |
|--------|------|------------|
| PATCH | `/api/v1/project-one/rollout-phases/{phase}/gate` | `project_one:rollout:manage` |

Body: `{ "gate_status": "pending|passed|failed|waived" }`

- **passed** sets `actual_end_date` to today
- Returns refreshed rollout detail

Frontend: gate status dropdown on rollout timeline tab (phases with a control gate).

### 2. Record RFI (program close)

| Method | Path | Permission |
|--------|------|------------|
| POST | `/api/v1/project-one/rollouts/{rollout}/rfi-recorded` | `project_one:rollout:manage` |

Body: `{ "actual_rfi_date": "2026-05-15" }`

- Requires Day-1 (`tssr_approved_date`) to be set
- Sets `status=completed`
- Computes `sla_variance_working_days` = elapsed WD from Day-1 minus SLA budget (negative = early)

Frontend: **Record RFI** panel on rollout timeline tab.

### 3. SLA recalc on holiday changes

When holidays are created, updated, deleted, or PH-seeded, active rollouts (no actual RFI, not batch/completed/cancelled) have:

- `target_rfi_working_date` recomputed
- Timeline phase target dates recomputed

Uses current tenant holiday calendar via `WorkingDaysCalendar` singleton.

## Tests

```bat
cd backend
php -d memory_limit=1G artisan test --filter=Rollout
```

Covers SLA recalculation, RFI variance, gate/RFI HTTP endpoints, and holiday CRUD integration.

## Verify locally

1. Open a demo rollout with Day-1 set: `/project-one/rollouts/{id}` → Timeline
2. Change a gate status on a phase with a control gate label
3. Record RFI and confirm variance + `completed` status
4. Add a PH holiday on `/project-one/public-holidays` → refresh rollout → target dates shift

See also: [rollout-playbook-phase5.md](./rollout-playbook-phase5.md) · [project-one-roadmap.md](./project-one-roadmap.md) (Phases 7–14 pending)
