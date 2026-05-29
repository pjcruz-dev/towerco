# Rollout Playbook — Phase 9 (Milestone cycle tracker) ✅

Surface the playbook’s **19 `milestone_cycle_targets`** alongside the 9 timeline phases.

## Delivered

- **`RolloutMilestoneCyclePresenter`** — cumulative working-day targets from endorsement (pre–Day-1) and Day-1 (post–`moc_securing`), region-aware calendar, `day_overrides` support
- **Rollout detail API** — `milestone_cycles[]` + `milestone_cycles_summary` embedded in `RolloutProgramPresenter::detail()`
- **Frontend** — **Milestones** tab on rollout detail with summary cards + status table
- **Tests** — `RolloutMilestoneCycleTest` (19 rows, v2 site hunting 6 WD, batch empty, presenter embed)

## Goals

- PMO sees granular SLA checkpoints (e.g. Locational Clearance, Building Permit, Energization)
- Progress derived from rollout dates + working-day math (Phase 8 calendar)
- Read-only from playbook snapshot; tenant may only override via existing `day_overrides`

## Backend

### 1. Milestone projection service

`RolloutMilestoneCyclePresenter`:

- Input: `RolloutProgram` + playbook snapshot `milestone_cycle_targets`
- Output: list of `{ phase_key, label, target_working_days, target_date, status, variance_wd }`
- `target_date` = anchor date + `target_working_days` (endorsement or Day-1 per playbook key)
- `status`: `pending` | `active` | `at_risk` | `overdue` | `completed` (infer from rollout status + dates)

### 2. API

Extend rollout detail payload **or** dedicated endpoint:

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/project-one/rollouts/{rollout}/milestone-cycles` | `project_one:rollout:view` |

Prefer embedding in `RolloutProgramPresenter::detail()` as `milestone_cycles[]` to avoid extra round-trip.

## Frontend

| Surface | Change |
|---------|--------|
| Rollout detail | New tab **Milestones** (or sub-table under Timeline) |
| Display | Table: milestone, target WD, target date, status badge, variance |
| BTS vs RTB vs Colo | Per-type milestone list from snapshot (`milestone_cycle_targets.bts` / `.rtb` / `.colocation`) |

Optional: compact **progress bar** (% milestones on track) on rollout list + dashboard recent panel.

## Data rules

- Do **not** allow add/remove milestones (playbook-locked per Phase 1 decision)
- `milestone_cycle_targets` is keyed by project type: BTS (19 rows), RTB (19 rows scaled to 85 WD post–Day-1), Colocation (3 rows)
- `day_overrides` may adjust working day counts where keys align (per template tab on playbook settings)
- Batch container rollouts: empty milestone list

## Tests

```bat
php artisan test --filter=RolloutMilestoneCycle
```

Assert BTS demo rollout returns 19 rows with computed dates after Day-1 set.

## Verify locally

1. Open Alliance BTS rollout with Day-1 set
2. Milestones tab shows 19 rows; `site_hunting` target aligns with playbook (6–7 WD in v2)
3. Add PH holiday → milestone target dates shift (via Phase 8 calendar)

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase8.md](./rollout-playbook-phase8.md)
