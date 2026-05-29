# Rollout gate approvals — Phase C (Custom phases)

Phase C adds a **platform custom phase catalog** so super admins can define reusable timeline phases (e.g. LGU clearance, finance capex release) and insert them into rollout policy bundles.

---

## What shipped

| Area | Deliverable |
|------|-------------|
| Central DB | `rollout_custom_phases` catalog table |
| Tenant DB | `rollout_timeline_phases.counts_toward_sla`, `is_custom`, `catalog_phase_id` |
| Platform API | `GET/POST /platform/rollout-phases`, `GET/PATCH/DELETE /platform/rollout-phases/{id}` |
| SLA validation | Post–Day-1 budget excludes phases with `counts_toward_sla: false` |
| Policy editor | Add custom phase from catalog, toggle SLA flag, remove custom rows |
| Playbooks UI | Custom phase catalog section on `/platform/playbooks` |
| Rollout create | `RolloutProgramService::instantiateTimeline()` copies custom metadata to phase rows |
| Rollout API | Timeline detail exposes `is_custom` and `counts_toward_sla` |

---

## Operator workflow

1. **Create catalog phase** — `/platform/playbooks` → *Create custom phase* (key, label, WD range, templates, SLA flag).
2. **Edit policy bundle** — open draft policy → *Add custom phase…* → pick catalog entry → reorder as needed.
3. **Adjust SLA flag** — per phase, toggle *Counts toward SLA* for post–Day-1 budget (e.g. LGU clearance off-SLA).
4. **Publish policy** → assign to tenant (Phase B flow).
5. **New rollouts** — custom phases appear on tenant timeline from snapshot; existing rollouts unchanged.

---

## `counts_toward_sla`

| Value | Behavior |
|-------|----------|
| `true` (default) | Phase WD span included in post–Day-1 SLA validation |
| `false` | Phase still appears on timeline and can have gates; excluded from SLA budget math |

Use `false` for parallel or administrative gates that should not consume delivery-period working days.

---

## API quick reference

### List catalog phases

```http
GET /api/v1/platform/rollout-phases?template=bts
```

### Create catalog phase

```http
POST /api/v1/platform/rollout-phases
{
  "phase_key": "lgu_clearance",
  "label": "LGU Clearance",
  "default_anchor": "tssr_approved",
  "default_working_day_start": 10,
  "default_working_day_end": 14,
  "counts_toward_sla": false,
  "applicable_templates": ["bts", "rtb"]
}
```

### Archive catalog phase

```http
DELETE /api/v1/platform/rollout-phases/{id}
```

Archiving sets `is_active = false`. Existing policy timelines and tenant snapshots are not modified.

---

## Tenant impact

On policy assign + sync, custom phases in `timeline_templates` flow into `tenant_rollout_playbook_config.playbook_snapshot`. New rollouts create `rollout_timeline_phases` rows with:

- `is_custom = true`
- `catalog_phase_id` (source catalog UUID)
- `counts_toward_sla` (from policy template)

---

## Tests

```bash
php artisan migrate --force
php artisan tenants:migrate --force
php artisan test --filter=RolloutPolicyBundle
php artisan test --filter=RolloutCustomPhase
```

---

## Next: Phase D

Operational polish — pending approval widget, escalation email, delegation, SES defaults.
