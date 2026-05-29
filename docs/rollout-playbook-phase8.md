# Rollout Playbook — Phase 8 (SLA precision & ops commands)

**Status: implemented** — region-aware holiday SLA math and operator recalc/backfill commands.

Region-aware holiday exclusion and operator tooling for SLA maintenance.

## Goals

- SLA math uses **national + rollout-region** holidays (not all regional rows globally)
- Operators can **bulk-recalculate** SLAs and **backfill gates** on legacy tenants

## Backend

### 1. Region-aware `WorkingDaysCalendar`

| Change | Detail |
|--------|--------|
| `TenantWorkingDaysCalendarFactory` | Accept optional `?string $rolloutRegion`; load holidays where `region IS NULL OR region = $rolloutRegion` |
| `RolloutSlaRecalculationService` | Pass `$program->region` when building calendar per program |
| `RolloutProgramService` / presenter | SLA remaining uses region-scoped calendar |

Document rule: **national holidays (`region = null`) always apply**; regional rows apply only when rollout `region` matches.

### 2. Artisan commands

```bat
php artisan tenants:recalculate-rollout-slas {--tenant=} {--domain=}
php artisan tenants:backfill-rollout-gates {--tenant=} {--domain=}
```

| Command | Behavior |
|---------|----------|
| `recalculate-rollout-slas` | Runs `RolloutSlaRecalculationService::recalculateActivePrograms()` per tenant |
| `backfill-rollout-gates` | Runs existing `RolloutPhaseGateLabelBackfillService` for tenants missing `gate_label` |

Register in `TenantRolloutServiceProvider` or console routes; support `--domain=alliance.localhost` for dev.

### 3. Holiday seed clarity

- PH seed defaults to **national** (`region = null`)
- UI copy: regional holidays are optional overlays

## Frontend

| Surface | Change |
|---------|--------|
| Public holidays page | Badge: National vs Regional; filter by region |
| Rollout playbook page | Note when regional holidays affect SLA |
| Rollout detail | Show which holiday set applies (region label) |

## Tests

```bat
php artisan test --filter=RegionAwareWorkingDays
php artisan test --filter=RecalculateRolloutSlasCommand
```

Scenarios:

- National holiday shifts all rollouts
- Regional holiday shifts NCR rollout only, not Visayas rollout
- Recalc command updates `target_rfi_working_date` after holiday add

## Verify locally

1. Seed NCR-only holiday on `2026-06-12`
2. Compare Alliance rollout in `ncr` vs `visayas` — dates differ correctly
3. Run `tenants:recalculate-rollout-slas --domain=alliance.localhost`

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase7.md](./rollout-playbook-phase7.md)
