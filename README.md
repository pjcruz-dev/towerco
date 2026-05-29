# TowerOS

Enterprise multi-tenant telecom SaaS for tower companies (TowerCos). Built as a modular monolith with a Laravel API and Next.js tenant workspace.

**Board reference:** [`Rules/TowerOS_Board_Presentation.pdf`](Rules/TowerOS_Board_Presentation.pdf) — module names, phases, and roadmap.

---

## Technology stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Next.js 16, React 19, TypeScript |
| Database | **MySQL 8.0** — database-per-tenant (stancl/tenancy) |
| Cache / queues | Redis (local dev often uses `sync` + database cache) |
| Auth | Sanctum (tenant SPA) + Passport (platform console) |
| SSO | Microsoft Entra ID (Azure) |
| RBAC | Spatie Laravel Permission |
| Realtime | Socket.IO client (Soketi-compatible; optional in dev) |
| GIS (UI) | MapLibre GL |
| UI | Tailwind CSS 4, shadcn/ui, Geist font |

> The board deck originally specified PostgreSQL + PostGIS + TimescaleDB. The **current implementation uses MySQL** with decimal lat/lng for sites and MapLibre on the frontend. See [`docs/architecture/tenant-isolation-mysql.md`](docs/architecture/tenant-isolation-mysql.md).

---

## Platform modules (board alignment)

| Module | Category | Tenant nav | Status (early build) |
|--------|----------|------------|----------------------|
| **Foundation** | Auth, tenancy, RBAC | Platform console + settings | In progress (~42%) |
| **Sites** | Shared site registry | Sites | Read-only registry (~35%) |
| **PROJECT-ONE** | Project & QMS | PROJECT-ONE | Dashboard, projects, approvals, rollouts, field SAQ/CME (~75%, rollout production-ready) — [roadmap](docs/project-one-roadmap.md) |
| **TOWER-ONE** | Tower management | TOWER-ONE | Dashboard + tower registry (~28%) |
| **FIBER-ONE** | Network / GIS | FIBER-ONE | Dashboard + route registry (~22%) |
| **ASSET-ONE** | Asset & logistics | ASSET-ONE | Dashboard + asset registry (~25%) |
| **GIS** | Operational map | GIS | MapLibre shell (~15%) |

Modules after ASSET-ONE (TASK-ONE, VENDOR-ONE, SITE-ONE, ADMIN-ONE, AUDIT-ONE, FLEET-ONE, ACCESS-ONE) are on the roadmap but not yet in the tenant shell.

---

## Repository layout

```
TowerOS/
├── backend/          Laravel API (central + tenant routes)
├── frontend/         Next.js tenant workspace + platform console
├── docs/             Architecture and design system
├── Rules/            Board presentation (source of truth for module IA)
├── docker-compose.yml   MySQL, API, Web, phpMyAdmin
├── dev.cmd              Alias for `npm run dev` (full Docker stack)
└── dev-help.cmd         Command reference
```

---

## Local development (Windows)

Requires [Docker Desktop](https://www.docker.com/products/docker-desktop/). The default workflow runs **API, Web, MySQL, and phpMyAdmin** in containers — no local PHP/Node install required.

### Daily workflow

| Terminal | Command | Purpose |
|----------|---------|---------|
| 1 | `npm run dev` or `dev.cmd` | Build/start full stack (API `:8000`, Web `:3001`, MySQL `:3307`) |
| 2 | `npm run dev:logs:api` | Follow API container logs |

Stop everything: `npm run dev:down` or `dev-stop.cmd`.

**Legacy (PHP/Node on host, MySQL in Docker):** `dev.cmd host` or `npm run dev:host`.

### URLs

| Service | URL |
|---------|-----|
| Tenant web | http://localhost:3001 |
| API | http://127.0.0.1:8000 |
| Platform console | http://localhost:3001/platform |
| **phpMyAdmin** (MySQL web UI) | http://localhost:8080 |

### Database (MySQL)

**Web UI (phpMyAdmin):** http://localhost:8080 — login with `root` / `toweros`

**Direct connection** (CLI or desktop clients):

| Setting | Value |
|---------|--------|
| Host | `127.0.0.1` |
| Port | `3307` |
| Database | `toweros` |
| User | `root` |
| Password | `toweros` |

- Quick SQL CLI: `dev-db.cmd`
- Connection info only: `dev-db.cmd info`
- Change phpMyAdmin port: `TOWEROS_PHPMYADMIN_PORT` in `.env.docker`

Tenant databases are provisioned automatically when you create a tenant from the platform console (`tenant_*` prefix).

### Logs

| Command | When to use |
|---------|-------------|
| `dev-logs.cmd` or `dev-logs.cmd api` | API / Laravel errors |
| `dev-logs.cmd mysql` | MySQL container won't start |
| `dev-logs.cmd all` | Both streams in one window |
| `dev-help.cmd` | Full command reference |

### First-time setup (Docker)

**Full step-by-step guide (zero → first tenant):** [`docs/local-development-docker-guide.md`](docs/local-development-docker-guide.md)

Quick start:

```bat
copy env.docker.example .env.docker
npm install
npm run dev:fresh
```

Or normal start: `npm run dev` then `npm run dev:mysql:grants` and `npm run dev:seed`.

Login: `superadmin@toweros.local` / `123123123` — create tenant at http://localhost:3001/platform/tenants/create

App config for containers: `backend/.env.docker` and `frontend/.env.docker`.

### Without Docker

Install PHP 8.3, Composer, Node 22+, and MySQL 8 locally, then `npm run dev:host` after configuring `backend/.env`.

---

## Documentation

| Document | Description |
|----------|-------------|
| [`docs/local-development-docker-guide.md`](docs/local-development-docker-guide.md) | Docker setup → platform login → create tenant |
| [`docs/architecture/tenant-isolation-mysql.md`](docs/architecture/tenant-isolation-mysql.md) | Multi-tenant MySQL strategy |
| [`docs/design-system/toweros-design-system.md`](docs/design-system/toweros-design-system.md) | Colors, typography, components |
| [`.cursor/rules/uiux-theme.mdc`](.cursor/rules/uiux-theme.mdc) | UI/UX rules for AI and contributors |
| [`.cursor/rules/toweros.mdc`](.cursor/rules/toweros.mdc) | Architecture and coding standards |

---

## Design & UX

- **Font:** Geist (sans + mono)
- **Page titles:** 24px (`text-2xl`), `font-semibold` — operational calm, low visual weight
- **Style:** Azure Portal / ServiceNow-inspired operational minimalism
- **Navigation:** Left sidebar, top header, module-first hierarchy (see board deck §2)
