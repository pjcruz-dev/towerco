# TowerOS

Enterprise multi-tenant telecom SaaS for tower companies (TowerCos). Modular monolith: **Laravel API** + **Next.js** tenant workspace + **platform superadmin console**.

**Board reference:** [`docs/Rules/TowerOS_Board_Presentation.pdf`](docs/Rules/TowerOS_Board_Presentation.pdf) — module names, phases, and roadmap.

---

## Table of contents

1. [Technology stack](#technology-stack)
2. [Platform modules](#platform-modules)
3. [Repository layout](#repository-layout)
4. [Prerequisites](#prerequisites)
5. [Local development (Docker) — start to finish](#local-development-docker--start-to-finish)
6. [Daily development commands](#daily-development-commands)
7. [Authentication & security](#authentication--security)
8. [Microsoft Entra ID (SSO)](#microsoft-entra-id-sso)
9. [Tenant features & URLs](#tenant-features--urls)
10. [Platform console](#platform-console)
11. [Database & migrations](#database--migrations)
12. [Production deployment](#production-deployment)
13. [Troubleshooting](#troubleshooting)
14. [Documentation index](#documentation-index)

---

## Technology stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Next.js 16, React 19, TypeScript |
| Database | **MySQL 8.0** — database-per-tenant ([stancl/tenancy](https://tenancyforlaravel.com/)) |
| Cache / queues | Redis (cache, sessions, permission cache) — local `toweros-redis`; production Docker Redis on EC2 or ElastiCache; queues `sync` locally, `redis` in prod |
| Auth | Sanctum (tenant SPA) + Passport (platform console) |
| SSO | Microsoft Entra ID per tenant |
| RBAC | Spatie Laravel Permission |
| Realtime | Pusher protocol / Soketi (optional in dev) |
| Maps | MapLibre GL |
| UI | Tailwind CSS 4, shadcn/ui, Geist |

> The board deck mentions PostgreSQL + PostGIS + TimescaleDB. **Current implementation uses MySQL** with decimal coordinates and MapLibre. See [`docs/architecture/tenant-isolation-mysql.md`](docs/architecture/tenant-isolation-mysql.md).

---

## Platform modules

| Module | Purpose | Typical URL (tenant) |
|--------|---------|----------------------|
| **Foundation** | Auth, tenancy, RBAC, settings | `/dashboard`, `/admin/settings` |
| **Sites** | Shared site registry | `/sites` |
| **PROJECT-ONE** | Projects, rollouts, SAQ/CME, gate approvals | `/project-one` |
| **TOWER-ONE** | Tower registry | `/tower-one` |
| **FIBER-ONE** | Fiber routes | `/fiber-one` |
| **ASSET-ONE** | Asset registry | `/asset-one` |
| **GIS** | Operational map | `/gis` |
| **E-Approval** | Forms, submissions, approvals | `/e-approval` |

Roadmap modules (TASK-ONE, VENDOR-ONE, etc.) are in the board deck; not all are in the tenant shell yet.

**Deep dives:** [PROJECT-ONE](docs/roadmaps/project-one-roadmap.md) · [E-Approval](docs/modules/e-approval.md) · [E-Approval form builder](docs/modules/e-approval-form-builder.md) · [E-Approval go-live](docs/modules/e-approval-go-live-checklist.md)

---

## Repository layout

```text
TowerOS/
├── backend/              Laravel API (central + tenant routes)
├── frontend/             Next.js tenant app + platform console
├── docs/                 All non-prod docs: phases, Rules, guides, rollout
│   ├── Rules/            Board deck + rollout playbook
│   ├── archives/         Local scratch only (gitignored)
│   ├── guides/           Docker / Podman / performance
│   ├── roadmaps/         Product roadmaps
│   ├── rollout/          Playbook + gate-approval phases
│   └── local-dev/        Optional Windows launchers (use npm run … day-to-day)
├── docker-compose.yml    mysql, api, web, phpmyadmin
├── env.docker.example    Root Docker ports & MySQL credentials
├── package.json          npm scripts (dev, dev:fresh, …)
└── scripts/              Docker helpers (grants, fresh reset)
```

**AWS note:** Deploy builds Docker images from `backend/` and `frontend/` only. The entire `docs/` tree is **not** included in production/staging images. See [`docs/README.md`](docs/README.md).

**Docker service names** (use these in `docker compose exec`):

| Service | Container | Role |
|---------|-----------|------|
| `api` | `toweros-api` | Laravel (`:8000`) |
| `web` | `toweros-web` | Next.js (`:80`) |
| `mysql` | `toweros-mysql` | MySQL (`:3307` on host) |
| `phpmyadmin` | `toweros-phpmyadmin` | DB UI (`:8080`) |

There is **no** `backend` service name in Compose.

---

## Prerequisites

| Tool | Purpose |
|------|---------|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | Run full stack (recommended) |
| [Node.js](https://nodejs.org/) LTS | Root `npm run dev` scripts only |
| Git | Clone and pull updates |

**Optional (without Docker):** PHP 8.3, Composer, MySQL 8, Node 22+ — see [Host-only development](#host-only-development).

---

## Local development (Docker) — start to finish

Estimated time: **~15 minutes** first run (image build + MySQL init).

### Step 1 — Clone and open the repo

```bash
git clone <your-repo-url> TowerOS
cd TowerOS
```

### Step 2 — Create environment files

```bash
# Root: Docker ports & MySQL passwords
copy env.docker.example .env.docker          # Windows
# cp env.docker.example .env.docker          # macOS / Linux

# App config (created automatically on first API boot if missing)
# backend/.env.docker  → copied to backend/.env once (see entrypoint)
# frontend/.env.docker → used by web container
```

Install root npm scripts:

```bash
npm install
```

### Step 3 — Start the stack

**Clean first install (wipes MySQL, seeds superadmin + playbooks):**

```bash
npm run dev:fresh
```

When prompted, type **`FRESH`** (all caps).

**Normal start (keep existing data):**

```bash
npm run dev
# or detached:
docker compose --env-file .env.docker up -d --build
```

Verify containers:

```bash
docker compose --env-file .env.docker ps
```

Expect: `toweros-mysql` (healthy), `toweros-api`, `toweros-web`, `toweros-phpmyadmin`.

### Step 4 — MySQL grants (first time per MySQL volume)

Tenant databases are named `tenant<uuid>`. The app user needs `CREATE DATABASE`:

```bash
npm run dev:mysql:grants
```

### Step 5 — Seed platform data (skip if you used `dev:fresh`)

```bash
npm run dev:seed
```

Creates:

- Platform superadmin (`superadmin@toweros.local` / `123123123` by default)
- Published rollout playbooks and policy bundles
- Passport personal access client

### Step 6 — Platform superadmin login

| | |
|---|---|
| URL | http://localhost/platform/login |
| Email | `superadmin@toweros.local` |
| Password | `123123123` (from `backend/.env.docker` → `TOWEROS_PLATFORM_DEV_PASSWORD`) |

If you see **Unauthenticated** after a fresh reset, clear browser `localStorage` key `toweros.platform.session` and sign in again.

### Step 7 — Create your first tenant

1. Open http://localhost/platform/tenants/create  
2. Example local tenant:

| Field | Example |
|-------|---------|
| Environment | `local` |
| Slug | `atc` |
| Brand domain | `alliancetowers.com` (or `example.com`) |
| Hostname | `atc.localhost` (auto-suggested) |
| Rollout playbook | Latest published |

3. Click **Create tenant** and wait **1–2 minutes**.  
4. Save the one-time **bootstrap admin password** and **tenant login URL**.

Provisioning automatically:

- Creates central tenant + domain rows  
- Creates MySQL database `tenant<uuid>`  
- Runs tenant migrations  
- Assigns rollout policy, syncs playbook, seeds holidays  
- Creates `admin@<hostname>` (e.g. `admin@atc.localhost`)

### Step 8 — Tenant login (recommended)

Add to `C:\Windows\System32\drivers\etc\hosts` (Administrator):

```text
127.0.0.1 atc.localhost
```

Open **http://atc.localhost/login** and sign in with the bootstrap admin from step 7.

> Prefer `*.localhost` for tenant UX. The platform host (`localhost`) is for superadmin only.

### Step 9 — After `git pull` (migrations)

```bash
docker compose exec api php artisan toweros:migrate
```

Runs central + all tenant migrations.

---

### Local URLs

| Service | URL |
|---------|-----|
| Tenant web (central host) | http://localhost |
| Tenant web (tenant host) | http://atc.localhost |
| API | http://localhost:8000 |
| Platform console | http://localhost/platform |
| phpMyAdmin | http://localhost:8080 |

### MySQL connection (host tools)

| Setting | Value |
|---------|--------|
| Host | `127.0.0.1` |
| Port | `3307` |
| Database | `toweros` |
| User | `root` |
| Password | `toweros` |

SSO and tenant settings live in **central** DB table `tenant_sso_configs`, not in `tenant<uuid>` databases.

---

## Daily development commands

| Task | Command |
|------|---------|
| Start (foreground logs) | `npm run dev` |
| Start (background) | `docker compose --env-file .env.docker up -d` |
| Stop | `npm run dev:down` |
| API logs | `npm run dev:logs:api` |
| All logs | `npm run dev:logs` |
| Central migrate | `docker compose exec api php artisan migrate` |
| All tenants migrate | `docker compose exec api php artisan tenants:migrate` |
| Both | `docker compose exec api php artisan toweros:migrate` |
| Repair missing tenant DBs | `docker compose exec api php artisan toweros:repair-tenant-databases --create` |
| Full local reset | `npm run dev:fresh` |
| Restart API | `docker compose restart api` |

**Detailed walkthrough:** [`docs/guides/local-development-docker-guide.md`](docs/guides/local-development-docker-guide.md)

---

## Authentication & security

### Two consoles

| Console | URL | Auth | Who |
|---------|-----|------|-----|
| **Platform (superadmin)** | `/platform` | Passport | TowerOS operators — provision tenants |
| **Tenant workspace** | `https://app.{customer}/` or `*.localhost` | Sanctum (+ optional MFA) | Customer org users |

Tenant users do **not** use the platform console for daily work.

### Tenant sign-in options

| Method | Where configured |
|--------|------------------|
| Email + password | Team & Access (users) |
| Microsoft Entra ID | **Administration → Settings → Sign-in & security** (`/admin/settings`) |
| MFA (TOTP) | **Settings → MFA Security**; tenant `mfa_required` on platform |

### Standard security defaults (per tenant)

Configured under **Sign-in & security**:

| Setting | Default | Meaning |
|---------|---------|---------|
| Auto-provision on Microsoft sign-in | Off | Users must exist in Team & Access first |
| Disable password when SSO enabled | On | Normal users use Microsoft; break-glass `admin@…` keeps password |
| Allowed email domains | Empty | Optional restrict (e.g. `atc.com`) |

### APP_KEY and encrypted SSO secrets (Docker)

- **`backend/.env`** is the only place for `APP_KEY` (generated once on first boot). **Do not reset** after saving Microsoft client secrets.  
- **`backend/.env.docker` must not define `APP_KEY`.** Docker Compose `env_file` injects variables into the container; an empty `APP_KEY=` there **overrides** `.env` on every `docker compose restart` and breaks decryption.  
- Entrypoint copies `.env.docker` → `.env` **only if `.env` is missing**, and runs `key:generate` **only if** `.env` has no `APP_KEY=base64:…` line.  
- After fixing a key mismatch, re-paste the Microsoft client secret on **Sign-in & security** once and save.  
- `backend/.env` must contain **`APP_KEY=base64:…`** (not a bare `APP_KEY` line). If you see *No application encryption key*, run `docker compose up -d --force-recreate api` so an old empty `APP_KEY=` container env is cleared.

---

## Microsoft Entra ID (SSO)

Configured **per tenant** (not on the platform console).

### A. Azure Portal (one app registration per customer)

1. **Microsoft Entra ID → App registrations → New registration**  
2. **Supported account types:** single org (typical)  
3. **Authentication → Web redirect URI** — must match TowerOS exactly, e.g. local:

   ```text
   http://localhost:8000/api/v1/auth/sso/azure/callback
   ```

4. **Certificates & secrets → New client secret** — copy the **Value** immediately  
5. **API permissions:** `openid`, `profile`, `email`, `User.Read` (+ optional group reads for role mapping)  
6. **Enterprise applications → Users and groups** — assign who may use the app  

Note from app **Overview**:

- **Application (client) ID**  
- **Directory (tenant) ID** — use this in TowerOS (not `common` for single-tenant apps)

### B. TowerOS tenant admin

1. Sign in at **http://atc.localhost/login** (tenant admin)  
2. **Administration → Settings → Sign-in & security** → http://atc.localhost/admin/settings  
3. Enable Microsoft sign-in; paste Client ID, **Directory (tenant) ID**, and **client secret Value**  
4. **Entra group → role mapping:** use `{}` when empty (not `[]`) to skip Entra role sync — assign roles only in **Team & Access**. When you map a group, roles are **merged** on each Microsoft sign-in (they do not remove roles already assigned in TowerOS), e.g.:

   ```json
   {
     "<entra-group-object-id>": ["viewer"]
   }
   ```

   A user in that group gets `viewer` from Entra plus any roles you set in Team & Access (e.g. `e_approval_requestor`). No matching group → existing TowerOS roles are unchanged.

5. **Save sign-in settings** → **Validate Microsoft app**  
6. Ensure the user exists in **Team & Access** (or enable auto-provision). **Bulk CSV import** (`email`, `name`, `role`) matches users case-insensitively — Microsoft sign-in reuses the same account (no duplicate). See [docs/modules/tenant-user-bulk-import.md](docs/modules/tenant-user-bulk-import.md).  
7. Test: **http://atc.localhost/login** → **Sign in with Microsoft**

### Production redirect URI

Use your real API host, e.g.:

```text
https://api.customer.com/api/v1/auth/sso/azure/callback
```

Same host routing model as local; update Azure and TowerOS together.

---

## Tenant features & URLs

After login on a tenant host (e.g. `atc.localhost`):

| Area | Path |
|------|------|
| Dashboard | `/dashboard` |
| Notifications | `/notifications` |
| Sites | `/sites` |
| PROJECT-ONE | `/project-one` (rollouts, projects, approvals, playbook, holidays) |
| TOWER-ONE | `/tower-one` |
| FIBER-ONE | `/fiber-one` |
| ASSET-ONE | `/asset-one` |
| GIS | `/gis` |
| E-Approval | `/e-approval` (forms, submissions, approvals, audit, settings) |
| Team & Access | `/users` |
| Sign-in & security | `/admin/settings` |
| KPI & SLA (admin JSON) | `/admin/settings/kpi` |
| Sessions | `/settings/sessions` |
| MFA | `/settings/security/mfa` |

**E-Approval** runs inside this Next.js app only. Standalone formbuilder is decommissioned (historical only; not in this repo or deploy).

---

## Platform console

| Feature | Path |
|---------|------|
| Superadmin dashboard | `/platform` |
| Tenant directory | `/platform#tenant-directory` |
| Create tenant | `/platform/tenants/create` |
| Rollout playbooks | `/platform/playbooks` |
| Helper center | `/platform/helper-center` |

**Tenant hostname patterns:** [`docs/infrastructure/tenant-domain-slugs.md`](docs/infrastructure/tenant-domain-slugs.md)

---

## Database & migrations

| Scope | Command |
|-------|---------|
| Central only | `docker compose exec api php artisan migrate` |
| All tenants | `docker compose exec api php artisan tenants:migrate` |
| Both | `docker compose exec api php artisan toweros:migrate` |

New tenants from the platform UI run tenant migrations automatically during provisioning.

**Central tables include:** `tenants`, `domains`, `tenant_sso_configs`, playbooks, platform users.  
**Tenant tables include:** `users`, rollouts, e-approval, sites, etc. (per `tenant<uuid>` database).

---

## Production deployment

**Confirmed production baseline:** Amazon **EC2 t3.large** + **RDS MySQL db.t3.medium Multi-AZ** (AWS 1-year subscription). Full provision + env + cutover steps:

→ [`docs/infrastructure/aws-ec2-rds-production.md`](docs/infrastructure/aws-ec2-rds-production.md)  
→ Env template: [`backend/.env.production.example`](backend/.env.production.example)

| Model | Best for | Doc |
|-------|----------|-----|
| **A — Linux EC2 + RDS** (current production) | First customer go-live, fixed monthly cost | [`aws-ec2-rds-production.md`](docs/infrastructure/aws-ec2-rds-production.md) |
| **B — ECS Fargate + Aurora** (scale path) | Multi-tenant scale, autoscaling, zero-downtime | [`aws-ecs-cicd.md`](docs/infrastructure/aws-ecs-cicd.md) |

**Release process:** Phase 1 — [`release-runbook.md`](docs/infrastructure/release-runbook.md). Phase 2 — [`tenant-environments-phase-2.md`](docs/infrastructure/tenant-environments-phase-2.md). Phase 3 — [`cicd-phase-3.md`](docs/infrastructure/cicd-phase-3.md). Phase 4 — [`hardening-phase-4.md`](docs/infrastructure/hardening-phase-4.md).

---

### Is TowerOS ready for production?

**Application:** Yes for the modules you have been testing (Project-One, Sites, Documents, Document register, E-Approval, Ticketing). Priority automated tests pass; run your staging manual checklist before cutover.

**Operations:** Production is ready when **you** complete the checklist below — not only when code is merged.

| Area | Status | Before go-live |
|------|--------|----------------|
| Staging validation | Your checklist on `staging.*` | Complete smoke + module flows |
| Secrets & TLS | Required | `APP_KEY`, DB passwords, OAuth secrets in a vault (not git) |
| HTTPS everywhere | Required | ACM cert + Route 53 (or ALB) |
| Database | Required | RDS MySQL 8 Multi-AZ; app user can `CREATE DATABASE` |
| Redis | **Required** | Queues + cache — Docker on EC2 or ElastiCache (gap vs subscription slide) |
| Queue worker | **Required** | `php artisan queue:work` always running |
| Scheduler | **Required** | Cron every minute: `schedule:run` |
| File storage | Required | S3 (`TOWEROS_TENANT_FILES_DISK=s3`) — not local EC2 disk |
| CDN | Recommended | CloudFront; set `AWS_URL` when enabled |
| Backups | Required | AWS Backup **30-day** (RDS + EBS) + S3 versioning |
| Monitoring | Required | CloudWatch alarms (5xx, disk, RDS CPU, free storage) |
| Mail | Required | SES or Microsoft 365 SMTP for approvals / gate emails |
| SSO | Per tenant | Entra redirect URI on production API host |

---

### A. Confirmed AWS production stack

| Resource | Spec | TowerOS use |
|----------|------|-------------|
| **EC2 t3.large** | 2 vCPU, 8 GB, 50 GB root | Docker: API + Next.js + Redis + queue worker |
| **EBS gp3** | 100 GB | Images, logs, temp |
| **RDS MySQL db.t3.medium** | 2 vCPU, 4 GB, 50 GB, **Multi-AZ** | Central `toweros` + per-tenant DBs |
| **S3 Standard** | 50 GB/mo + request allowance | Documents, exports, binders |
| **CloudFront** | CDN | Static assets + file downloads |
| **Route 53** | DNS | Console / app / tenant hosts |
| **CloudWatch** | Logs + metrics + alarms | Health monitoring |
| **AWS Backup** | **30-day** retention | RDS + EBS |

**Must still add:** Redis, queue worker, scheduler, TLS (ALB/Nginx), SES. Details in the [EC2 + RDS runbook](docs/infrastructure/aws-ec2-rds-production.md).

```text
Internet → Route 53 → CloudFront → ALB/Nginx :443
  → Laravel :8000 + Next.js :80 (Docker on EC2)
  → RDS MySQL Multi-AZ | S3 | Redis | CloudWatch | AWS Backup (30d)
```

**Quick start on the EC2:**

```bash
cp backend/.env.production.example backend/.env
# Set APP_KEY, DB_HOST=<rds-endpoint>, AWS_BUCKET, domains, SES
docker compose --env-file .env.docker up -d --build redis api
docker compose --env-file .env.docker --profile web up -d --build web
# Then: migrate, seed, passport client, systemd queue worker, cron schedule:run, TLS
```

---

### B. ECS Fargate (scale path)

Target for multi-tenant scale: **ECS Fargate**, **Aurora MySQL 8**, **ElastiCache Redis**, **ALB + WAF**, **S3**, **Secrets Manager**.

Full diagram and pipeline: [`docs/infrastructure/aws-ecs-cicd.md`](docs/infrastructure/aws-ecs-cicd.md)

#### Environment checklist (all deployments)

| Concern | Production guidance |
|---------|---------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Stable secret — never rotate without re-encrypting SSO secrets |
| `CENTRAL_DOMAINS` | Platform hostnames only (e.g. `console.toweros.app`) |
| `TOWEROS_ALLOW_TENANT_ON_CENTRAL_HOST` | `false` |
| Tenant API | Same hostname as SPA (`app.customer.com/api/v1`) |
| TLS | ACM certificates; wildcard DNS for tenant apps |
| Database | MySQL 8; central DB + one DB per tenant |
| Queues | Redis + dedicated worker service |
| Scheduler | Cron or ECS scheduled task: `schedule:run` |
| Files | S3 for `TOWEROS_TENANT_FILES_DISK` |
| Mail | SES or Microsoft 365 SMTP |
| SSO | Per-tenant Entra app; production redirect URI on API host |
| Bootstrap passwords | `TOWEROS_TENANT_BOOTSTRAP_EXPOSE_PASSWORD_IN_API=false` |

#### Deploy runbook (ECS summary)

1. **CI:** PR → lint, test, build (`.github/workflows/ci.yml`)
2. **Build & push** Docker images to ECR (`toweros-api`, `toweros-web`)
3. **Migrate:** ECS one-off: `php artisan migrate --force` then `tenants:migrate --force`
4. **Deploy** ECS services (API, web, worker, scheduler)
5. **Smoke test:** `/up`, platform login, tenant login, one SSO flow

#### Post-deploy tenant operations

- Create tenants from the **production** platform console with production `brand_domain` and DNS.
- Point customer DNS (CNAME) to ALB.
- Configure **Sign-in & security** per tenant.
- Run `toweros:migrate` after releases that include migrations.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `service "backend" is not running` | Use service name **`api`**: `docker compose exec api …` |
| `tenant_sso_configs` doesn't exist | `docker compose exec api php artisan migrate` (central table) |
| Query on wrong DB for SSO | SSO config is **central** only; pull latest API code |
| `The MAC is invalid` / client secret cannot be decrypted | Remove `APP_KEY` from `.env.docker`; keep stable key in `backend/.env`; `docker compose restart api`; re-save client secret once |
| `Undefined variable $request` (SSO) | Pull latest; `docker compose restart api` |
| Group mapping save failed | Use `{}` not `[]` for empty mapping |
| Access denied creating `tenant*` DB | `npm run dev:mysql:grants` |
| Orphan tenant / missing DB | `docker compose exec api php artisan toweros:repair-tenant-databases --create` |
| Port in use | Change ports in `.env.docker` |
| `atc.localhost` won't open | Add hosts file entry `127.0.0.1 atc.localhost` |
| Microsoft login: tenant context | Sign in from **tenant URL** (`atc.localhost`), not platform host |
| Account not provisioned (SSO) | Add user in Team & Access or enable auto-provision |

**More:** [`docs/guides/local-development-docker-guide.md`](docs/guides/local-development-docker-guide.md#troubleshooting)

---

## Documentation index

| Document | Description |
|----------|-------------|
| [`docs/guides/local-development-docker-guide.md`](docs/guides/local-development-docker-guide.md) | Step-by-step Docker setup (printable checklist) |
| [`docs/infrastructure/aws-ec2-rds-production.md`](docs/infrastructure/aws-ec2-rds-production.md) | **Production:** EC2 t3.large + RDS MySQL Multi-AZ (confirmed AWS stack) |
| [`backend/.env.production.example`](backend/.env.production.example) | Production env template (RDS, S3, Redis, SES, CloudFront) |
| [`docs/infrastructure/aws-ecs-cicd.md`](docs/infrastructure/aws-ecs-cicd.md) | Scale path: AWS ECS, Aurora, CI/CD |
| [`docs/infrastructure/hardening-phase-4.md`](docs/infrastructure/hardening-phase-4.md) | Phase 4: circuit breaker, snapshots, rollback drill |
| [`docs/infrastructure/cicd-phase-3.md`](docs/infrastructure/cicd-phase-3.md) | Phase 3: GitHub Actions Staging / Production CD |
| [`docs/infrastructure/release-runbook.md`](docs/infrastructure/release-runbook.md) | Phase 1: Staging → tag → Production → rollback |
| [`docs/infrastructure/tenant-environments-phase-2.md`](docs/infrastructure/tenant-environments-phase-2.md) | Phase 2: Staging + production tenant workspaces |
| [`docs/infrastructure/tenant-domain-slugs.md`](docs/infrastructure/tenant-domain-slugs.md) | Hostnames per environment |
| [`docs/architecture/tenant-isolation-mysql.md`](docs/architecture/tenant-isolation-mysql.md) | Multi-tenant MySQL |
| [`docs/README.md`](docs/README.md) | Docs home (phases, Rules, archives) — not deployed to AWS |
| [`docs/Rules/TowerOS_Board_Presentation.pdf`](docs/Rules/TowerOS_Board_Presentation.pdf) | Board / investor module map |
| [`docs/design-system/DESIGN_SYSTEM.md`](docs/design-system/DESIGN_SYSTEM.md) | Full UI design system |
| [`docs/design-system/toweros-design-system.md`](docs/design-system/toweros-design-system.md) | Token / component summary |
| [`docs/modules/e-approval.md`](docs/modules/e-approval.md) | E-Approval module |
| [`docs/roadmaps/project-one-roadmap.md`](docs/roadmaps/project-one-roadmap.md) | PROJECT-ONE / rollouts |
| [`.cursor/rules/toweros.mdc`](.cursor/rules/toweros.mdc) | Coding standards |
| [`.cursor/rules/uiux-theme.mdc`](.cursor/rules/uiux-theme.mdc) | UI/UX rules |

---

## Design & UX

- **Font:** Geist  
- **Style:** Operational minimalism (Azure Portal / ServiceNow-inspired)  
- **Layout:** Left sidebar, top header, module-first navigation  
- **Details:** [`docs/design-system/DESIGN_SYSTEM.md`](docs/design-system/DESIGN_SYSTEM.md)

---

## Host-only development (fastest on Windows)

Running the API on host PHP avoids ~1.5–2 s/request of Docker-on-Windows overhead (~6× faster). Infra stays in Docker.

1. Start infra only: `docker compose --env-file .env.docker up -d mysql redis soketi`
2. Configure `backend/.env`: `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `CENTRAL_DB_PORT=3307`, `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379`
3. If you previously ran the Docker API, clear its baked config: `cd backend && php artisan config:clear`
4. Terminal 1: `cd backend && php artisan serve --host=127.0.0.1 --port=8000`
5. Terminal 2: `cd frontend && npm run dev -- -p 80`

Switch back to Docker API: stop the host `php artisan serve`, then `docker compose --env-file .env.docker up -d api` (compose overrides `DB_HOST=mysql` automatically).

Prefer full Docker (`npm run dev`) for the least setup; prefer host mode for the fastest requests. Full step-by-step: [`docs/guides/local-development-docker-guide.md`](docs/guides/local-development-docker-guide.md#run-modes--performance-updated-jul-2026).

---

## License & support

Proprietary — Alliance / TowerOS. For internal setup questions, use this README and `docs/guides/local-development-docker-guide.md` first, then check API logs: `npm run dev:logs:api`.
