# TowerOS

Enterprise multi-tenant telecom SaaS for tower companies (TowerCos). Modular monolith: **Laravel API** + **Next.js** tenant workspace + **platform superadmin console**.

**Board reference:** [`Rules/TowerOS_Board_Presentation.pdf`](Rules/TowerOS_Board_Presentation.pdf) — module names, phases, and roadmap.

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
| Cache / queues | Redis (production); local Docker often uses `sync` + database cache |
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

**Deep dives:** [PROJECT-ONE](docs/project-one-roadmap.md) · [E-Approval](docs/modules/e-approval.md) · [E-Approval form builder](docs/modules/e-approval-form-builder.md) · [E-Approval go-live](docs/modules/e-approval-go-live-checklist.md)

---

## Repository layout

```text
TowerOS/
├── backend/              Laravel API (central + tenant routes)
├── frontend/             Next.js tenant app + platform console
├── docs/                 Architecture, modules, runbooks
├── legacy/               Archived reference (not deployed)
├── Rules/                Board presentation
├── docker-compose.yml    mysql, api, web, phpmyadmin
├── env.docker.example    Root Docker ports & MySQL credentials
├── package.json          npm scripts (dev, dev:fresh, …)
└── scripts/              Docker helpers (grants, fresh reset)
```

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

**Detailed walkthrough:** [`docs/local-development-docker-guide.md`](docs/local-development-docker-guide.md)

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

**E-Approval** runs inside this Next.js app only. Legacy formbuilder under `legacy/atcformbuiilder/` is not deployed.

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

Target architecture: **AWS ECS Fargate**, **Aurora MySQL 8**, **ElastiCache Redis**, **ALB + WAF**, **S3**, **Secrets Manager**.  
Full diagram and pipeline: [`docs/infrastructure/aws-ecs-cicd.md`](docs/infrastructure/aws-ecs-cicd.md)

### Environment checklist

| Concern | Production guidance |
|---------|---------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Stable secret in Secrets Manager — never rotate without re-encrypting SSO secrets |
| `CENTRAL_DOMAINS` | Platform hostnames only (e.g. `console.toweros.app`) |
| `TOWEROS_ALLOW_TENANT_ON_CENTRAL_HOST` | `false` unless you have a controlled split-host API design |
| Tenant API | Prefer **same hostname** as SPA (`app.customer.com/api/v1` via ALB routing) |
| Tenant hosts | `app.{slug}.{brand_domain}` per [`tenant-domain-slugs`](docs/infrastructure/tenant-domain-slugs.md) |
| TLS | ACM certificates; wildcard DNS for tenant apps |
| Database | Aurora MySQL; central DB + one DB per tenant |
| Queues | Redis or SQS; run `toweros-worker` ECS service |
| Scheduler | ECS task: `php artisan schedule:run` |
| Files | S3 disk for `TOWEROS_TENANT_FILES_DISK` |
| Mail | SES or Microsoft 365 SMTP for gate/approval emails |
| SSO | Per-tenant Entra app; production redirect URI on API host |
| Bootstrap passwords | `TOWEROS_TENANT_BOOTSTRAP_EXPOSE_PASSWORD_IN_API=false` |
| MFA | `TENANT_MFA_REQUIRED` / per-tenant `mfa_required` as required |

### Frontend production (`frontend/.env`)

```env
NEXT_PUBLIC_APP_ENV=production
NEXT_PUBLIC_API_BASE_URL=https://app.customer.com/api/v1
NEXT_PUBLIC_CENTRAL_API_BASE_URL=https://console.toweros.app/api/v1
NEXT_PUBLIC_CENTRAL_DOMAINS=console.toweros.app
NEXT_PUBLIC_SOCKET_ENABLED=true   # when Soketi/Pusher is deployed
```

### Backend production highlights (`backend/.env`)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.customer.com
FRONTEND_APP_URL=https://app.customer.com
TOWEROS_TENANT_APP_URL=https://app.customer.com
CENTRAL_DOMAINS=console.toweros.app
TOWEROS_ALLOW_TENANT_ON_CENTRAL_HOST=false
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

### Deploy runbook (summary)

1. **CI:** PR → lint, test, build (see `.github/workflows/ci.yml`)  
2. **Build & push** Docker images to ECR (`toweros-api`, `toweros-web`)  
3. **Migrate:** ECS one-off task: `php artisan migrate --force` then `tenants:migrate --force`  
4. **Deploy** ECS services (API, web, worker, scheduler)  
5. **Smoke test:** `/up`, platform login, tenant login, one SSO flow  
6. **Rollback:** ECS circuit breaker; DB restore from snapshot only if needed  

### Post-deploy tenant operations

- Create tenants from **production** platform console with production `brand_domain` and DNS.  
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

**More:** [`docs/local-development-docker-guide.md`](docs/local-development-docker-guide.md#troubleshooting)

---

## Documentation index

| Document | Description |
|----------|-------------|
| [`docs/local-development-docker-guide.md`](docs/local-development-docker-guide.md) | Step-by-step Docker setup (printable checklist) |
| [`docs/infrastructure/aws-ecs-cicd.md`](docs/infrastructure/aws-ecs-cicd.md) | AWS ECS, Aurora, CI/CD |
| [`docs/infrastructure/tenant-domain-slugs.md`](docs/infrastructure/tenant-domain-slugs.md) | Hostnames per environment |
| [`docs/architecture/tenant-isolation-mysql.md`](docs/architecture/tenant-isolation-mysql.md) | Multi-tenant MySQL |
| [`docs/design-system/toweros-design-system.md`](docs/design-system/toweros-design-system.md) | UI tokens and components |
| [`docs/modules/e-approval.md`](docs/modules/e-approval.md) | E-Approval module |
| [`docs/project-one-roadmap.md`](docs/project-one-roadmap.md) | PROJECT-ONE / rollouts |
| [`.cursor/rules/toweros.mdc`](.cursor/rules/toweros.mdc) | Coding standards |
| [`.cursor/rules/uiux-theme.mdc`](.cursor/rules/uiux-theme.mdc) | UI/UX rules |

---

## Design & UX

- **Font:** Geist  
- **Style:** Operational minimalism (Azure Portal / ServiceNow-inspired)  
- **Layout:** Left sidebar, top header, module-first navigation  
- **Details:** [`docs/design-system/toweros-design-system.md`](docs/design-system/toweros-design-system.md)

---

## Host-only development

If you run PHP and Node on the host instead of the API/web containers:

1. `npm run dev:docker` — starts MySQL (+ phpMyAdmin) only  
2. Configure `backend/.env` with `DB_HOST=127.0.0.1`, `DB_PORT=3307`  
3. `npm run dev:app` — Laravel on `:8000`, Next on `:80`  
4. `php artisan key:generate`, `migrate`, `db:seed` from `backend/`

Prefer full Docker (`npm run dev`) for the least friction on Windows.

---

## License & support

Proprietary — Alliance / TowerOS. For internal setup questions, use this README and `docs/local-development-docker-guide.md` first, then check API logs: `npm run dev:logs:api`.
