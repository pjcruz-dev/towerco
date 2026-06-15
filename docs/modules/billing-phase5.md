# Billing — Phase 5 (usage & revenue reporting)

Phase 5 adds **enterprise custom limits**, **tenant usage reporting**, and a **platform revenue dashboard**. Payment processing remains optional (Phase 4 / `TOWEROS_STRIPE_ENABLED`).

## Shipped

### Enterprise custom limits

- **Column:** `tenants.billing_overrides` (JSON, nullable)
- **Validator:** `TenantBillingOverridesValidator` — `seat_limit`, `modules.e_approval`, `modules.project_one`
- **Entitlements:** `TenantPlanEntitlementsService::forTenant()` merges overrides into the catalog
- **Seats:** `effectiveSeatLimit()` — override seat cap wins over `seat_limit` column
- **Platform:** Billing sheet → **Enterprise custom limits** when plan tier is Enterprise

### Tenant usage report

- `GET /api/v1/admin/billing/usage` (`tenant:manage`)
- **Service:** `TenantUsageReportService` — last 30 days:
  - Active/total users, seat usage
  - E-Approval forms & submissions
  - PROJECT-ONE rollouts
- **UI:** `/billing` → Usage section

### Platform revenue dashboard

- `GET /api/v1/platform/billing/insights` (platform admin)
- **Service:** `PlatformBillingInsightsService`
  - Indicative MRR from config list prices (active/trial tenants)
  - Revenue by tier, plan/subscription breakdown
  - Stripe linked subscription count
  - Seat totals, custom-entitlement tenants
  - Recent billing audit activity
  - Top tenant billing rows
- **UI:** `/platform/billing` — **Billing & revenue** (link from platform home)

## Config (indicative pricing)

```env
TOWEROS_BILLING_CURRENCY=USD
TOWEROS_LIST_PRICE_STARTER_USD=0
TOWEROS_LIST_PRICE_PROFESSIONAL_USD=99
TOWEROS_LIST_PRICE_ENTERPRISE_USD=299
```

These values power the **revenue dashboard only** — not invoicing. Stripe or manual contracts remain authoritative.

## Operations

```bat
docker compose --env-file .env.docker exec api php artisan migrate
```

Open **Platform → Billing & revenue** or tenant **Administration → Billing** for usage.

## Related

- [billing-phase4.md](./billing-phase4.md) — Stripe
- [billing-phase3.md](./billing-phase3.md) — lifecycle
- [billing-phase2.md](./billing-phase2.md) — catalog
- [billing-phase1.md](./billing-phase1.md) — operational billing
