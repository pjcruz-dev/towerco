# TowerOS release process — Phase 1 (manual promote)

**Status:** Phase 1 active  
**Scope:** Process only — no product module code changes (Ticketing, Project-One, E-Approval, etc. are unchanged).  
**Goal:** Staging first → tag a release → Production promote → rollback to previous tag if needed.

---

## Rules (always)

1. **Never** deploy a new commit straight to Production.
2. Deploy and test on **Staging** first (staging tenant host).
3. Promote the **same git tag** (same code) to Production.
4. Keep the **previous tag** as the restore point.
5. Prefer **app rollback** (redeploy previous tag). Database snapshot restore is break-glass only.

---

## Environments vs tenants

| Concept | Meaning |
|---------|---------|
| **Environment (deploy)** | Which app build is running (staging stack vs production stack) |
| **Tenant environment** | Customer workspace: separate tenant record + DB per `staging` / `production` (see [`tenant-domain-slugs.md`](./tenant-domain-slugs.md)) |

Release process deploys **application code**. Tenant data stays isolated per env.

| Tier | Example host | Purpose |
|------|----------------|---------|
| Local / staging tenant | `staging.myapp.localhost` | Validate release |
| Production tenant | `app.{brand}` | Customer traffic |

---

## Phase 1 — Manual release runbook

### A. Before you start

- [ ] CI green on `main` (GitHub Actions: backend + frontend)
- [ ] Know last good production tag (e.g. `v1.0.4`)
- [ ] Staging tenant reachable and login works
- [ ] Migrations reviewed (if any): note whether they are additive / reversible

### B. Deploy to Staging

1. Ensure the commit to release is on `main` (or the branch you deploy from).
2. Deploy/restart **Staging** API + web with that commit.
3. Run migrations on Staging only:

```bash
# API container / host — adjust to your staging access method
php artisan migrate --force
php artisan tenants:migrate --force
# or: php artisan toweros:migrate
```

4. Clear caches if you use config/route cache in that environment.

### C. Staging smoke checklist

Run on the **staging tenant** (not production):

| # | Check | Pass? |
|---|--------|-------|
| 1 | Platform or tenant login | |
| 2 | `/up` (API health) | |
| 3 | Dashboard / home loads | |
| 4 | Project-One: open a rollout or gate approvals list | |
| 5 | Ticketing: list tickets + open New ticket | |
| 6 | E-Approval: open forms or a submission (if enabled) | |
| 7 | Documents / Sites: open one record (if enabled) | |
| 8 | No console/API 500s on those flows | |

If any fail → **do not tag / do not promote**. Fix on a branch, retest Staging.

### D. Tag the release

Only after Staging smoke passes:

```bash
git checkout main
git pull
git tag -a v1.0.5 -m "Release v1.0.5 — short summary of changes"
git push origin v1.0.5
```

Use semver: `vMAJOR.MINOR.PATCH`.

### E. Promote to Production

1. Deploy **the same tag** `v1.0.5` (same commit) to Production API + web.
2. Run migrations on Production (if this release includes them).
3. Smoke a shorter prod check: login, one critical module, `/up`.
4. Record the release: previous tag = `v1.0.4`, current = `v1.0.5`.

### F. Rollback (restore)

If Production is broken after promote:

1. Redeploy **previous good tag** (e.g. `v1.0.4`) to Production API + web.
2. Restart services; confirm `/up` and login.
3. **Do not** blindly roll back migrations unless you have a tested down path and a DB snapshot.
4. If a migration corrupted data: restore Aurora/RDS **snapshot** from before the migrate (break-glass), then redeploy the good tag.
5. Hotfix separately; leave Production on the last good tag until the fix is Staging-validated.

---

## Version / tag naming

| Tag | Meaning |
|-----|---------|
| `v1.0.4` | Last known good (example) |
| `v1.0.5` | New release under test / just promoted |
| `v1.0.5-rc.1` | Optional: staging-only candidate (optional discipline) |

Footer version in the UI (if shown) should match the deployed tag when you wire that later (Phase 3+).

---

## What Phase 1 does **not** include

- No button inside TowerOS to deploy Production
- No automatic ECR / ECS promote (see later phases)
- No changes to Ticketing, Project-One, or other modules

---

## Full roadmap (phases)

| Phase | Name | Status | What you get |
|-------|------|--------|--------------|
| **1** | Manual promote + rollback | **Done** | Staging first, git tags, smoke checklist, restore via previous tag — this doc |
| **2** | Tenant environments | **Done** | Staging + production tenant workspaces per customer — [`tenant-environments-phase-2.md`](./tenant-environments-phase-2.md) |
| **3** | CI/CD automation | **Done** | `main` → Staging; tag `v*` → Production digest promote — [`cicd-phase-3.md`](./cicd-phase-3.md) |
| **4** | Hardening | **Done** | Circuit breaker, snapshots, rollback workflow, drill — [`hardening-phase-4.md`](./hardening-phase-4.md) |

---

## Related docs

- [`hardening-phase-4.md`](./hardening-phase-4.md) — circuit breaker, snapshots, rollback drill
- [`cicd-phase-3.md`](./cicd-phase-3.md) — GitHub Actions Staging / Production deploy
- [`aws-ecs-cicd.md`](./aws-ecs-cicd.md) — target AWS pipeline
- [`tenant-domain-slugs.md`](./tenant-domain-slugs.md) — staging vs production hosts / tenants
- [`tenant-environments-phase-2.md`](./tenant-environments-phase-2.md) — Add environment checklist
- [`../local-development-docker-guide.md`](../guides/local-development-docker-guide.md) — local Docker
- Root [`README.md`](../../README.md) — production deployment overview
