# Rollout Playbook — Phase 2

Extends Phase 1 with batch rollouts, PH holiday-aware SLA math, playbook v2 publishing, and gate label backfill.

## Features

### 1. Batch rollouts

One MNO batch endorsement creates:

- **Parent** rollout (`status=batch`) — container only
- **Child** rollouts — full SAQ/CME/profitability programs (`parent_rollout_id` set)

| Method | Path |
|--------|------|
| POST | `/api/v1/project-one/rollout-batches` |

Frontend: `/project-one/rollouts/batch/new`

### 2. Philippines public holidays

Working-day SLA math excludes seeded PH national holidays (Mon–Fri holidays).

| Item | Detail |
|------|--------|
| Table | `tenant_public_holidays` |
| Seed | `php artisan tenants:seed-holidays --domain=alliance.localhost --year=2026` |
| Playbook UI | Shows `public_holidays_count` on `/project-one/rollout-playbook` |

### 3. Playbook v2 publish

| Method | Path |
|--------|------|
| POST | `/api/v1/platform/rollout-playbooks/publish` `{ "version": "2.0.0" }` |

CLI:

```bat
php artisan rollout-playbook:publish-v2
```

**v2 changes vs v1:**

- BTS SLA: **115** working days (was 120)
- RTB timeline + milestones: post–Day-1 phases scaled to **85 WD** (was incorrectly using BTS 115/120 end)
- Site hunting window tightened (WD 7 vs 8)
- Tenants see `upgrade_available: true` until superadmin reassigns; live rollouts stay frozen on assigned version

Platform console: **Publish playbook v2** on `/platform/playbooks` (sidebar: Rollout playbooks). Tenant assignment stays on the tenant directory.

### 4. Gate label backfill

```bat
php artisan tenants:backfill-rollout-gates --domain=alliance.localhost
```

Copies playbook template `gate` text onto existing timeline phases.

## Commands summary

```bat
cd backend
php artisan tenants:migrate --force
php artisan tenants:seed-holidays --domain=alliance.localhost --year=2026
php artisan tenants:backfill-rollout-gates --domain=alliance.localhost
php artisan rollout-playbook:publish-v2
php artisan tenants:seed-demo --domain=alliance.localhost
```

## Tests

```bat
cd backend
php artisan test --filter=WorkingDaysCalendar
php artisan test --filter=RolloutPlaybookDefinitionRegistry
```

## Demo data (Alliance)

After re-seed:

- Single rollout: `RP-2026-GLO-DEMO`
- Batch: `BATCH-2026-GLO-DEMO` with 2 Smart child rollouts
- PH holidays for current year
- Gate labels backfilled on demo rollout

See also: [rollout-playbook-phase1.md](./rollout-playbook-phase1.md)
