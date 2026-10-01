---
name: tenant-safe-migration
description: Write safe database migrations for TowerOS's multi-tenant MySQL (central vs tenant migrations, ordered UUID keys, indexes, zero-downtime expand/contract, backfills). Use whenever the user asks to add, change, rename, or drop a table or column, add an index, backfill data, or otherwise touch the database schema.
---

# Tenant-safe migrations (TowerOS)

1. **Pick the folder.** Tenant data (tickets, e-forms/approvals, documents, extraction jobs...) goes in `backend/database/migrations/tenant` (database-per-tenant via stancl/tenancy). Central data (tenants, domains, plans, billing, platform admins) goes in `backend/database/migrations`. Check `config/tenancy.php` and `app/Modules/Tenancy` if unsure.
2. **Never edit a committed migration.** Add a new one. (A hook blocks edits to tracked migrations.)
3. **Keys.** Ordered UUID primary keys and `HasUuids` (or the project's base model). FKs via `foreignUuid()->constrained()`, `restrictOnDelete` unless a cascade is deliberate and audited.
4. **Indexes** for every column used in `filter[]`, `sort` or joins. Composite indexes match real query patterns.
5. **Zero-downtime changes (expand/contract).** Add nullable column or new table -> deploy code that writes both -> backfill in chunks via a queued job -> switch reads -> drop the old column in a later release. Renames and type changes are add + copy + switch + drop. Avoid long locks on big tables; prefer `INSTANT`/`INPLACE` DDL on MySQL 8.4.
6. **Backfills** are idempotent, chunked, tenant-aware and resumable, and run as Horizon jobs. Not inside the schema migration for large tables.
7. **`down()`** must be correct or documented as irreversible.
8. **Test** on at least two tenant databases: migrate, roll back, migrate again. Add or update the factory and seeder. Follow `tests/Feature/Tenancy`.
9. **Commands.** Central: `php artisan migrate`. Tenants: `php artisan tenants:migrate`. Run inside the container with `node scripts/compose-run.js --env-file .env.docker exec api php artisan ...` or on the host from `backend/`. Never use `migrate:fresh`, and ask before `npm run dev:fresh`.
