# Billing — Phase 1 (operational, no Stripe)

Phase 1 provides **manual plan and seat management** for TowerOS operators and a **read-only billing view** for tenant administrators. There is no payment processor integration.

## Shipped

### Central (platform)

| Item | Detail |
|------|--------|
| Tenant fields | `plan_tier`, `subscription_status`, `seat_limit` on `tenants` |
| API | `PATCH /api/v1/platform/tenants/{tenant}` (existing; now audited for billing fields) |
| Audit | `GET /api/v1/platform/tenants/{tenant}/billing-audit` |
| UI | Platform console → tenant row menu → **Billing & plan** |
| CLI | `php artisan tenants:set-plan-tier {tier} --domain=…` |

### Tenant

| Item | Detail |
|------|--------|
| API | `GET /api/v1/admin/billing` (`tenant:manage`) |
| UI | **Administration → Billing** (`/billing`) |
| Seat enforcement | Creating users, CSV import, and reactivate blocked when active users ≥ `seat_limit` |
| E-Approval gating | File fields require `professional` or `enterprise` (unchanged; driven by `plan_tier`) |

### Plan tiers (E-Approval)

| Tier | File fields | Max file fields |
|------|-------------|-----------------|
| starter | No | 0 |
| professional | Yes | 10 |
| enterprise | Yes | Unlimited |

## Local development

- New tenants in `APP_ENV=local` default to **professional** (`TOWEROS_TENANT_DEFAULT_PLAN_TIER`).
- Override: `php artisan tenants:set-plan-tier professional --domain=atc.localhost`

## Not in Phase 1

- Stripe / checkout / invoices
- Self-serve upgrade in tenant UI

## Related

- [billing-phase2.md](./billing-phase2.md) — entitlements catalog, comparison UI, downgrade warnings
- [billing-phase3.md](./billing-phase3.md) — subscription lifecycle (trial, grace, suspension)
- [billing-phase4.md](./billing-phase4.md) — Stripe (optional)
- [billing-phase5.md](./billing-phase5.md) — usage & revenue reporting
