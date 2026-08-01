# TowerOS Performance Runbook

This runbook documents the performance optimization pass (caching, query rewrites, DB indexes,
SLA denormalization, and frontend bundle/fetching work) and the operational steps required to
realize those gains in each environment.

The codebase baseline is already reasonable (30s dashboard caches, 60s React Query `staleTime`,
debounced search, queued exports/ingestion). The changes below are targeted tuning, not a rewrite.

---

## 1. Backend caching (in-repo)

Heavy dashboard reads are wrapped in `TenantScopedCache::remember` (30-120s), mirroring
`TenantWorkspaceDashboardService`:

- `TicketingDashboardService::build` (30s, per user)
- `EApprovalAnalyticsService::build` (60s, per tenant + date range)
- `EApprovalFormWorkspaceService::buildDashboard` (30s, per form + viewer; auth/404 resolved
  outside the cache)
- `AssetOneDashboardService` / `TowerOneDashboardService` / `FiberOneDashboardService` (30s)
- `PlatformDashboardService` (90s, keyed by tenant count + max `updated_at` so it busts on change)

`TenantScopedCache` transparently falls back to the array store in testing / when the `cache`
table is missing, so these are safe without Redis, but see the infra checklist for production.

## 2. Query rewrites / N+1 removal (in-repo)

- E-Approval submissions-over-time: one `GROUP BY DATE(created_at)` instead of a COUNT per day.
- `EApprovalMasterDataService::listSets`: `withCount('rows')` instead of a COUNT per row.
- `FeedbackGapReportService::gaps`: batch-prefetch prior user questions by `conversation_id`.
- `EApprovalFormWorkspaceService`: cached published slug -> form-id map (60s) with an authoritative
  fallback scan, so per-page workspace lookups do not scan all published forms.
- `RolloutDashboardMetricsService` open-SAQ count: `has('candidates', '<', 3)` (SQL correlated
  subquery) instead of loading every SAQ program and filtering in PHP.

## 3. Database indexes (tenant migrations)

`2026_07_24_120000_add_rollout_ticketing_performance_indexes.php`:

- `rollout_programs`: `(parent_rollout_id, status, updated_at)`, plus `status`, `mno`,
  `project_type`, `region` (matches `RolloutProgramIndexService` filters).
- `rollout_timeline_phases`: `(gate_status)`.
- `ticketing_tickets`: `(priority, status)`, `(category, status)`, `(status, resolved_at)`.
  `(status, sla_due_at)` already existed from the SLA columns migration.

## 4. SLA / metric denormalization (tenant migrations + runners)

- `ticketing_tickets.sla_status` (`2026_07_24_130000...`): denormalized `on_track|at_risk|breached`,
  written on ticket create/update and refreshed every run by `TicketingSlaRunnerService`
  (`ticketing:sla-run`, every 5 min). The dashboard SLA-at-risk KPI and category analytics now use
  SQL aggregates against this column instead of loading active tickets into PHP.
- `rollout_programs.sla_remaining_working_days` + `sla_risk_computed_on`
  (`2026_07_24_140000...`): recomputed daily by `rollout:recompute-sla-risk` (scheduled 01:30) and
  lazily on the first dashboard hit of the day. `RolloutSlaAtRiskService` then filters in SQL.
  Recompute uses the base query builder so it never bumps `updated_at`.

## 5. Frontend bundle + data fetching (in-repo)

- Code-splitting via `next/dynamic` (`ssr: false` + skeleton):
  - MapLibre `OperationalMap` -> `components/maps/operational-map-lazy.tsx` (used by the
    Project-One map panel and rollout SAQ map).
  - Recharts dashboard charts -> `dashboard-{bar,line,donut}-chart.tsx` are now thin lazy wrappers
    over `*-impl.tsx`, with a shared `DashboardChartSkeleton`.
  - Rollout per-phase work panels in `rollout-phase-timeline.tsx` (loaded when a phase expands).
  - `pdf-lib` in `lib/e-approval/e-approval-attachment-pdf.ts` (dynamic `import()` on export/preview).
- Controlled-documents search debounced (300ms) via `useDebouncedValue`.
- Reduced global polling: sidebar gate-count no longer polls (relies on rollout Echo invalidation +
  focus refetch); tenant notifications relaxed from 60s to 120s.
- Unified E-Approval workspace query key (`EAPPROVAL_FORM_WORKSPACES_QUERY_KEY`) so the sidebar and
  command palette share one cached fetch.
- Gate-approvals list/count queries dropped `staleTime: 0` + `refetchOnMount: "always"` for
  15s/30s staleTimes (still invalidated by mutations + Echo).
- `next.config.ts`: `experimental.optimizePackageImports: ['lucide-react','recharts']`.

---

## 6. Infrastructure checklist (run per environment — NOT in repo)

These must be verified operationally; the code changes above assume them in production:

- [ ] `CACHE_STORE=redis` and a reachable Redis/ElastiCache instance.
- [ ] `QUEUE_CONNECTION=redis` **with queue workers running** (Horizon / ECS worker service).
      Exports, AI ingestion, and notifications are queued; without workers they never run.
- [ ] `AI_ASSISTANT_VECTOR_STORE=opensearch` with `AI_ASSISTANT_OPENSEARCH_ENDPOINT` /
      `AI_ASSISTANT_OPENSEARCH_INDEX` set; the `database` store is dev-only.
- [ ] Run the new tenant migrations for **every** tenant:
      `php artisan tenants:migrate` (or the project's per-tenant migrate command).
- [ ] Confirm scheduler is running so `ticketing:sla-run` (5 min) and `rollout:recompute-sla-risk`
      (daily 01:30) keep the denormalized SLA columns fresh.
- [ ] After deploy, the first ticketing SLA run backfills `sla_status`; the first rollout dashboard
      hit (or the scheduled command) backfills `sla_remaining_working_days`.

---

## Validation per phase

- Backend: `php artisan test` for touched modules (Ticketing, EApproval, Rollout, AiAssistant);
  `vendor/bin/pint`.
- Frontend: `npm run typecheck`, `npm run build` (check bundle-size delta), route-context vitest.
- Note: local SQLite tenant tests fail at `setUp` (`no such table: permissions`) — a pre-existing
  environment issue; rely on CI (MySQL).
