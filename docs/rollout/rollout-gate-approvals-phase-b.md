# Rollout gate approvals — Phase B (Rollout policy bundle)

Phase B delivers a **platform Rollout policy bundle**: one assignable object that combines playbook version, timeline layout, SLA windows, hidden phases, and default gate approval chains.

---

## What shipped

| Area | Deliverable |
|------|-------------|
| Central DB | `rollout_policy_bundles` table; `tenant_playbook_bindings.rollout_policy_bundle_id` |
| Platform API | `GET/POST /platform/rollout-policies`, `GET/PATCH /platform/rollout-policies/{id}`, `POST .../publish` |
| Tenant assign | `POST /platform/tenants/{id}/playbook` accepts `rollout_policy_bundle_id` (preferred) or legacy `playbook_version_id` |
| Tenant sync | `TenantPlaybookSyncService` merges bundle snapshot + gate policies into tenant DB |
| Platform UI | `/platform/playbooks` — policy list + create draft |
| Platform UI | `/platform/playbooks/policies/[id]` — editor (reorder, hide, WD ranges, gate chains, SLA summary) |
| Platform UI | `/platform` tenant directory — one-click assign published policy bundle |
| CLI | `rollout:policy:publish {code}`, `tenants:assign-rollout-policy`, `tenants:sync-playbook --policy=` |

---

## Operator workflow

1. **Publish playbook v2** — `/platform/playbooks` → *Publish playbook v2* (or `rollout-playbook:publish-v2`).
2. **Create draft policy** — *Create draft policy* → base on v2 → opens editor.
3. **Customize** — reorder phases (↑↓), hide phases (eye icon), edit WD start/end, adjust SLA working days, edit gate approval chains.
4. **Validate SLA** — editor shows post–Day-1 total vs target; save/publish blocked if mismatch.
5. **Publish policy** — *Publish policy* (draft → published).
6. **Assign tenant** — `/platform` tenant directory → select policy code → *Assign* / *Change* → confirms sync to tenant DB.

---

## API quick reference

### Create draft

```http
POST /api/v1/platform/rollout-policies
{
  "playbook_version_id": "<uuid>",
  "code": "towerco-standard",
  "name": "TowerCo Standard"
}
```

### Update draft

```http
PATCH /api/v1/platform/rollout-policies/{id}
{
  "timeline_templates": { "bts": [ ... ] },
  "hidden_phases": { "bts": ["phase_key"] },
  "gate_approval_policies": { ... },
  "delivery_periods": { "bts": { "working_days": 115 } }
}
```

### Assign to tenant

```http
POST /api/v1/platform/tenants/{tenantId}/playbook
{
  "rollout_policy_bundle_id": "<uuid>",
  "sync_tenant_database": true
}
```

---

## CLI

```bash
# Publish a draft policy by code
php artisan rollout:policy:publish towerco-standard

# Assign published policy to tenant by domain
php artisan tenants:assign-rollout-policy --policy=towerco-standard --domain=alliance.localhost

# Sync playbook + policy bundle (with RBAC refresh)
php artisan tenants:sync-playbook --domain=alliance.localhost --policy=towerco-standard --with-rbac
```

---

## Tenant impact

On assign + sync:

- `tenant_rollout_playbook_config.playbook_snapshot` receives effective timeline (hidden phases removed, sort order applied).
- `gate_approval_policies` on tenant config is overwritten from bundle defaults (tenant may still override locally per Phase A).
- Existing rollouts keep their frozen playbook version; **new rollouts** use the updated binding.

---

## Tests

```bash
php artisan test --filter=RolloutPolicyBundle
php artisan test --filter=RolloutGateApproval
```

---

## Next: Phase C

Custom phases in platform catalog (`counts_toward_sla`, snapshot on assign).
