# TowerOS local development — Docker guide (zero to first tenant)

Step-by-step guide for **Windows** using Docker Desktop. No local PHP or Node install required.

**Time:** ~15 minutes first run (image build + MySQL init). Later starts are ~1 minute.

---

## What you will have at the end

| Service | URL |
|---------|-----|
| Tenant + platform web | http://localhost:3001 |
| API | http://localhost:8000 |
| Platform console | http://localhost:3001/platform |
| phpMyAdmin | http://localhost:8080 |
| MySQL (host tools) | `127.0.0.1:3307` |

You will be able to log in as **platform superadmin** and **create your first tenant** (e.g. `atc.localhost`).

---

## Prerequisites

1. **Docker Desktop** installed and running (whale icon in the system tray).
2. **Git** — repo cloned, e.g. `C:\LaravelProject\TowerOS`.
3. **Node.js** (for root `npm` scripts only) — [nodejs.org](https://nodejs.org/) LTS.

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

You should see `toweros-mysql`, `toweros-api`, `toweros-web`, `toweros-phpmyadmin` running.

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
- Published rollout playbooks **v1** and **v2**
- Published policy **`towerco-default`** (v1 baseline + email notifications)
- Published policy **`towerco-full-gate-approval`** (v2, all phases gated + email notifications)
- Helper center glossary (~47 operational acronyms, same as **Sync TowerOS defaults**)
- Passport personal access client (for platform login)

---

## Part 4 — Platform login

After **`dev:fresh`**, you must sign in again. The old session in the browser is invalid (database was wiped).

1. Open **http://localhost:3001/platform/login** (or sign out if you still see the console shell)
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

1. Go to **http://localhost:3001/platform/tenants/create**  
   (or use the sidebar → **Create tenant**).

2. Fill the form:

   | Field | Example (local dev) | Notes |
   |-------|---------------------|--------|
   | **Tenant domain (host)** | `atc.localhost` or `atc.example.localhost` | Slug + brand auto-fill from hostname |
   | **Tenant ID** | *(leave blank)* | Auto-generated UUID |
   | **Slug** | `atc` | Fills automatically from domain; edit if needed |
   | **Brand domain** | `example.com` | From host (`atc.example.localhost`) or `NEXT_PUBLIC_TENANT_LOCALHOST_DEFAULT_BRAND_DOMAIN` in `frontend/.env.docker` for `{slug}.localhost` |
   | **Environment** | Local (fixed) | First tenant is always local |
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
   - **Tenant login URL** (e.g. http://atc.localhost:3001/login)

---

## Part 6 — Log in to the tenant (optional)

### Option 1 — Use `*.localhost` in the browser

1. Edit hosts file as Administrator:  
   `C:\Windows\System32\drivers\etc\hosts`

2. Add:

   ```text
   127.0.0.1 atc.localhost
   ```

3. Open the login URL from the success screen, e.g. **http://atc.localhost:3001/login**

4. Sign in with **admin@atc.localhost** and the password from step 5.

### Option 2 — Stay on `localhost:3001`

Platform-created tenants also work on the central host with tenant headers when configured. Prefer **Option 1** for normal tenant UX.

---

## Daily commands (after setup)

| Task | Command |
|------|---------|
| Start stack (background) | `docker compose --env-file .env.docker up -d` |
| Start stack (see logs) | `npm run dev` |
| Stop stack | `npm run dev:down` |
| API logs | `npm run dev:logs:api` |
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

### Port already in use

Edit `.env.docker` (e.g. `TOWEROS_WEB_PORT=3002`, `TOWEROS_API_PORT=8001`) and restart.

---

## Checklist (printable)

```text
[ ] Docker Desktop running
[ ] copy env.docker.example .env.docker
[ ] npm install
[ ] npm run dev:fresh  OR  docker compose up -d --build
[ ] npm run dev:mysql:grants
[ ] npm run dev:seed     (skip if dev:fresh)
[ ] Open http://localhost:3001/platform — login superadmin
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

## Related docs

- [Tenant isolation (MySQL)](architecture/tenant-isolation-mysql.md)
- [Tenant domain slugs](infrastructure/tenant-domain-slugs.md)
- [README — local development](../README.md)
