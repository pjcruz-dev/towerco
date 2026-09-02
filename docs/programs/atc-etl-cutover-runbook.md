# ATC Dynamic Entities — ETL cutover runbook

Staging reconcile and production cutover for Alliance Towers Metacoresoft → TowerOS **Dynamic Entities**.

Related: [atc-erp-parity.md](./atc-erp-parity.md) · [dynamic-entities.md](../modules/dynamic-entities.md) · [release-runbook.md](../infrastructure/release-runbook.md)

---

## Hard rules

1. **Never** restore the Metacoresoft SQL dump into a TowerOS database (`mysql < dump` is forbidden).
2. Use **ETL only**: `atc:import-meta`, `atc:import-data`, `atc:seed-ticketing-pack`.
3. Keep **E-Approval** and native **`/ticketing`** unchanged.
4. Staging first — production only after `atc:verify-import` is OK.

---

## Prerequisites

- [ ] Tenant has `dynamic_entities` enabled
- [ ] Tenant migrated (`tenants:migrate` includes `dyn_*` tables)
- [ ] Dump mounted/copied (e.g. `backend/storage/app/imports/atc-dump.sql` → `/var/www/html/storage/app/imports/atc-dump.sql` in Docker)
- [ ] Operator has tenant UUID and `dynamic_entities:*` RBAC for smoke users
- [ ] Queue / Redis healthy if background jobs are used elsewhere (imports are sync Artisan)

---

## Staging reconcile

### 1. Backup reminder

Prefer a tenant logical backup before large imports (see `tenant_database_backup` in `config/toweros.php` / `tenants:backup-schedule`).

### 2. Metadata

```bash
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:import-meta \
  --tenant=<TENANT_UUID> \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql
```

### 3. Dry-run data (optional)

```bash
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:import-data \
  --tenant=<TENANT_UUID> --pack=all --dry-run \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql
```

### 4. Import packs (idempotent)

Run in order (or `--pack=all` after meta):

```bash
# Phase 1 PM + reference
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:import-data \
  --tenant=<TENANT_UUID> --pack=phase1 \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql

# Phase 2 procurement
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:import-data \
  --tenant=<TENANT_UUID> --pack=phase2 \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql

# Phase 3 finance (large — several minutes)
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:import-data \
  --tenant=<TENANT_UUID> --pack=phase3 \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql
```

### 5. Ticketing seed (no dump tables)

```bash
docker exec -it toweros-api php artisan atc:seed-ticketing-pack --tenant=<TENANT_UUID>
```

### 6. Verify / reconcile

```bash
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:verify-import \
  --tenant=<TENANT_UUID> --pack=all \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql
```

Expect:

- `status=ok` for imported PM / procurement / finance entities (dump eligible ≈ dyn imported)
- `status=skipped_no_dump` for ticketing slugs
- `status=missing_table` is acceptable for known empty dump tables (e.g. `construction_activities`, `site_documents` in current ATC dump)
- Exit code **0** when there are **no mismatches** and **no missing entities** (missing tables alone do not fail)

Optional: `--tolerance=N` if a known small delta is accepted (document the reason).

### 7. UI smoke (staging)

| Check | Path |
|---|---|
| Executive Dashboard | `/dynamic-entities/executive-dashboard` |
| Tower Sites list + detail + related tabs | `/dynamic-entities/tower_sites` |
| Purchase Monitoring | `/dynamic-entities/reports/purchase-monitoring` |
| Capex & Opex | `/dynamic-entities/reports/capex-opex` |
| Ticketing Board | `/dynamic-entities/ticketing-board` |
| Native IT helpdesk still works | `/ticketing` |
| E-Approval unchanged | `/e-approval` |

---

## Production cutover

1. [ ] Announce maintenance window
2. [ ] **Backup** tenant DB (logical backup / RDS snapshot per infra runbook)
3. [ ] Copy/mount **production-approved** dump (same ETL path — never SQL restore)
4. [ ] `atc:import-meta`
5. [ ] `atc:import-data --pack=all` (or phased) — re-run is safe (id maps)
6. [ ] `atc:seed-ticketing-pack`
7. [ ] `atc:verify-import --pack=all` → **must exit 0**
8. [ ] UI smoke checklist above on production hostname
9. [ ] Watch audit channel for `atc.import.*` / `atc.verify.*` events (`toweros.logging.audit_*`)
10. [ ] 48h monitor: report pages, import error logs, support queue

### Rollback notes

- **App** rollback (deploy previous image) does **not** undo ETL data.
- Data undo = restore tenant backup / RDS snapshot taken **before** import.
- Do not “fix” by loading Metacoresoft dump into TowerOS MySQL.

---

## Commands reference

| Command | Purpose |
|---|---|
| `atc:import-meta` | Entities + fields from dump |
| `atc:import-data --pack=…` | Records ETL |
| `atc:import-data --dry-run` | Count only |
| `atc:seed-ticketing-pack` | Greenfield ticketing schema |
| `atc:verify-import` | Dump vs dyn_records reconcile |

Audit events (structured): `atc.import_meta.completed`, `atc.import.dry_run`, `atc.import.completed`, `atc.verify.completed`.
