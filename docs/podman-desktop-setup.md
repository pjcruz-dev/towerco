# TowerOS — Podman Desktop (Windows)

Use Podman Desktop instead of Docker Desktop for local MySQL, Soketi, and optional API/web containers.

## 1. Install and start the engine

1. Install [Podman Desktop](https://podman-desktop.io/downloads/windows).
2. Open Podman Desktop → **Resources** (or follow the “No Container Engine” prompt).
3. **Create** a Podman machine if none exists:
   - **CPUs:** 4+
   - **Memory:** **10 GB** recommended (Next.js in a container needs headroom)
   - **Disk:** 50 GB+
4. Click **Start** on the machine until status is **Running**.

CLI check (PowerShell or Git Bash):

```powershell
podman info
podman machine list
```

Both should succeed without errors.

## 2. Configure TowerOS

In repo root `.env.docker`:

```env
TOWEROS_CONTAINER_CLI=podman
```

Other settings stay the same (`TOWEROS_MYSQL_PORT=3307`, etc.). Copy from `env.docker.example` if needed:

```powershell
copy env.docker.example .env.docker
```

## 3. Start stack (default — web on host)

**Terminal 1** — MySQL + API + Soketi in Podman (no web container):

```powershell
npm run dev
```

**Terminal 2** — Next.js on Windows:

```powershell
npm run dev:web
```

Or both in one shot:

```powershell
npm run dev:hybrid
```

The `web` service is opt-in only: `npm run dev:docker:full` (needs 8 GB+ Podman RAM for dev mode).

### API on host instead

**Terminal 1** — MySQL + Soketi only:

```powershell
npm run dev:docker:infra
```

**Terminal 2** — Laravel API:

```powershell
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 3** — Next.js:

```powershell
npm run dev:web
```

Ensure `backend/.env` uses:

```env
DB_HOST=127.0.0.1
DB_PORT=3307
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
```

## 4. npm scripts (Podman-aware)

All compose scripts use `node scripts/compose-run.js`, which reads `TOWEROS_CONTAINER_CLI`:

| Command | Purpose |
|---------|---------|
| `npm run dev:docker:infra` | MySQL + Soketi |
| `npm run dev:docker:core` | MySQL + API + Soketi |
| `npm run dev:down` | Stop stack |
| `npm run dev:mysql:grants` | Tenant DB grants |

Manual compose (equivalent):

```powershell
node scripts/compose-run.js --env-file .env.docker up -d mysql soketi
```

## 5. First-time database

```powershell
npm run dev:mysql:grants
cd backend
php artisan toweros:migrate
```

## 6. Optional — Docker-compatible CLI

Podman Desktop → **Settings** → enable **Docker compatibility** if you want `docker` commands to route to Podman. TowerOS does not require this when `TOWEROS_CONTAINER_CLI=podman`.

## 7. Troubleshooting

| Issue | Fix |
|-------|-----|
| “No Container Engine” in UI | Resources → create/start machine |
| `podman info` fails | Start machine in Podman Desktop |
| Port 3307 / 6001 in use | Stop Docker Desktop or old containers |
| Slow bind mounts | Use hybrid mode (no web container) |
| Build fails on Windows paths | Run API/web on host, containers for DB only |

## Auto-detect (Docker or Podman)

Leave `TOWEROS_CONTAINER_CLI=auto` (default in `env.docker.example`). TowerOS uses Podman if its engine is running, otherwise Docker.

---

See also: [local-development-docker-guide.md](local-development-docker-guide.md)
