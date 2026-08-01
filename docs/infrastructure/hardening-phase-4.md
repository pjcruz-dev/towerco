# TowerOS hardening — Phase 4

**Status:** Phase 4 complete (runbooks + workflow safeguards; apply AWS settings when infra exists)  
**Scope:** Make releases safer: ECS circuit breaker, pre-migrate DB snapshots, emergency rollback workflow, on-call drill.  
**Does not change:** Product modules (Ticketing, Project-One, etc.).

Related: [`cicd-phase-3.md`](./cicd-phase-3.md) · [`release-runbook.md`](./release-runbook.md) · [`aws-ecs-cicd.md`](./aws-ecs-cicd.md)

---

## What Phase 4 adds

| Control | Purpose |
|---------|---------|
| **ECS deployment circuit breaker** | Auto-stop a bad rolling deploy and roll back the service |
| **Pre-migrate DB snapshot** | Point-in-time restore if a migration corrupts data |
| **Emergency rollback workflow** | One Actions run to re-promote a previous `v*` tag |
| **On-call rollback drill** | Practice restore so the team can execute under pressure |

---

## 1. ECS deployment circuit breaker

Configure on **every** ECS service (API, web, worker) in staging and production.

### Desired deployment configuration

```json
{
  "deploymentConfiguration": {
    "deploymentCircuitBreaker": {
      "enable": true,
      "rollback": true
    },
    "maximumPercent": 200,
    "minimumHealthyPercent": 100
  }
}
```

### Apply (CLI example)

```bash
aws ecs update-service \
  --cluster toweros-production \
  --service toweros-api \
  --deployment-configuration "deploymentCircuitBreaker={enable=true,rollback=true},maximumPercent=200,minimumHealthyPercent=100"
```

Repeat for `toweros-web` and `toweros-worker` (and staging cluster).

### Checklist

- [ ] Staging API / web / worker circuit breaker on  
- [ ] Production API / web / worker circuit breaker on  
- [ ] CloudWatch alarm on ECS deployment failures (optional but recommended)  
- [ ] ALB target group health checks aligned with `/up` (API) and `/` (web)

**Note:** Circuit breaker recovers **task/service** failures. It does **not** undo a completed DB migration — that is why snapshots exist.

---

## 2. Pre-migrate database snapshots

Before Production (and preferably Staging) migrate steps, take a named snapshot.

### Variables

| Variable | Example | Use |
|----------|---------|-----|
| `RDS_SNAPSHOT_ENABLED` | `true` | Turn on workflow snapshot step |
| `RDS_DB_CLUSTER_IDENTIFIER_PRODUCTION` | `toweros-prod` | Aurora cluster (preferred) |
| `RDS_DB_INSTANCE_IDENTIFIER_PRODUCTION` | `toweros-prod` | Non-Aurora RDS instance |
| `RDS_DB_CLUSTER_IDENTIFIER_STAGING` | `toweros-staging` | Optional staging |
| `RDS_DB_INSTANCE_IDENTIFIER_STAGING` | — | Optional staging |

Workflows create identifiers like:

`toweros-pre-migrate-v1-0-5-20260724T031500Z`

### Break-glass restore

1. Stop writers / scale services to 0 if needed.  
2. Restore snapshot to a **new** cluster/instance (do not overwrite blindly).  
3. Point Secrets Manager / task defs at restored endpoint **or** rename cutover per DBA runbook.  
4. Redeploy last good app tag (`Deploy Rollback` workflow).  
5. Verify `/up`, login, one write path.  
6. Post-incident: root cause, fix forward on Staging first.

---

## 3. Emergency rollback workflow

Workflow: [`.github/workflows/deploy-rollback.yml`](.github/workflows/deploy-rollback.yml)

**When:** Production is broken after a promote and you need the previous release quickly.

1. Actions → **Deploy Rollback** → Run workflow.  
2. Input `previous_tag` = last good tag (e.g. `v1.0.4`).  
3. Approve **production** environment.  
4. Workflow retags that release’s digests to `:production` and force-deploys ECS.  
5. **Do not** run migrate on rollback unless a DBA explicitly requires it (default: skip migrate).

Also valid: re-run **Deploy Production** with the older tag (same digest promote path).

---

## 4. On-call rollback drill (quarterly)

Run on **Staging** first; Production drill only with change window approval.

| Step | Action | Time box | Pass? |
|------|--------|----------|-------|
| 1 | Note current tag (`:production` / footer / ECR) | 2 min | |
| 2 | Confirm previous good tag exists in ECR | 3 min | |
| 3 | Trigger **Deploy Rollback** (or Deploy Production) with previous tag on Staging | 5 min | |
| 4 | Wait ECS stable; hit `/up` | 10 min | |
| 5 | Staging tenant login + one critical flow | 10 min | |
| 6 | Document duration + gaps | 5 min | |

**Pass criteria:** restore to previous tag in ≤ 30 minutes without guessing credentials.

Store drill notes in your ops channel / ticket; link the date in release chat.

---

## 5. Production promote hardening checklist

Use before approving a Production environment deploy:

- [ ] Staging smoke checklist passed ([`release-runbook.md`](./release-runbook.md))  
- [ ] Same git tag / SHA that is on Staging  
- [ ] Previous tag recorded for rollback  
- [ ] `RDS_SNAPSHOT_ENABLED=true` (Production) or manual snapshot taken  
- [ ] ECS circuit breaker enabled on prod services  
- [ ] On-call engineer available for 30–60 minutes post-deploy  
- [ ] Comms ready if customer-facing risk  

---

## Monitoring (minimum)

| Signal | Action |
|--------|--------|
| ALB 5xx spike | Investigate; trigger rollback if deploy-related |
| ECS service unstable / circuit breaker rollback | Confirm auto-rollback; verify health |
| Queue depth rising | Check worker service / logs |
| Migrate task non-zero exit | Stop promote; fix on Staging |

Wire CloudWatch alarms when AWS infra is live (Phase 3 enablement).

---

## What Phase 4 does **not** do

- Does not provision Terraform / VPC (still a separate infra deliverable)  
- Does not put a Deploy button inside TowerOS UI  
- Does not auto-restore databases without human break-glass  

---

## Roadmap status

| Phase | Name | Status |
|-------|------|--------|
| **1** | Manual promote + rollback | Done |
| **2** | Tenant environments | Done |
| **3** | CI/CD automation | Done |
| **4** | Hardening | **Done** (this doc + rollback workflow + snapshot hooks) |
