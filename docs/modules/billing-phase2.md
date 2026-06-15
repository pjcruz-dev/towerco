# Billing — Phase 2 (entitlements catalog)

Phase 2 centralizes **what each plan tier includes** and surfaces it in tenant and platform UIs. Still no payment processor.

## Shipped

### Canonical catalog

- **Config:** [`backend/config/billing.php`](../../backend/config/billing.php) — `plan_tiers` with per-module entitlements
- **Service:** `TenantPlanEntitlementsService` — catalog, tier resolution, downgrade warnings
- **E-Approval:** `EApprovalPlanFeaturesService` reads the `e_approval` slice from the catalog (removed duplicate `e_approval.plan_tiers` config)

### Entitlements (current)

| Module | Feature | Starter | Professional | Enterprise |
|--------|---------|---------|--------------|------------|
| E-Approval | Form file fields | No | Up to 10 | Unlimited |
| E-Approval | Submission attachments | No | Yes | Yes |
| PROJECT-ONE | Rollout evidence uploads | No | Yes | Yes |

PROJECT-ONE rollout upload gating in API will follow in a later module pass; the catalog documents intent.

### Platform

- `GET /api/v1/platform/billing/plan-catalog`
- **Billing & plan** sheet: plan comparison table, downgrade confirmation
- `PATCH /platform/tenants/{id}` accepts `confirm_plan_downgrade: true` when downgrading would affect published E-Approval forms with file fields

### Tenant

- `GET /api/v1/admin/billing` returns `plan_catalog`, `entitlements`, `plan_label`
- **Billing** page: full tier comparison + upgrade callout on Starter
- **Form builder:** clearer toast when file fields are blocked (links to Billing)

## Downgrade behavior

When lowering plan tier (e.g. Enterprise → Starter), the API returns **422** with `errors.plan_tier` listing warnings until `confirm_plan_downgrade: true` is sent on retry.

Warnings consider published/draft E-Approval forms that use `file` fields.

## Related

- [billing-phase1.md](./billing-phase1.md) — operational billing, seats, audit
- [billing-phase3.md](./billing-phase3.md) — subscription lifecycle and API access control
- [billing-phase4.md](./billing-phase4.md) — Stripe checkout, portal, webhooks
