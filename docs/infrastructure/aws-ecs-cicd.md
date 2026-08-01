# TowerOS — AWS ECS, Aurora, CI/CD (scale path)

**Current production baseline** is EC2 + RDS MySQL — see [`aws-ec2-rds-production.md`](./aws-ec2-rds-production.md).

This document is the **scale-out** target (ECS Fargate + Aurora + ElastiCache) and CI/CD foundation; resources are not provisioned in the local dev stack.

## Architecture overview

```mermaid
flowchart TB
  subgraph users [Users]
    Browser[Tenant / Platform browsers]
  end

  subgraph edge [Edge]
    ALB[Application Load Balancer]
    WAF[AWS WAF]
  end

  subgraph compute [Compute — ECS Fargate]
    Web[Next.js web service]
    API[Laravel API service]
    Worker[Queue workers]
    Scheduler[Scheduler / cron]
  end

  subgraph data [Data]
    Aurora[(Aurora MySQL 8 — central + tenant DBs)]
    Redis[(ElastiCache Redis)]
    S3[(S3 — uploads / exports)]
  end

  subgraph cicd [CI/CD]
    GH[GitHub Actions]
    ECR[Amazon ECR]
    ECS[ECS deploy]
  end

  Browser --> WAF --> ALB
  ALB --> Web
  ALB --> API
  API --> Aurora
  API --> Redis
  Worker --> Aurora
  Worker --> Redis
  Web --> API
  GH --> ECR --> ECS
  ECS --> compute
```

## Environment tiers

| Tier | Purpose | Notes |
|------|---------|-------|
| **dev** | Engineer sandboxes | Smallest Fargate tasks; single-AZ Aurora dev cluster |
| **staging** | Pre-production validation | Mirrors prod topology at reduced scale |
| **production** | Customer workloads | Multi-AZ Aurora, autoscaling, WAF, Secrets Manager |

## ECS services (Fargate)

| Service | Image | Port | Health check |
|---------|-------|------|--------------|
| `toweros-api` | Laravel (PHP-FPM + nginx sidecar or Octane) | 8000 | `GET /up` |
| `toweros-web` | Next.js standalone | 3000 | `GET /` |
| `toweros-worker` | Same API image, `queue:work` | — | process heartbeat |
| `toweros-scheduler` | Same API image, `schedule:run` loop | — | CloudWatch logs |

**Task sizing (starting point):**

- API: 0.5 vCPU / 1 GB (scale on CPU + request count)
- Web: 0.5 vCPU / 1 GB
- Worker: 0.25 vCPU / 512 MB per queue priority lane

## Aurora MySQL

- **Engine:** Aurora MySQL 8.0 compatible (matches local MySQL 8 dev)
- **Topology:** writer + 1–2 readers (production)
- **Tenancy:** database-per-tenant (stancl/tenancy); central schema on `toweros` database
- **Backups:** automated snapshots (7–35 days by tier), point-in-time recovery enabled
- **Secrets:** credentials in AWS Secrets Manager; injected into ECS task definitions

## Supporting AWS services

- **ElastiCache Redis** — cache, sessions (if not DB), queues (or SQS for cross-AZ durability)
- **S3** — document uploads, CSV exports, static report artifacts
- **ACM + Route 53** — TLS certificates and tenant wildcard DNS (`*.toweros.app`)
- **CloudWatch** — logs, metrics, alarms (5xx rate, queue depth, Aurora CPU)
- **Secrets Manager** — DB, Redis, OAuth, Sanctum keys

## CI/CD pipeline (GitHub Actions)

Repository workflow: [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)

### Pull request (CI)

1. Backend: `composer install`, PHP lint, PHPUnit
2. Frontend: `npm ci`, ESLint, TypeScript check, production build
3. Optional: Docker image build (no push) to catch Dockerfile regressions

### Main branch (CD — staging)

1. Build and push images to ECR (`toweros-api`, `toweros-web`)
2. Run central + tenant migration task on ECS one-off task
3. Rolling deploy to staging ECS services
4. Smoke tests against staging ALB

### Release tag `v*` (CD — production)

Workflow: [`.github/workflows/deploy-production.yml`](../../.github/workflows/deploy-production.yml) — see [`cicd-phase-3.md`](./cicd-phase-3.md).

1. Promote tested ECR image digests (no rebuild)
2. Blue/green or rolling deploy with circuit breaker
3. Post-deploy: `/up`, tenant login smoke, queue drain check

## Required GitHub / AWS secrets

| Secret / var | Used for |
|--------------|----------|
| `TOWEROS_CD_ENABLED` (variable) | Set `true` to enable CD on push/tag |
| `AWS_ROLE_ARN` | OIDC deploy role (no long-lived keys) |
| `AWS_REGION` | e.g. `ap-southeast-1` |
| `ECR_REGISTRY` | `123456789012.dkr.ecr.region.amazonaws.com` |
| ECS cluster/service/subnet vars | See [`cicd-phase-3.md`](./cicd-phase-3.md) |

Application secrets remain in AWS Secrets Manager per environment.

## Local vs cloud parity

| Concern | Local (`dev.cmd`) | AWS |
|---------|-------------------|-----|
| Database | Docker MySQL 3307 | Aurora MySQL |
| Cache/queue | Optional Redis | ElastiCache / SQS |
| Web port | 80 (`http://localhost`) | 443 via ALB |
| Tenancy | `*.localhost` | `*.toweros.app` |

## Phase 1 deliverables checklist

- [x] CI workflow skeleton (lint, test, build)
- [x] Manual release runbook (Staging → tag → Production → rollback) — [`release-runbook.md`](./release-runbook.md)
- [x] Automated CD workflows (Phase 3) — [`cicd-phase-3.md`](./cicd-phase-3.md) + `deploy-staging.yml` / `deploy-production.yml`
- [ ] Terraform / CDK modules for VPC, ECS, Aurora, Redis
- [ ] ECR repositories + lifecycle policies
- [ ] Staging environment first deploy (enable `TOWEROS_CD_ENABLED` after AWS exists)

## Operations runbook (summary)

**Deploy:** merge to `main` → CI green → **Deploy Staging** (Phase 3) → smoke checklist → **git tag `v*`** → **Deploy Production** (digest promote). Manual steps: [`release-runbook.md`](./release-runbook.md). Enablement: [`cicd-phase-3.md`](./cicd-phase-3.md).

**Migrate:** Staging first; then Production on promote. ECS one-off: `php artisan toweros:migrate --force` (workflow) or `migrate` + `tenants:migrate`.

**Rollback:** Actions → **Deploy Rollback** ([`hardening-phase-4.md`](./hardening-phase-4.md)). ECS circuit breaker auto-reverts bad rolling deploys; DB snapshot restore is break-glass only.

**Scale:** target tracking on API CPU (70%) and ALB request count per target.

**Hardening (Phase 4):** circuit breaker, pre-migrate snapshots, quarterly drill — [`hardening-phase-4.md`](./hardening-phase-4.md).
