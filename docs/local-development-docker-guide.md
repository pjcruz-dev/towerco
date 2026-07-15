# TowerOS local development — Docker guide (zero to first tenant)

Step-by-step guide for **Windows** using Docker Desktop. No local PHP or Node install required.

**Time:** ~15 minutes first run (image build + MySQL init). Later starts are ~1 minute.

---

## What you will have at the end

| Service | URL |
|---------|-----|
| Tenant + platform web | http://localhost |
| API | http://localhost:8000 |
| Platform console | http://localhost/platform |
| phpMyAdmin | http://localhost:8080 |
| MySQL (host tools) | `127.0.0.1:3307` |
| Redis (cache/session) | `127.0.0.1:6379` |

You will be able to log in as **platform superadmin** and **create your first tenant** (e.g. `atc.localhost`).

**E-Approval:** Forms and approvals run inside this stack only (`/e-approval` in the main Next.js app). The old standalone formbuilder was decommissioned (P4) and lives under `legacy/atcformbuiilder/` for reference — **do not** run a second Node app or its `docker-compose.yml` for TowerOS development.

---

## Run modes & performance (updated Jul 2026)

The backend now ships with several performance optimizations. Read this before choosing how to run locally.

### What changed

- **Redis container added** (`toweros-redis`, `127.0.0.1:6379`). App **cache**, **sessions**, and the **permission cache** now use Redis instead of MySQL. Driver is `predis` (pure PHP — no PHP extension needed).
- **4 API workers** behind nginx in Docker (`TOWEROS_API_WORKERS=4`). The several API calls each page fires now run in **parallel** instead of queuing behind one worker — this was the main cause of slow page loads.
- **OPcache + JIT** tuned (`backend/docker/opcache.ini`), applied at container boot.
- **Config + event cache at boot** in Docker, controlled by `TOWEROS_API_OPTIMIZE` (default `1`).
  ⚠️ **With this on, after editing `config/*` or `.env` you must restart the API:**
  `docker compose --env-file .env.docker restart api` — or set `TOWEROS_API_OPTIMIZE=0` in `.env.docker` while iterating. (Application/PHP code and routes stay hot via the bind mount.)

### Which mode should I use?

| Mode | API runtime | Per-request latency¹ | When |
|------|-------------|----------------------|------|
| **Full Docker** | Docker, 4 workers | ~1.5–2 s | Simplest; no host PHP needed |
| **Host API + host web** ⭐ | Host PHP `artisan serve` | **~0.3 s (~6× faster)** | Fastest local dev on Windows |

¹ Windows Docker Desktop adds ~1.5–2 s of per-request overhead (bind-mount + networking) that no in-container config removes. Running the API on host PHP avoids it entirely.

### Fastest local mode — host API + host web

Requires **PHP 8.3 + Composer** and **Node.js** installed on Windows (`cd backend && composer install`, `cd frontend && npm ci` once).

1. Start **infra only** (MySQL, Redis, Soketi) in Docker:

   ```bat
   docker compose --env-file .env.docker up -d mysql redis soketi
   ```

