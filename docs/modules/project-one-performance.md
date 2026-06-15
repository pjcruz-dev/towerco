# Project One — API performance

Guidance for fast dashboard and rollout list loads. Complements [rollout-playbook-phase12.md](../rollout-playbook-phase12.md).

---

## Dashboard (split load)

### Fast first paint (default)

```http
GET /api/v1/project-one/dashboard
```

Returns KPIs, milestones, sites, rollout **metrics** — **no** `map_pins` (large geo payload deferred).

### Map pins (lazy)

Either:

```http
GET /api/v1/project-one/dashboard/map
```

Or legacy opt-in on the main dashboard:

```http
GET /api/v1/project-one/dashboard?include=map
GET /api/v1/project-one/dashboard?with_map=1
```

Frontend (implemented): `useProjectOneDashboard` loads KPIs via `GET /project-one/dashboard`, then map pins via `GET /project-one/dashboard/map` in parallel. `MapPanel` shows site fallback pins until rollout pins arrive.

### Caching (server)

| Payload | TTL |
|---------|-----|
| Core dashboard | 30s per tenant |
| Rollout KPI block | 30s per tenant + user |
| Map pins | 45s per tenant |

---

## Rollouts list (thin rows)

Default list uses **`view=summary`** (implicit):

- Counts only: `candidate_count`, `phase_count`, `child_count`
- No `timelinePhases` / `candidates` collections in JSON
- Batch children still included as compact rows

Full relations for screens that need inline phase/candidate data:

```http
GET /api/v1/project-one/rollouts?view=full
```

Exports always use `view=full` internally.

---

## SLA at risk filter

`sla_at_risk=1` and dashboard KPI **Rollout SLA risk** share `RolloutSlaAtRiskService` with a **60s** cached ID list (working-day calendar per region).

---

## Related platform work

- E-Approval performance: slim form payloads, assignable-user cache — see prior architecture notes.
- Global: React Query `staleTime`, avoid blocking UI on secondary requests, `/me` permission cache.
