# Rollout Playbook — Phase 14 (Platform polish, realtime & QA)

Production hardening: live updates, playbook upgrade UX, documentation, and end-to-end test coverage.

## Goals

- Rollout changes propagate via **realtime** (optional Soketi in dev)
- Platform **playbook upgrade** workflow is clear for operators
- Module readiness documented; **full test suite** for rollout happy path

## Backend

### 1. Realtime events ✅

Broadcast on tenant channel `tenant.{id}.rollouts`:

| Event | When |
|-------|------|
| `RolloutUpdated` | Gate change, Day-1, RFI, cancel, SLA recalc |
| `RolloutCandidateSelected` | TCO site ID issued |

Laravel broadcasting + Soketi (Pusher protocol); channel auth at `POST /api/v1/broadcasting/auth`.

### 2. Playbook upgrade flow (platform) ✅

| Change | Detail |
|--------|--------|
| Platform tenant detail | **Upgrade** action → confirmation sheet → reassign version + sync snapshot |
| Policy | Confirm: existing rollouts unchanged; new rollouts use new version |
| Audit | `platform.playbook.synced` log entry in `TenantPlaybookSyncService` |

### 3. Test suite expansion ✅

| Flow | Test class |
|------|------------|
| Create BTS rollout → Day-1 → 3 candidates → select → gate pass → RFI | `RolloutHappyPathApiTest` |
| Batch create parent + children | `RolloutBatchApiTest` |
| Holiday add → SLA shift | `RolloutHolidaySlaIntegrationTest` |
| Profitability RBAC tiers | `RolloutProfitabilityRbacTest` |
| Broadcast dispatch | `RolloutBroadcastTest` |
| File upload + candidate attach | `RolloutCandidateMediaApiTest` (Phase 7) |

Run: `php -d memory_limit=1G artisan test --filter=Rollout` — **51 tests**, all green.

## Frontend ✅

| Surface | Change |
|---------|--------|
| Rollout detail/list | `useRolloutRealtime` — Laravel Echo + Pusher.js; invalidates React Query on broadcasts |
| Platform tenant directory | Upgrade playbook sheet with policy confirmation |
| Env | `NEXT_PUBLIC_PUSHER_*` vars alongside `NEXT_PUBLIC_SOCKET_ENABLED` |

## Documentation deliverables ✅

- [README.md](../README.md) PROJECT-ONE row updated (~75%, rollout production-ready)
- [rollout-playbook-e2e-verify.md](./rollout-playbook-e2e-verify.md) — manual QA script (Alliance tenant, 15 steps)
- [project-one-roadmap.md](../roadmaps/project-one-roadmap.md) — Phase 14 marked complete

## CI ✅

```yaml
- run: php -d memory_limit=1G artisan test --filter=Rollout
```

## Verify locally

1. Two browser tabs on same rollout — gate change in tab A refreshes tab B (with Soketi + `NEXT_PUBLIC_SOCKET_ENABLED=true`)
2. Platform upgrades Alliance tenant v1 → v2; new rollout uses v2; old rollout unchanged
3. Full test filter passes; manual E2E script completed once

## Module complete checklist

PROJECT-ONE rollout track is **production-ready**:

- [x] Phases 1–6 (pilot loop)
- [x] Phase 7 — media
- [x] Phase 8 — region SLA
- [x] Phase 9 — milestone cycles
- [x] Phase 10 — registry & audit
- [x] Phase 11 — projects link
- [x] Phase 12 — map & QMS
- [x] Phase 13 — field mobile
- [x] Phase 14 — realtime & QA

See also: [project-one-roadmap.md](../roadmaps/project-one-roadmap.md) · [rollout-playbook-phase13.md](./rollout-playbook-phase13.md) · [rollout-playbook-e2e-verify.md](./rollout-playbook-e2e-verify.md)
