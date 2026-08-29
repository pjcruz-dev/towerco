# Tenant database backups (Data Protection Center)

Per-tenant **logical MySQL dumps** stored on the tenant files disk (S3 in production). This complements **AWS Backup / RDS snapshots** (infra DR); it does not replace them.

## Actors

| Actor | Create | List | Download | Restore | Delete | Cron Sync |
|-------|--------|------|----------|---------|--------|-----------|
| Platform (`platform.tenants.backup`) | Yes | Yes | Yes | Yes | Yes | Yes |
| Platform (`platform.tenants.view`) | — | Yes | Yes | — | — | — |
| Tenant admin (`tenant:manage`) | — | Completed only | Yes | — | — | — |

## UI

- **Platform:** Tenant 360 → **Backups** tab (Data Protection Center).
- **Tenant workspace:** Administration → Settings hub → **Backups** (`/admin/backups`).

## API

### Platform (central)

- `GET /api/v1/platform/tenants/{tenant}/backups`
- `POST /api/v1/platform/tenants/{tenant}/backups` `{ "reason": "..." }`
- `POST /api/v1/platform/tenants/{tenant}/backups/schedule-run`
- `GET /api/v1/platform/tenants/{tenant}/backups/{id}/download`
- `POST /api/v1/platform/tenants/{tenant}/backups/{id}/restore` `{ "confirm": "<slug|brand_domain>", "reason": "..." }`
- `DELETE /api/v1/platform/tenants/{tenant}/backups/{id}`

### Tenant

- `GET /api/v1/admin/backups`
- `GET /api/v1/admin/backups/{id}/download`

## Storage

`{tenantId}/backups/{yyyy}/{mm}/{backupId}.sql.gz` on `TOWEROS_TENANT_FILES_DISK`.

**Download:** the API streams an ungzipped `.sql` file (easier to open on Windows). Storage stays gzipped.

> Windows Explorer **Extract all** does not support `.gz`. If you still have an old `.sql.gz` download, open it with 7-Zip / WinRAR, or re-download after this change to get a plain `.sql`.

## Config

| Env | Default | Purpose |
|-----|---------|---------|
| `TOWEROS_TENANT_DB_BACKUP_ENABLED` | `true` | Master switch |
| `TOWEROS_TENANT_DB_BACKUP_RETENTION_DAYS` | `15` | Auto-expire completed dumps |
| `TOWEROS_TENANT_DB_BACKUP_SCHEDULE_ENABLED` | `false` / prod example `true` | Nightly fleet backups |
| `TOWEROS_TENANT_DB_BACKUP_SCHEDULE_TIME` | `02:30` | UTC daily schedule |
| `TOWEROS_MYSQLDUMP_PATH` / `TOWEROS_MYSQL_PATH` | `mysqldump` / `mysql` | CLI binaries |

## Ops

1. API image must include MySQL client tools (`default-mysql-client` in `backend/Dockerfile`).
2. Queue worker must run (`toweros-worker`) — create/restore are async jobs.
3. After deploy: `php artisan migrate` (central table `tenant_database_backups`).
4. Manual fleet run: `php artisan tenants:backup-schedule --force`
5. Prune: `php artisan tenants:backup-prune` (also scheduled daily).

## Restore / import

There is **no Upload SQL** in v1. To restore (import) a dump back into the tenant database:

1. Open **Platform console → Tenant → Backups**.
2. On a **completed** row, click **Restore**.
3. Type the tenant **slug** (or brand domain) exactly and enter a **reason**.
4. Confirm — live tenant data is replaced; access is blocked until the job finishes.

Tenant admins can only **download**; they cannot restore.

## Safety

- Never dumps/restores the central database.
- Storage paths must start with `{tenantId}/backups/`.
- Downloads stream through authenticated API endpoints (local `tenant_files` is not public `/storage`).
- Restore requires typing the tenant slug (or brand domain) and a reason; sets `operator_access_mode=blocked` for the duration of the job.
- No arbitrary SQL upload in v1.
