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
| Cache / queues | Redis (cache, sessions, permission cache) — local `toweros-redis` container + production ElastiCache; queues `sync` locally, `redis`/SQS in prod |
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

TowerOS supports two production models:

| Model | Best for | Doc |
|-------|----------|-----|
| **A — Linux EC2 + RDS** (your proposal) | First customer go-live, fixed monthly cost, simpler ops | [below](#a-linux-ec2--rds-your-aws-proposal) |
| **B — ECS Fargate + Aurora** | Multi-tenant scale, autoscaling, zero-downtime deploys | [`docs/infrastructure/aws-ecs-cicd.md`](docs/infrastructure/aws-ecs-cicd.md) |

---

### Is TowerOS ready for production?

**Application:** Yes for the modules you have been testing (Project-One, Sites, Documents, Document register, E-Approval, Ticketing). Priority automated tests pass; run your staging manual checklist before cutover.

**Operations:** Production is ready when **you** complete the checklist below — not only when code is merged.

| Area | Status | Before go-live |
|------|--------|----------------|
| Staging validation | Your checklist on `staging.*` | Complete smoke + module flows |
| Secrets & TLS | Required | `APP_KEY`, DB passwords, OAuth secrets in a vault (not git) |
| HTTPS everywhere | Required | ACM cert + Route 53 (or ALB) |
| Database | Required | RDS MySQL 8; app user can `CREATE DATABASE` (tenant provisioning) |
| Redis | **Required** | Queues + cache (not in your AWS slide — see gaps below) |
| Queue worker | **Required** | `php artisan queue:work` always running |
| Scheduler | **Required** | Cron every minute: `schedule:run` |
| File storage | Recommended | S3 disk for tenant uploads (not local EC2 disk) |
| Backups | Required | RDS snapshots + S3 versioning (your 30-day AWS Backup fits) |
| Monitoring | Required | CloudWatch alarms (5xx, disk, RDS CPU, queue depth) |
| Mail | Required | SES or Microsoft 365 SMTP for approvals / gate emails |
| SSO | Per tenant | Entra redirect URI on production API host |

---

### A. Linux EC2 + RDS (your AWS proposal)

Your **1-year AWS subscription** slide maps well to TowerOS with a few additions.

#### Spec alignment

| Proposed resource | TowerOS use | Verdict |
|-------------------|-------------|---------|
| **EC2 t3.large** (2 vCPU, 8 GB, 50 GB) | Docker: API + Next.js web + Redis + Soketi + queue worker | **OK** for first production tenant (~hundreds of users). Plan **16 GB** if you run heavy imports + Next.js build on the same box. |
| **EBS 100 GB gp3** | Docker images, logs, temp build | **OK** |
| **RDS MySQL db.t3.medium Multi-AZ** (2 vCPU, 4 GB, 50 GB) | Central DB `toweros` + one DB per tenant (`tenant<uuid>`) | **Good** — enable automated backups; grant `CREATE` to app user |
| **S3 50 GB** | Tenant documents, exports, presigned uploads | **Required** — set `TOWEROS_TENANT_FILES_DISK=s3` |
| **CloudFront** | Next.js static assets, file downloads | **Recommended** |
| **Route 53** | `console.yourdomain.com`, `app.customer.com`, wildcards | **Required** |
| **CloudWatch** | API/web logs, RDS metrics, alarms | **Required** |
| **AWS Backup 30-day** | RDS + EBS | **Good** |

#### Gaps to add (not on your slide)

| Missing | Why TowerOS needs it |
|---------|-------------------|
| **Redis** | Production `.env` uses `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`. Add **ElastiCache cache.t3.micro** *or* run **Redis in Docker** on the EC2 (acceptable for first go-live). |
| **Queue worker process** | Without it: no approval emails, no gate escalation, no async jobs. |
| **Laravel scheduler** | Gate SLA, document expiry, rollout recalc — needs cron every minute. |
| **Reverse proxy + TLS** | Terminate HTTPS on **ALB** or **Nginx/Caddy** on the EC2; do not expose `:8000` publicly. |
| **Soketi / Pusher** (optional) | Realtime notifications; can enable later with `NEXT_PUBLIC_SOCKET_ENABLED=true`. |

#### Suggested production topology

```text
Internet
  → Route 53
  → CloudFront (optional, static + downloads)
  → ALB or Nginx :443
       ├─ /api/*  → Laravel API :8000 (Docker)
       └─ /*      → Next.js :80 (Docker)
  → RDS MySQL (private subnet)
  → S3 (tenant files)
  → Redis (ElastiCache or Docker on EC2)
```

**DNS pattern** (see [`tenant-domain-slugs`](docs/infrastructure/tenant-domain-slugs.md)):

| Host | Role |
|------|------|
| `console.yourdomain.com` | Platform superadmin (`CENTRAL_DOMAINS`) |
| `app.customer.com` or `staging.customer.com` | Tenant SPA + API (`/api/v1` on same host) |
| `*.customer.com` | Optional per-tenant hosts |

---

### B. Deploy to Linux EC2 — step by step

Target OS: **Amazon Linux 2023** or **Ubuntu 22.04 LTS**. All commands as `ubuntu` or `ec2-user` with `sudo` where noted.

#### 1. Provision AWS (console or IaC)

1. **VPC** with public + private subnets (RDS in private subnet).
2. **RDS MySQL 8.0** — Multi-AZ as proposed; database name `toweros`; note endpoint hostname.
3. **EC2 t3.large** — Amazon Linux 2023; security group: `22` (your IP only), `80`/`443` from ALB or `0.0.0.0/0` if using on-box Nginx.
4. **S3 bucket** — e.g. `toweros-prod-files-<account-id>`; block public access; IAM role for EC2 with `s3:PutObject/GetObject`.
5. **Route 53** — hosted zone for your domain.
6. **(Recommended)** ElastiCache Redis `cache.t3.micro` in same VPC **or** skip and use Redis container on EC2.
7. **IAM role** attached to EC2: S3 access, SES send (if using SES), CloudWatch agent.

**RDS app user** (run once as master user):

```sql
CREATE USER 'toweros'@'%' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON `toweros`.* TO 'toweros'@'%';
GRANT CREATE ON *.* TO 'toweros'@'%';
FLUSH PRIVILEGES;
```

`CREATE` is required so new tenants get `tenant<uuid>` databases automatically.

#### 2. Install Docker on the EC2

**Amazon Linux 2023:**

```bash
sudo dnf update -y
sudo dnf install -y docker git
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
# Log out and back in, then:
docker compose version || sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose && sudo chmod +x /usr/local/bin/docker-compose
```

**Ubuntu 22.04:**

```bash
sudo apt update && sudo apt install -y docker.io docker-compose-plugin git
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

#### 3. Clone TowerOS and configure env

```bash
sudo mkdir -p /opt/toweros && sudo chown $USER:$USER /opt/toweros
cd /opt/toweros
git clone <your-repo-url> .
```

**Root Docker env** — create `/opt/toweros/.env.docker`:

```env
TOWEROS_MYSQL_PORT=3307
MYSQL_ROOT_PASSWORD=unused-local-only
MYSQL_DATABASE=toweros
MYSQL_USER=toweros
MYSQL_PASSWORD=unused-local-only
TOWEROS_API_PORT=8000
TOWEROS_WEB_PORT=80
TOWEROS_REDIS_PORT=6379
TOWEROS_DOCKER_AUTO_MIGRATE=0
TOWEROS_DOCKER_MIGRATE_TENANTS=0
TOWEROS_API_WORKERS=4
TOWEROS_API_MEM_LIMIT=2g
TOWEROS_WEB_MEM_LIMIT=3g
TOWEROS_WEB_MODE=prod
```

**Backend** — copy and edit production env:

```bash
cp backend/.env.production.example backend/.env
```

Edit `backend/.env` (minimum):

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...          # php artisan key:generate --show (once, store safely)
APP_URL=https://app.customer.com
FRONTEND_APP_URL=https://app.customer.com
TOWEROS_TENANT_APP_URL=https://app.customer.com

DB_HOST=<rds-endpoint.region.rds.amazonaws.com>
DB_PORT=3306
CENTRAL_DB_PORT=3306
DB_DATABASE=toweros
DB_USERNAME=toweros
DB_PASSWORD=<rds-password>

CENTRAL_DOMAINS=console.yourdomain.com
TOWEROS_ALLOW_TENANT_ON_CENTRAL_HOST=false

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379

# S3 tenant files
FILESYSTEM_DISK=s3
AWS_BUCKET=toweros-prod-files-<account-id>
AWS_DEFAULT_REGION=ap-southeast-1
TOWEROS_TENANT_FILES_DISK=s3

MAIL_MAILER=ses
TOWEROS_TENANT_BOOTSTRAP_EXPOSE_PASSWORD_IN_API=false
TOWEROS_TENANT_DEFAULT_MFA_REQUIRED=true
```

**Frontend** — create `frontend/.env.docker`:

```env
NEXT_PUBLIC_APP_ENV=production
NEXT_PUBLIC_API_BASE_URL=https://app.customer.com/api/v1
NEXT_PUBLIC_CENTRAL_API_BASE_URL=https://console.yourdomain.com/api/v1
NEXT_PUBLIC_CENTRAL_DOMAINS=console.yourdomain.com
NEXT_PUBLIC_SOCKET_ENABLED=false
TOWEROS_WEB_MODE=prod
```

> Do **not** put `APP_KEY` in `.env.docker` — only in `backend/.env` (see [Authentication & security](#authentication--security)).

#### 4. Point Compose at RDS (not container MySQL)

Override DB host for the API container in `.env.docker` or shell export:

```bash
export DB_HOST=<rds-endpoint>
export DB_USERNAME=toweros
export DB_PASSWORD=<rds-password>
```

Start **without** the local `mysql` service — API, web, redis only:

```bash
cd /opt/toweros
docker compose --env-file .env.docker up -d --build redis api
docker compose --env-file .env.docker --profile web up -d --build web
```

First boot on RDS:

```bash
docker compose --env-file .env.docker exec api php artisan migrate --force
docker compose --env-file .env.docker exec api php artisan db:seed --force
docker compose --env-file .env.docker exec api php artisan passport:client --personal --no-interaction
```

#### 5. Queue worker (required)

Create `/etc/systemd/system/toweros-worker.service`:

```ini
[Unit]
Description=TowerOS queue worker
After=docker.service
Requires=docker.service

[Service]
Restart=always
WorkingDirectory=/opt/toweros
ExecStart=/usr/bin/docker compose --env-file .env.docker exec -T api php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
ExecStop=/usr/bin/docker compose --env-file .env.docker exec -T api php artisan queue:restart

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now toweros-worker
```

#### 6. Scheduler (required)

```bash
sudo crontab -e
```

Add:

```cron
* * * * * cd /opt/toweros && docker compose --env-file .env.docker exec -T api php artisan schedule:run >> /var/log/toweros-scheduler.log 2>&1
```

#### 7. HTTPS reverse proxy

**Option A — Application Load Balancer (recommended):** Target groups → EC2:80 (web) and EC2:8000 (API) or single Nginx on EC2 routing `/api` → API. ACM certificate on ALB.

**Option B — Nginx on EC2** terminating TLS with ACM-exported cert or Let's Encrypt:

```nginx
server {
    listen 443 ssl http2;
    server_name app.customer.com;

    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

Point Route 53 `A`/`CNAME` records to ALB or EC2 Elastic IP.

#### 8. Post-deploy smoke test

```bash
curl -fsS https://app.customer.com/api/v1/health || curl -fsS https://app.customer.com/up
```

| Check | URL / action |
|-------|----------------|
| Platform login | `https://console.yourdomain.com/platform/login` |
| Create tenant | Platform → Tenants → Create (save bootstrap password) |
| Tenant login | Customer hostname from provisioning |
| Microsoft SSO | Tenant → Sign-in & security → Validate |
| File upload | Site binder or document register |
| Queue | Trigger approval → confirm email / notification |

#### 9. Release upgrades

```bash
cd /opt/toweros
git pull
docker compose --env-file .env.docker build api web
docker compose --env-file .env.docker up -d api web
docker compose --env-file .env.docker exec api php artisan toweros:migrate --force
docker compose --env-file .env.docker exec api php artisan config:cache
docker compose --env-file .env.docker exec api php artisan queue:restart
sudo systemctl restart toweros-worker
```

Clear Next.js cache if routes 404 after upgrade:

```bash
docker compose --env-file .env.docker exec web sh -c 'rm -rf .next && npm run build'
docker compose --env-file .env.docker restart web
```

---

### C. ECS Fargate (scale path)

Target architecture for multi-tenant scale: **ECS Fargate**, **Aurora MySQL 8**, **ElastiCache Redis**, **ALB + WAF**, **S3**, **Secrets Manager**.

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

## Host-only development (fastest on Windows)

Running the API on host PHP avoids ~1.5–2 s/request of Docker-on-Windows overhead (~6× faster). Infra stays in Docker.

1. Start infra only: `docker compose --env-file .env.docker up -d mysql redis soketi`
2. Configure `backend/.env`: `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `CENTRAL_DB_PORT=3307`, `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379`
3. If you previously ran the Docker API, clear its baked config: `cd backend && php artisan config:clear`
4. Terminal 1: `cd backend && php artisan serve --host=127.0.0.1 --port=8000`
5. Terminal 2: `cd frontend && npm run dev -- -p 80`

Switch back to Docker API: stop the host `php artisan serve`, then `docker compose --env-file .env.docker up -d api` (compose overrides `DB_HOST=mysql` automatically).

Prefer full Docker (`npm run dev`) for the least setup; prefer host mode for the fastest requests. Full step-by-step: [`docs/local-development-docker-guide.md`](docs/local-development-docker-guide.md#run-modes--performance-updated-jul-2026).

---

## License & support

Proprietary — Alliance / TowerOS. For internal setup questions, use this README and `docs/local-development-docker-guide.md` first, then check API logs: `npm run dev:logs:api`.
