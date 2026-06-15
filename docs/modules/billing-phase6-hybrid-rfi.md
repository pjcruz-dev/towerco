# Billing Phase 6 — Hybrid RFI metering

## Policy (locked)

| Rule | Behavior |
|------|----------|
| Primary metric | **RFI completions** — 1 unit per rollout when `actual_rfi_date` is recorded |
| Inventory towers | Do **not** count without RFI |
| Enforcement | **Hard block** new RFIs only; tower/site CRUD is never blocked |
| Go-live | Only RFIs **on or after** `billing_meter_starts_at` count |
| Grandfather | `billing_overrides.grandfather_rfi_units` adds free capacity for sales |
| Paid seats | Active users with **viewer-only** role are free |
| Annual pricing | Platform catalog default + per-tenant override; interval on `billing_interval` |

## Central schema

- `tenants.billing_meter_starts_at` — nullable; when null, metering is off
- `tenants.billing_interval` — `monthly` \| `annual`
- `platform_billing_settings` — singleton catalog (prices, annual %, included limits)
- `tenant_billing_rfi_completions` — ledger (`tenant_id`, `rollout_id`, `rfi_at`)

## APIs

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/platform/billing/plan-catalog` | `platform.billing.view` |
| PATCH | `/api/v1/platform/billing/catalog` | `platform.billing.manage` |
| PATCH | `/api/v1/platform/tenants/{id}` | `platform.tenants.manage` — meter start, interval, overrides |
| GET | `/api/v1/admin/billing` | tenant admin — includes `rfi_units` snapshot |

## Platform UI

- **Billing** → Plan catalog & pricing panel (annual discount, tier prices, included RFI/seats)
- **Tenants** → Billing & plan sheet → go-live date, grandfather units, annual override

## Config defaults

See `backend/config/billing.php` — `included` and `pricing` per tier, `annual.default_discount_percent`.

## Currency & overage

- Canonical list prices are stored in **USD** (`pricing.*_usd` fields).
- Superadmin display currency is converted via `billing.exchange_rates` (e.g. `$99` → `₱5,544` at `56` PHP/USD).
- **+1 paid seat** monthly add-on = `paid_seat_overage_usd` (per tier, converted for display).
- **+1 RFI unit** monthly add-on = `rfi_overage_usd` (per tier, converted for display).
- Tenant billing snapshot includes `billing_estimate` (and legacy `overage` alias):
  - `monthly_base` — fixed tier list price (converted to platform currency)
  - `seat_addons_monthly` / `rfi_addons_monthly` — committed capacity above catalog bundle
  - `estimated_monthly_total` — base + add-ons (auto-updates when limits or currency change)
  - `annual_base_prepaid` — discounted annual plan base; add-ons estimated monthly
- Platform tenant billing sheet shows a live estimate preview while editing seat / RFI limits.

## Tests

- `TenantRfiMeterServiceTest` — meter off, limit block, pre-go-live exclusion, grandfather
- `RolloutGateAndRfiApiTest::test_post_rfi_blocked_when_billable_limit_reached`
- `TenantSeatLimitServiceTest` — viewer seats excluded from paid count
