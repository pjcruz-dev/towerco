# Rollout gate approvals — Phase E

**Milestone grid alignment with unified timeline**

Phase E makes `timeline_templates` the single source of truth for the 19-row PMO milestone grid. Policy bundle edits (reorder, hide, working-day spans, custom phases) flow through to milestone cycles without maintaining a separate manual milestone matrix.

---

## Problem

Before Phase E, two structures lived in the playbook snapshot:

| Field | Used by |
|-------|---------|
| `timeline_templates` | Timeline tab, rollout instantiation, gate approvals |
| `milestone_cycle_targets` | Milestone cycle grid (`RolloutMilestoneCyclePresenter`) |

Edits in the platform policy editor updated timeline only. Milestones could drift (e.g. v2 site hunting timeline WD 1–7 vs stored milestone row of 6 WD).

---

## Solution

### `RolloutPlaybookMilestoneDeriver`

Derives fine-grained milestone rows from coarse timeline phases using a segment map:

- **BTS / RTB:** timeline phases expand to 19 PMO checkpoints (e.g. `moc_col` → `moc_securing` + `col_social`)
- **Custom phases:** one milestone row each, inserted in timeline order
- **Post–Day-1 pivot:** first timeline phase with `anchor === tssr_approved` (supports custom phases as Day 1)
- **Working days:** segment weights from canonical playbook targets, scaled to each timeline phase span
- **Colocation:** site license uses canonical 1 WD; implementation + billing scale to `delivery_period − 1`

### `RolloutPlaybookMilestoneResolver`

- Derives when `timeline_templates` is present, `policy_bundle_code` is set, or `milestone_derived_from_timeline === true`
- Falls back to stored `milestone_cycle_targets` for legacy snapshots without timeline
- Exposes `postDayOneStartKey()` from timeline (replaces hardcoded `moc_securing` / `site_license`)

### Policy assign cache

`RolloutPolicyBundleService::buildTenantPlaybookSnapshot()` writes derived `milestone_cycle_targets` and sets `milestone_derived_from_timeline: true` on tenant assign.

### Presenter + frontend

- Presenter passes snapshot into `postDayOneStartKey()` and returns `timeline_phase_key` / `is_custom` on rows
- `day_overrides` on timeline phase keys propagate via `timeline_phase_key`
- Frontend milestone timeline uses `anchor === "day_one"` instead of hardcoded `moc_securing`

---

## Files

| Area | Path |
|------|------|
| Deriver | `backend/app/Modules/Rollout/Data/RolloutPlaybookMilestoneDeriver.php` |
| Resolver | `backend/app/Modules/Rollout/Data/RolloutPlaybookMilestoneResolver.php` |
| Presenter | `backend/app/Modules/Rollout/Services/RolloutMilestoneCyclePresenter.php` |
| Policy snapshot | `backend/app/Modules/Platform/Services/RolloutPolicyBundleService.php` |
| Grid utils | `frontend/components/rollout/rollout-milestone-grid-utils.ts` |
| Tests | `backend/tests/Unit/Rollout/RolloutPlaybookMilestoneDeriverTest.php`, `RolloutMilestoneCycleTest.php` |

---

## Verification

```bash
cd backend
php artisan test --filter=RolloutPlaybookMilestoneDeriverTest
php artisan test --filter=RolloutMilestoneCycleTest
```

After assigning a policy bundle:

```bash
php artisan tenants:sync-playbook --domain=<tenant> --policy=towerco-standard --with-rbac
```

Confirm rollout detail → Milestones tab row count and post–Day-1 sum match timeline SLA (115 BTS, 85 RTB, 30 colocation).

---

## Out of scope

- Milestone rows are **not** approval gates (timeline gates remain authoritative)
- Per-milestone `day_overrides` on segment keys still apply when set; timeline-phase overrides preferred for grouped segments
