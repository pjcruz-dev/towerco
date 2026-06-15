# Billing — Phase 4 (Stripe payments)

Phase 4 adds **optional Stripe** subscription checkout and Customer Portal, with webhooks that sync `plan_tier` and subscription lifecycle on central `tenants`. Default remains **off** until you explicitly enable it.

## ON/OFF switch

| Control | Effect |
|---------|--------|
| `TOWEROS_STRIPE_ENABLED=false` (default) | No checkout, no portal, webhooks return 404 — manual billing (Phases 1–3) |
| `TOWEROS_STRIPE_ENABLED=true` + API keys + price IDs | Self-serve upgrade on tenant `/billing` when `operational` |

There is no platform UI toggle for secrets; operators use environment variables (or your deployment secret store).

## Shipped

### Backend

- **Package:** `stripe/stripe-php`
- **Schema:** `tenants.stripe_customer_id`, `stripe_subscription_id`, `stripe_price_id`; `stripe_webhook_events` (idempotency)
- **Config:** [`backend/config/billing.php`](../../backend/config/billing.php) — keys, price IDs, self-serve tiers
- **Services:** `StripeBillingConfig`, `StripeBillingService`, `StripeWebhookProcessor`
- **Job:** `ProcessStripeWebhookJob` on `toweros-webhooks` queue
- **Webhook:** `POST /api/v1/webhooks/stripe` (signature verified)
- **Tenant APIs** (`tenant:manage`):
  - `POST /api/v1/admin/billing/checkout-session` — `{ plan_tier }` → Stripe Checkout URL
  - `POST /api/v1/admin/billing/portal-session` — Customer Portal URL
- **Snapshot:** `GET /admin/billing` → `payments` block (`operational`, `upgrade_options`, etc.)

### Webhook events handled

- `checkout.session.completed`
- `customer.subscription.created` / `updated` / `deleted`
- `invoice.payment_failed`

### Frontend

- **Tenant `/billing`:** Upgrade buttons + **Manage billing** when Stripe is operational
- **Platform billing sheet:** Stripe status line from plan catalog

## Environment

```env
TOWEROS_STRIPE_ENABLED=false
STRIPE_SECRET=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_STARTER=price_...
STRIPE_PRICE_PROFESSIONAL=price_...
# STRIPE_PRICE_ENTERPRISE=   # optional; enterprise usually sales-assisted
```

Tenant checkout return URLs use `TOWEROS_TENANT_APP_URL` (or `FRONTEND_APP_URL`) + `/billing?checkout=success|canceled`.

## Stripe Dashboard setup

1. Create **Products / Prices** for Starter and Professional (recurring).
2. Copy price IDs into `STRIPE_PRICE_*` env vars.
3. Add webhook endpoint: `https://<api-host>/api/v1/webhooks/stripe`
4. Subscribe to: `checkout.session.completed`, `customer.subscription.*`, `invoice.payment_failed`
5. Run queue worker for `toweros-webhooks` (or `sync` in local dev).

Local testing: `stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe`

## Operations

```bat
docker compose --env-file .env.docker exec api php artisan migrate
docker compose --env-file .env.docker exec api php artisan queue:work --queue=toweros-webhooks
```

Enable Stripe only when keys and webhook secret are set:

```env
TOWEROS_STRIPE_ENABLED=true
```

## Related

- [billing-phase3.md](./billing-phase3.md) — lifecycle and access control
- [billing-phase2.md](./billing-phase2.md) — entitlements catalog
- [billing-phase1.md](./billing-phase1.md) — manual plan/seats

**Phase 5:** [billing-phase5.md](./billing-phase5.md) — usage reporting, revenue dashboard, enterprise overrides.
