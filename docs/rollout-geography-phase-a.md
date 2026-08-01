# Rollout geography — Phases A–E

Tenant-managed **Region** (PSA) and **Territory** (telecom cluster), plus site geocoding.

## Model

| Field | Role |
|-------|------|
| **Region** | PSA admin code (`01`…`17`) — reporting / labeling |
| **Territory** | Ops scope (`LUZ`, `VIS`, `MIN`, `NCR`, `SLZ`, `NLZ`) — **SLA holidays + TCO site IDs** |

Ops scope resolution: **Territory → Region (legacy fallback)**.

## Delivered (Phase A) ✅

- Tenant table `rollout_geography_lookups`
- Seed catalog + CRUD API + Settings UI (**Geography lookups**)

## Delivered (Phase B) ✅

- Dropdowns on New rollout, Edit metadata, Bulk edit
- Region `13` suggests Territory `NCR`

## Delivered (Phase C) ✅

- `RolloutOpsGeography` — territory-first scope for calendars / TCO
- SLA holiday matching uses ops scope (territory preferred)
- Public holidays UI: territory scope select
- TCO IDs from territory codes; geography delete blocked when in use

## Delivered (Phase D) ✅

- `POST /project-one/geocode/reverse` — lat/long → address
- UI **Fill from coordinates** (confirm overwrite)

## Delivered (Phase E) ✅

- `POST /project-one/geocode/forward` — address → lat/long
- UI **Locate from address** (confirm overwrite of coordinates)
- **Map pin**: click map to drop pin, drag to fine-tune (New rollout + Edit metadata)
- Placeholder `MAPBOX_ACCESS_TOKEN=pk....` is ignored; falls back to Nominatim until a real token is set

### Configure geocoding

```env
GEOCODING_DRIVER=auto
MAPBOX_ACCESS_TOKEN=pk....
GEOCODING_COUNTRY=ph
```

Replace `pk....` with a real Mapbox public token for production. Until then, Nominatim is used.

## Apply on a tenant

```bat
cd backend
php artisan tenants:migrate
```

Then **Geography lookups** → **Seed defaults**. Prefer setting **Territory** on new rollouts.

## Tests

```bat
php artisan test --filter=RolloutGeographyLookupService
php artisan test --filter=RolloutOpsGeography
php artisan test --filter=ReverseGeocodeService
```

## Phase status

| Phase | Scope | Status |
|-------|--------|--------|
| **A** | Lookups + seed + CRUD | ✅ |
| **B** | Wire dropdowns | ✅ |
| **C** | Holidays + TCO use Territory | ✅ |
| **D** | Reverse geocode (lat/long → address) | ✅ |
| **E** | Forward geocode + map pin | ✅ |
