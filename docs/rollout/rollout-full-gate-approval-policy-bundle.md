# Rollout policy bundle — full gate approval (all phases)

Platform policy bundle **`towerco-full-gate-approval`** enables formal multi-step gate approval on **every timeline phase** in playbook v2 (BTS, RTB, Colocation).

## What it includes

- **Playbook base:** v2.0.0 timeline and SLA (unchanged structure)
- **Gate approvals:** every phase `enabled: true` with role chains matched to phase owner (e.g. SAQ phases → `saq → pmo`, construction → `cme → pmo → tenant_admin`)

Tenants can still override chains on **Project One → Rollout playbook → Gate approval policies**.

## One-time setup (platform)

From `backend/`:

```bash
# 1. Ensure playbook v2 is published
php artisan rollout-playbook:publish-v2

# 2. Create + publish the full gate-approval policy bundle
php artisan rollout:policy:create-full-gate-approval --publish
```

Optional custom code/name:

```bash
php artisan rollout:policy:create-full-gate-approval \
  --code=my-tenant-full-gates \
  --name="Alliance Full Gate Approval" \
  --publish
```

## Assign to a tenant

### Option A — Policy bundle (platform template)

Replace domain with your tenant host (e.g. `app.atc.localhost`):

```bash
php artisan tenants:assign-rollout-policy \
  --policy=towerco-full-gate-approval \
  --domain=app.atc.localhost \
  --with-rbac
```

### Option B — Direct tenant sync (fastest)

Applies gate policies to the tenant DB only (no central bundle publish). Use when you only need approvals on the current assigned playbook:

```bash
php artisan tenants:sync-full-gate-approval-policies --domain=app.atc.localhost
```

### Option C — Create, publish, and assign in one step

```bash
php artisan rollout:policy:create-full-gate-approval \
  --publish \
  --assign-domain=app.atc.localhost \
  --with-rbac
```

## Platform UI (alternative)

1. `/platform/playbooks` → publish playbook v2 if needed  
2. After CLI create, bundle appears under rollout policies  
3. `/platform` tenant directory → assign **towerco-full-gate-approval**

## Verify on tenant

1. **Rollout playbook** → Gate approval policies: all phases listed, most enabled  
2. Open a rollout → **Timeline** → enabled phases show **Request** / approval flow  
3. **Gate approvals** inbox for approvers
