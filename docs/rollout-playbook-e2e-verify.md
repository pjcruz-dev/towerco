# Rollout Playbook — E2E verification (Alliance tenant)

Manual QA script for production sign-off after Phases 7–14. Run once per release candidate on a staging or local stack with the **Alliance** demo tenant.

**Tenant:** `alliance.localhost`  
**Login:** `admin@alliance.localhost` / `password`  
**Frontend:** `http://alliance.localhost`  
**API:** `http://localhost:8000/api/v1` (with `X-Tenant-Domain: alliance.localhost` if SPA is on central host)

---

## Prerequisites

1. Migrations applied: `php artisan migrate` and `php artisan tenants:migrate`
2. Alliance tenant seeded with rollout playbook v2
3. Optional realtime: Soketi on port 6001, `BROADCAST_CONNECTION=pusher`, `NEXT_PUBLIC_SOCKET_ENABLED=true`

---

## Steps

| # | Action | Expected |
|---|--------|----------|
| 1 | Sign in as tenant admin | Dashboard loads with rollout KPIs |
| 2 | Open **Project One → Rollouts → New rollout** | Create BTS Globe rollout; SLA days populated from playbook |
| 3 | Open rollout detail → **Timeline** | Phases visible; gate statuses show Pending/Passed |
| 4 | Record **Day-1** (delivery period start) | `tssr_approved_date` set; milestone dates shift |
| 5 | **SAQ tab** — add 3 candidates with GPS/camera (or manual coords) | Candidates listed; map pins appear |
| 6 | Select candidate #1 | TCO site ID issued; program status advances |
| 7 | Pass **Site Hunting** gate on timeline | Gate shows Passed; audit entry created |
| 8 | **CME tab** — submit inspection report with photo | Report saved; linked to rollout |
| 9 | **Profitability** — finance role sees full numbers; viewer sees summary only | RBAC enforced |
| 10 | Record **RFI** with actual date | Status `completed`; SLA variance calculated |
| 11 | **Rollouts list** — filter by status, export CSV | Filters work; CSV downloads |
| 12 | **Dashboard map** — open pin for rollout | Map shows candidate/site location |
| 13 | **Projects** — link rollout to a project | Cross-navigation from project detail |
| 14 | **Field mobile** — narrow viewport SAQ form | Large touch targets; offline draft sync works |
| 15 | **Realtime** (two tabs, optional) — change gate in tab A | Tab B refreshes rollout detail without manual reload |

---

## Platform playbook upgrade (operator)

| # | Action | Expected |
|---|--------|----------|
| P1 | Platform console → tenant directory → **Upgrade** playbook v1 → v2 | Confirmation modal explains existing rollouts unchanged |
| P2 | Create new rollout in Alliance after upgrade | New rollout uses v2 playbook version |
| P3 | Open pre-upgrade rollout | Still on original playbook version and timeline |

---

## Automated regression

```bash
cd backend
php -d memory_limit=1G artisan test --filter=Rollout
```

Target: **51+ tests**, all green.

---

## Sign-off

- [ ] All 15 tenant steps pass
- [ ] Platform upgrade steps pass (if applicable)
- [ ] Rollout test filter green in CI
- [ ] No P1/P2 defects open for rollout lifecycle

See also: [rollout-playbook-phase14.md](./rollout-playbook-phase14.md) · [project-one-roadmap.md](./project-one-roadmap.md)