2. Point `backend/.env` at the **published** container ports (Docker mode still works — `docker-compose.yml` overrides these with `mysql:3306` for the API container):

   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3307
   CENTRAL_DB_PORT=3307
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   ```

3. If you previously ran the **Docker** API, clear its cached config (it bakes in container hostnames like `mysql`/`redis`):

   ```bat
   cd backend && php artisan config:clear
   ```

4. Terminal 1 — API:

   ```bat
   cd backend && php artisan serve --host=127.0.0.1 --port=8000
   ```

5. Terminal 2 — web:

   ```bat
   cd frontend && npm run dev -- -p 80
   ```

Open http://localhost. **Switch back to Docker API:** stop the host `php artisan serve` (frees port 8000), then `docker compose --env-file .env.docker up -d api`.

---

## Prerequisites

1. **Podman Desktop** or **Docker Desktop** installed and running.  
   Podman: [docs/podman-desktop-setup.md](podman-desktop-setup.md) — create/start a machine under **Resources** first.  
   Docker: [nodejs.org](https://nodejs.org/) LTS for root `npm` scripts only.
2. **Git** — repo cloned, e.g. `C:\LaravelProject\TowerOS`.

---

## Part 1 — One-time project setup

Open **PowerShell** or **Git Bash** in the repo root (`TowerOS`).

### Step 1 — Create Docker env file

```bat
copy env.docker.example .env.docker
```

### Step 2 — Install root npm dependencies

```bat
npm install
```

This installs `concurrently` and wires up `npm run dev`, `dev:fresh`, etc.

---

## Part 2 — Start the stack (choose A or B)

### Option A — Clean start (recommended if you had errors before)

Wipes MySQL and recreates **superadmin + default playbook**.

```bat
npm run dev:fresh
```

When prompted, type exactly **`FRESH`** (all caps, not `1` or `yes`) and press Enter.

Wait until you see **Done** and login hints. Skip to [Part 4 — Platform login](#part-4--platform-login).

### Option B — Normal start (keep existing MySQL data)

```bat
npm run dev
```

First run **builds Docker images** (several minutes). Later runs are faster.

**Important:** Use **detached mode** if you do not want Ctrl+C to stop everything:

```bat
docker compose --env-file .env.docker up -d --build
```

Wait until containers are up:

```bat
docker compose --env-file .env.docker ps
```

You should see `toweros-mysql`, `toweros-redis`, `toweros-api`, `toweros-soketi` running (plus `toweros-web` in full-Docker mode and `toweros-phpmyadmin` with the `tools` profile).

---

## Part 3 — MySQL grants + platform seed (first time only)

Tenant databases are created as `tenant<uuid>`. The app MySQL user needs permission to `CREATE DATABASE`.

### Step 3 — Apply MySQL grants (once per MySQL volume)

```bat
npm run dev:mysql:grants
```

Expected: `Done.`

If this fails, ensure MySQL is running: `docker compose --env-file .env.docker up -d mysql`

### Step 4 — Seed platform superadmin (if you did not use `dev:fresh`)

```bat
npm run dev:seed
```

This creates:

- Platform user **superadmin@toweros.local**
- **Optional** auto dev tenant when `TOWEROS_DEV_DEFAULT_TENANT_DOMAIN` is set in `backend/.env.docker` (any hostname)
- Published rollout playbooks **v1** and **v2**
- Published policy **`towerco-default`** (v1 baseline + email notifications)
- Published policy **`towerco-full-gate-approval`** (v2, all phases gated + email notifications)
- Helper center glossary (~47 operational acronyms, same as **Sync TowerOS defaults**)
- Passport personal access client (for platform login)

---

## Part 4 — Platform login

After **`dev:fresh`**, you must sign in again. The old session in the browser is invalid (database was wiped).

1. Open **http://localhost/platform/login** (or sign out if you still see the console shell)
2. If you see **Unauthenticated** on any page, clear **localStorage** key `toweros.platform.session` and log in again.
3. Sign in:

   | Field | Value |
   |-------|--------|
   | Email | `superadmin@toweros.local` |
   | Password | `123123123` |

   (Configured in `backend/.env.docker` — change for real deployments only.)

3. You should see the **Tenant directory** (may be empty).

---

## Part 5 — Create your first tenant

1. Go to **http://localhost/platform/tenants/create**  
   (or use the sidebar → **Create tenant**).

2. Fill the form:

   | Field | Example (local dev) | Notes |
   |-------|---------------------|--------|
   | **Environment** | Local, Test, Staging, or Production | Pick the environment you are provisioning now |
   | **Slug** | `atc` | Org identifier — shared across linked environments |
   | **Brand domain** | `example.com` | Customer domain; drives deployed hostnames |
   | **Tenant hostname** | Auto-filled (e.g. `atc.localhost`) | Generated from env + slug + brand; override if needed |
   | **TCO sequence prefix** | `A` | Default is fine |
   | **Rollout playbook** | Latest published | Recommended |
   | **Seed demo dataset** | Off | Only for demo/UAT |

3. Click **Create tenant**.

4. **Wait 1–2 minutes** — do not close the tab. Provisioning:

   - Creates central tenant record
   - Creates MySQL database `tenant<uuid>`
   - Runs **tenant migrations**
   - Assigns rollout policy bundle (gates + email notifications)
   - Syncs playbook to tenant DB
   - Seeds PH holidays
   - Creates tenant admin `admin@atc.localhost`

5. On success, save:

   - **Tenant ID**
   - **Initial admin email / password** (shown once)
   - **Tenant login URL** (e.g. http://atc.localhost/login)

---

## Part 6 — Log in to the tenant (optional)

### Option 1 — Use `*.localhost` in the browser

1. Edit hosts file as Administrator:  
   `C:\Windows\System32\drivers\etc\hosts`

2. Add:

   ```text
   127.0.0.1 atc.localhost
   ```

3. Open the login URL from the success screen, e.g. **http://atc.localhost/login**

4. Sign in with **admin@atc.localhost** and the password from step 5.

### Option 2 — Stay on `localhost`

Platform-created tenants also work on the central host with tenant headers when configured. Prefer **Option 1** for normal tenant UX.

---

## Daily commands (after setup)

| Task | Command |
|------|---------|
| Start stack (background) | `docker compose --env-file .env.docker up -d` |
| Start stack (see logs) | `npm run dev` |
| Stop stack | `npm run dev:down` |
| API logs | `npm run dev:logs:api` |
| Restart API after `config/*` or `.env` edit (config cache on) | `docker compose --env-file .env.docker restart api` |
| Fastest local mode (host API + web) | see [Run modes & performance](#run-modes--performance-updated-jul-2026) |
| Full reset (local only) | `npm run dev:fresh` |
| Remove tenants only (keep playbooks) | `docker compose --env-file .env.docker exec api php artisan toweros:dev-reset --tenants-only --force` |

---

## Troubleshooting

### `exec ... entrypoint: no such file or directory`

Rebuild images:

```bat
docker compose --env-file .env.docker up -d --build
```

### `Access denied` creating `tenant<uuid>` database

```bat
npm run dev:mysql:grants
```

Then create the tenant again.

### `Database tenant<uuid> does not exist`

Orphan tenant row without a database:

```bat
docker compose --env-file .env.docker exec api php artisan toweros:repair-tenant-databases
```

- To **remove** orphans: add `--delete-orphans --force`
- To **create** missing DBs: add `--create` (after grants)

Or run a full fresh reset: `npm run dev:fresh`

### `Key file oauth-public.key permissions are not correct`

Restart API after pulling latest code:

```bat
docker compose --env-file .env.docker restart api
```

### Provisioning failed / timeout

Watch API logs while creating:

```bat
npm run dev:logs:api
```

### E-Approval import: “File upload fields require a Professional or Enterprise plan”

Tenant `plan_tier` is **starter** by default in production; local new tenants default to **professional**.

```bat
docker compose --env-file .env.docker exec api php artisan tenants:set-plan-tier professional --domain=atc.localhost
```

Or set plan tier in **Platform → Tenants** for that organization.

### Tenant UI: “Could not load submissions / notifications” on every page

The footer **Database connected** / **Cache active** labels are static — they do not reflect tenant API health.

Typical causes after `npm run dev:fresh`, `toweros:dev-reset`, or a wiped MySQL volume:

1. **No tenant registered for your hostname** — API returns `Tenant domain not found.`  
   Check: `docker compose --env-file .env.docker exec api php artisan tenants:list`  
   If empty, create the tenant again at **http://localhost/platform/tenants/create** (use the same hostname you open in the browser, e.g. `atc.localhost`).

2. **Stale browser session** — localStorage still has tokens for a deleted tenant.  
   Sign out, hard refresh, or clear site data for `atc.localhost`, then sign in with the **new** admin password from provisioning.

3. **Passport keys reset** — after a full fresh reset, old access tokens are invalid. Sign in again.

4. **Pending tenant migrations** — after `git pull`:

   ```bat
   docker compose --env-file .env.docker exec api php artisan toweros:migrate
   ```

After fixing, retry the page; the UI now shows the API message (e.g. tenant domain not found) and a **Retry** button.

### `toweros-web` keeps restarting

Usually **memory pressure** during Turbopack compiles (first page load can take 20–40s and spike RAM).

**Quick fix (recommended):** run Next.js on the host, not in Docker:

```bat
docker stop toweros-web
npm run dev:hybrid
```

**Host API + host Next.js (your setup):** start only MySQL + Soketi in Docker:

```bat
npm run dev:docker:infra
```

Then in separate terminals:

```bat
cd backend && php artisan serve --host=127.0.0.1 --port=8000
cd frontend && npm run dev -- -p 80
```

Ensure `backend/.env` uses `DB_HOST=127.0.0.1`, `DB_PORT=3307`, and Soketi `PUSHER_HOST=127.0.0.1`, `PUSHER_PORT=6001` (see `backend/.env.example`).

Requires Node.js on Windows (`cd frontend && npm ci` once).

**Or stay full Docker:**

1. Set in `.env.docker`: `TOWEROS_WEB_MEM_LIMIT=4g` and `NODE_OPTIONS=--max-old-space-size=1536`
2. **Docker Desktop → Settings → Resources → Memory:** set to **10 GB** if you can
3. Stop extras you do not need: `docker stop toweros-phpmyadmin mailpit`
4. Recreate web: `docker compose --env-file .env.docker up -d --force-recreate web`

Polling env vars (`WATCHPACK_POLLING`) are set in `docker-compose.yml` for Windows volume stability.

---

TowerOS **local** Docker is tuned for laptops (~8 GB RAM). **AWS production** uses ECS/Fargate with separate sizing — do not mirror local dev memory limits in prod.

### Why `toweros-web` uses so much RAM

| Mode | Typical web RAM | Hot reload | Best for |
|------|-----------------|------------|----------|
| **`TOWEROS_WEB_MODE=dev`** (Turbopack) | **3–5 GB** | Yes | Active UI work with **frontend on host** |
| **`TOWEROS_WEB_MODE=prod`** (`next start`) | **300–600 MB** | No — rebuild after changes | **Default full Docker stack** |

If Docker shows **web ~5 GB** and **CPU ~180%**, you are almost certainly in **dev mode inside Docker**. That is the main cause of host memory pressure.

### Recommended setups (pick one)

#### 1. Full Docker, low RAM — **recommended default**

In `.env.docker` (merge from `env.docker.example` if missing):

```env
TOWEROS_WEB_MODE=prod
TOWEROS_WEB_MEM_LIMIT=1536m
TOWEROS_API_WORKERS=4
TOWEROS_API_MEM_LIMIT=1g
TOWEROS_MYSQL_MEM_LIMIT=768m
TOWEROS_REDIS_MEM_LIMIT=256m
```

> `TOWEROS_API_WORKERS=4` enables concurrent API requests (each worker adds ~150–250 MB). Drop to `1` and `TOWEROS_API_MEM_LIMIT=512m` if you are very RAM-constrained and don't mind requests serializing.

Restart:

```bat
docker compose --env-file .env.docker down
docker compose --env-file .env.docker up -d --build
```

After **frontend** code changes:

```bat
npm run dev:web:build
```

Or force rebuild on start: `TOWEROS_WEB_FORCE_BUILD=1` in `.env.docker`.

**Trade-off:** no hot reload; stable RAM ~2 GB total for mysql + api + web + soketi.

#### 2. Hybrid — **best for daily UI development**

API + MySQL + Soketi in Docker; **Next.js dev on Windows** (fast HMR, Docker web container stopped):

```bat
npm run dev:hybrid
```

Requires **Node.js** on the host (`cd frontend && npm ci` once). API stays in Docker — no local PHP needed.

Stop the Docker web container if it is still running:

```bat
docker stop toweros-web
```

#### 3. Full dev in Docker — only on **16 GB+** machines

```env
TOWEROS_WEB_MODE=dev
TOWEROS_WEB_MEM_LIMIT=3g
NODE_OPTIONS=--max-old-space-size=2048
```

Also set **Docker Desktop → Settings → Resources → Memory** to **10 GB+**.

### Optional savings

| Action | Saves |
|--------|--------|
| Do not use `COMPOSE_PROFILES=tools` (phpMyAdmin) | ~250 MB |
| Stop **mailpit** if not testing email | ~20 MB |
| `docker stop toweros-web` + `npm run dev:hybrid` | **~4 GB** |

### Docker Desktop settings

- **Memory:** 8 GB minimum for full stack; **10 GB** if using `TOWEROS_WEB_MODE=dev` in Docker.
- **WSL2:** Give the WSL distro enough RAM in `.wslconfig` if the host feels starved.

### Expected steady-state RAM (prod mode, no phpMyAdmin)

| Container | Approx. |
|-----------|---------|
| mysql | 400–800 MB |
| redis | 5–20 MB |
| api | 120–400 MB (higher with 4 workers) |
| web (prod) | 300–600 MB |
| soketi | 15–30 MB |
| **Total** | **~1–1.8 GB** |

This aligns with how you will run on AWS: **built Next.js + Laravel API** — not Turbopack dev server.

---

## Checklist (printable)

```text
[ ] Docker Desktop running
[ ] copy env.docker.example .env.docker
[ ] npm install
[ ] npm run dev:fresh  OR  docker compose up -d --build
[ ] npm run dev:mysql:grants
[ ] npm run dev:seed     (skip if dev:fresh)
[ ] Open http://localhost/platform — login superadmin
[ ] Create tenant at /platform/tenants/create
[ ] Save admin password + login URL
[ ] (Optional) hosts file: 127.0.0.1 <slug>.localhost
[ ] Tenant login works
```

---

## What happens under the hood (tenant create)

```text
Platform UI  →  POST /api/v1/platform/tenants
              →  TenantOnboardingService
              →  Central: tenants, domains, playbook binding, policy bundle
              →  MySQL: CREATE DATABASE tenant<uuid>
              →  tenants:migrate (tenant migrations)
              →  Sync playbook + holidays + admin user
```

You do **not** run `php artisan tenants:migrate` manually for new tenants — provisioning does it.

After **git pull** with new migrations, run once:

```bat
docker compose --env-file .env.docker exec api php artisan toweros:migrate
```

---

## E-Approval (tenant module)

After tenant login with a user that has E-Approval permissions (e.g. `tenant_admin`):

| Page | URL |
|------|-----|
| Dashboard | http://localhost/e-approval |
| Forms | http://localhost/e-approval/forms |
| Submissions | http://localhost/e-approval/submissions |
| Approvals | http://localhost/e-approval/approvals |
| Settings (admin) | http://localhost/e-approval/settings |

Apply tenant migrations after pull:

```bat
docker compose --env-file .env.docker exec api php artisan toweros:migrate
```

Module docs: [docs/modules/e-approval.md](modules/e-approval.md)

---

## Related docs

- [Podman Desktop setup](podman-desktop-setup.md)
- [Tenant isolation (MySQL)](architecture/tenant-isolation-mysql.md)
- [Tenant domain slugs](infrastructure/tenant-domain-slugs.md)
- [E-Approval module](modules/e-approval.md)
- [README — local development](../README.md)
