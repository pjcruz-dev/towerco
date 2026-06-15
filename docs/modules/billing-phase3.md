# Billing — Phase 3 (subscription lifecycle)

Phase 3 adds **subscription status lifecycle** (trial, active, past due, canceled) with scheduled transitions and tenant API access control. Still **no payment processor** — Stripe remains off via `TOWEROS_STRIPE_ENABLED=false`.

## Shipped

### Central schema

Migration `2026_06_06_100000_add_tenant_subscription_lifecycle_fields.php` on `tenants`:

- `trial_ends_at`
- `past_due_grace_ends_at`
- `canceled_at`
- `subscription_locked_at`

### Config

[`backend/config/billing.php`](../../backend/config/billing.php):

- `subscription` — `trial_days`, `past_due_grace_days`, `on_trial_expire`, `default_status`
- `subscription_api_exempt_prefixes` — routes still available when suspended
- `stripe.enabled` — from `TOWEROS_STRIPE_ENABLED` (default `false`)

### Services

- `TenantSubscriptionLifecycleService` — snapshot, platform updates, provisioning defaults, `processScheduledTransitions()`
- `TenantBillingReadService` — includes `subscription` snapshot and `payments.stripe_enabled`
- `PlatformTenantSettingsService` — lifecycle fields on PATCH; audit covers date columns

### Access control

- Middleware `tenant.subscription` on tenant API routes (after tenancy init)
- `TenantSubscriptionSuspendedException` → HTTP **402** with code `subscription_suspended`
- Exempt: auth, E-Approval health, `GET /admin/billing`

### Scheduler

- Command: `php artisan toweros:subscriptions:process`
- Scheduled **hourly** in `bootstrap/app.php`
- Expires trials (`on_trial_expire`: `active` or `past_due`)
- Sets `subscription_locked_at` when past-due grace ends

### Platform

- `PATCH /platform/tenants/{id}` accepts `trial_ends_at`, `past_due_grace_ends_at`
- Billing sheet: trial / grace date inputs, Stripe-off note
- Plan catalog includes subscription defaults and `payments.stripe_enabled`

### Tenant

- Billing page shows access mode, trial/grace countdown, suspension banner

### Provisioning

- New tenants get `applyProvisioningDefaults()` + save (respects `TOWEROS_SUBSCRIPTION_DEFAULT_STATUS`)

## Environment

| Variable | Default | Purpose |
|----------|---------|---------|
| `TOWEROS_SUBSCRIPTION_DEFAULT_STATUS` | `active` | New tenant status (`trial` for timed trial) |
| `TOWEROS_SUBSCRIPTION_TRIAL_DAYS` | `14` | Default trial length when status set to trial |
| `TOWEROS_SUBSCRIPTION_PAST_DUE_GRACE_DAYS` | `7` | Grace after past due before lock |
| `TOWEROS_SUBSCRIPTION_ON_TRIAL_EXPIRE` | `active` | `active` or `past_due` when trial ends |
| `TOWEROS_STRIPE_ENABLED` | `false` | Stripe off until Phase 4 keys are configured |

## Operations

```bat
docker compose --env-file .env.docker exec api php artisan migrate
docker compose --env-file .env.docker exec api php artisan toweros:subscriptions:process
```

Set a tenant to past due with a short grace in Platform **Billing & plan**, then run the command after grace passes to verify lock.

## Related

- [billing-phase1.md](./billing-phase1.md) — plan tier, seats, audit
- [billing-phase2.md](./billing-phase2.md) — entitlements catalog
- [billing-phase4.md](./billing-phase4.md) — Stripe checkout, portal, webhooks
