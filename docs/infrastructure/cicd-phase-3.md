# TowerOS CI/CD — Phase 3

**Status:** Phase 3 workflows delivered (gated until AWS is configured)  
**Scope:** Automate Staging deploy on `main` and Production promote on git tags `v*` (same image digests, no rebuild).  
**Does not change:** Product modules (Ticketing, Project-One, etc.).

Related: [`release-runbook.md`](./release-runbook.md) · [`aws-ecs-cicd.md`](./aws-ecs-cicd.md) · [`tenant-environments-phase-2.md`](./tenant-environments-phase-2.md)

---

## Pipeline

```text
PR → CI (lint/test/build)
        ↓
merge to main → Deploy Staging (build → ECR → migrate → ECS staging)
        ↓
smoke on staging tenant (Phase 1 checklist)
        ↓
git tag vX.Y.Z → Deploy Production (retag digests → migrate → ECS prod)
```

| Workflow | Trigger | Action |
|----------|---------|--------|
| [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) | PR / push `main` | Quality gates |
| [`.github/workflows/deploy-staging.yml`](../../.github/workflows/deploy-staging.yml) | Push `main` + CD enabled, or manual | Build/push `:sha` + `:staging`, migrate, ECS force deploy |
| [`.github/workflows/deploy-production.yml`](../../.github/workflows/deploy-production.yml) | Tag `v*`, or manual | Retag `:sha` → `:vX.Y.Z` + `:production`, migrate, ECS force deploy |

Production **does not rebuild** images — it promotes the digests already tested on Staging for that commit.

---

## Enable CD (when AWS is ready)

### 1. Repository variable

| Variable | Value |
|----------|--------|
| `TOWEROS_CD_ENABLED` | `true` |

Until this is set, staging/production workflows **skip** on push/tag (manual `workflow_dispatch` can still dry-run).

### 2. GitHub Environments

Create environments:

| Environment | Protection |
|-------------|------------|
| `staging` | Optional reviewers |
| `production` | **Required reviewers** (your “click to deploy” approve) |

### 3. Secrets

| Secret | Purpose |
|--------|---------|
| `AWS_ROLE_ARN` | OIDC role for GitHub Actions |
| `AWS_REGION` | e.g. `ap-southeast-1` (or use var) |
| `ECR_REGISTRY` | `123456789012.dkr.ecr.region.amazonaws.com` (or use var) |

Prefer **repository/organization variables** for non-secret config:

| Variable | Example |
|----------|---------|
| `AWS_REGION` | `ap-southeast-1` |
| `ECR_REGISTRY` | `123456….dkr.ecr.ap-southeast-1.amazonaws.com` |
| `ECR_API_REPO` | `toweros-api` |
| `ECR_WEB_REPO` | `toweros-web` |
| `ECS_CLUSTER_STAGING` | `toweros-staging` |
| `ECS_CLUSTER_PRODUCTION` | `toweros-production` |
| `ECS_SERVICE_API_STAGING` / `_PRODUCTION` | `toweros-api` |
| `ECS_SERVICE_WEB_STAGING` / `_PRODUCTION` | `toweros-web` |
| `ECS_SERVICE_WORKER_STAGING` / `_PRODUCTION` | `toweros-worker` |
| `ECS_MIGRATE_TASK_STAGING` / `_PRODUCTION` | `toweros-migrate` |
| `ECS_SUBNETS_STAGING` / `_PRODUCTION` | `subnet-aaa,subnet-bbb` |
| `ECS_SECURITY_GROUPS_STAGING` / `_PRODUCTION` | `sg-xxx` |

App secrets (DB, `APP_KEY`, etc.) stay in **AWS Secrets Manager**, not GitHub.

### 4. AWS prerequisites

- [ ] ECR repos `toweros-api`, `toweros-web`
- [ ] ECS clusters + services for staging and production
- [ ] Task definitions pull `:staging` (staging) and `:production` (prod) **or** pin to digest after promote
- [ ] Migrate task definition `toweros-migrate` (same API image, one-shot)
- [ ] IAM OIDC trust for GitHub + permissions: ECR push/pull, ECS update/run-task, pass role

### 5. First enablement test

1. Set `TOWEROS_CD_ENABLED=true` and secrets/vars.
2. Actions → **Deploy Staging** → Run workflow → `dry_run: true`.
3. Run again without dry-run on a safe staging cluster.
4. Tag a commit already on Staging: `git tag -a v0.0.0-cd-test -m "CD test" && git push origin v0.0.0-cd-test`.
5. Approve **production** environment if prompted; confirm retag + deploy.

---

## Operator day-to-day (Phase 3)

1. Merge PR → CI green → Staging deploy runs (if CD enabled).
2. Smoke Staging tenant ([`release-runbook.md`](./release-runbook.md) checklist).
3. Tag release: `git tag -a v1.0.5 -m "…" && git push origin v1.0.5`.
4. Approve Production environment in GitHub (manual gate).
5. Confirm `/up` and one critical flow on production hosts.
6. Rollback: Actions → **Deploy Rollback** with previous tag ([`hardening-phase-4.md`](./hardening-phase-4.md)), or re-run Deploy Production with older tag.

---

## Image tag convention

| Tag | Meaning |
|-----|---------|
| `:abcdef123456` | Immutable commit (12-char SHA) |
| `:staging` | Moving pointer — last Staging deploy |
| `:v1.0.5` | Immutable release |
| `:production` | Moving pointer — current Production |

---

## Safety notes

- Workflows are **gated** so open PRs / unconfigured repos do not fail CD.
- Missing migrate subnets → migrate step warns and skips (run manually).
- Missing `:sha` image on Production promote → fails with clear error (Staging first).
- No in-app TowerOS “Deploy” button — promote is GitHub Environment approval + tag.

---

## Roadmap status

| Phase | Name | Status |
|-------|------|--------|
| **1** | Manual promote + rollback | Done |
| **2** | Tenant environments | Done |
| **3** | CI/CD automation | **Done** (workflows + this doc; enable when AWS ready) |
| **4** | Hardening | Done — [`hardening-phase-4.md`](./hardening-phase-4.md) |
