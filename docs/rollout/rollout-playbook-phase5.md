# Rollout Playbook — Phase 5 (Tenant provisioning)

Automatic rollout bootstrap when creating a tenant from the platform console.

## What runs automatically

When **Create tenant** completes (migrations always on):

| Step | Detail |
|------|--------|
| Tenant DB migrate | Stancl + `tenants:migrate` |
| Playbook assign | **Latest published** by default (or explicit version from form) |
| Playbook snapshot sync | `tenant_rollout_playbook_config` |
| PH holidays | Current year + next year (configurable) |
| RBAC baseline | All rollout permissions |
| Initial admin | `admin@{domain}` |

## Configuration

```env
TOWEROS_TENANT_DEFAULT_PLAYBOOK=latest   # latest | v1
TOWEROS_TENANT_AUTO_SEED_HOLIDAYS=true
TOWEROS_TENANT_SEED_NEXT_HOLIDAY_YEAR=true
TOWEROS_DEMO_SEED=false                # demo dataset still opt-in
```

| Key | Default | Purpose |
|-----|---------|---------|
| `TOWEROS_TENANT_DEFAULT_PLAYBOOK` | `latest` | Assign newest published playbook when UI/API omit `playbook_version_id` |
| `TOWEROS_TENANT_AUTO_SEED_HOLIDAYS` | `true` | Seed PH national holidays into `tenant_public_holidays` |
| `TOWEROS_TENANT_SEED_NEXT_HOLIDAY_YEAR` | `true` | Also seed next calendar year |

## Platform UI

`/platform/tenants/create`:

- **Rollout playbook** dropdown — default “Latest published”; optional pin to v1/v2
- **Seed demo dataset** — unchecked by default (dev/UAT only)
- Success screen shows playbook version, holidays seeded, recommended hostnames

## API response (create tenant)

Additional fields:

```json
{
  "playbook_version": "2.0.0",
  "public_holidays_seeded": 32,
  "holiday_years": [2026, 2027],
  "domain_endpoints": { "...": "..." }
}
```

## Tests

```bat
cd backend
php artisan test --filter=TenantRolloutBootstrapService
```

## Still optional (not auto)

- Alliance demo seeder (`seed` checkbox or `TOWEROS_DEMO_SEED=true`)
- Gate backfill (existing tenants with pre-migration rollouts only)

See also: [rollout-playbook-phase4.md](./rollout-playbook-phase4.md)
