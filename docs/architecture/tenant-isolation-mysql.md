# ADR: Tenant isolation on MySQL (schema-per-tenant)

| Field | Value |
|--------|--------|
| Status | Accepted |
| Stack | Laravel, MySQL, Redis, stancl/tenancy |
| Scope | TowerOS data plane and request lifecycle |

## Context

TowerOS is multi-tenant SaaS. In MySQL, the native isolation boundary for application data is a **database** (MySQL uses the term *database*; it is equivalent to what other engines call a *schema*). MySQL does not provide multiple named schemas inside one database like PostgreSQL.

The product requirement **“schema-per-tenant”** is therefore implemented as **one MySQL database per tenant**, provisioned and switched by **stancl/tenancy**, with a separate **central** MySQL database for global metadata (`tenants`, `domains`, billing references, platform identity, SSO configuration, and cross-tenant audit).

This is **not** row-level multi-tenancy (single shared database plus `tenant_id` on every table).

## Decision

1. **Central database**  
   Use the Laravel `central` connection for all records that must exist once globally and for platform operators. No tenant user tables or tenant business entities live here.

2. **Tenant databases**  
   Each tenant receives a dedicated MySQL database (naming driven by `config/tenancy.php`, for example prefix `tenant`). Tenant migrations live under `database/migrations/tenant` and run per tenant database.

3. **Runtime switching**  
   After tenant resolution, `tenancy()->initialize($tenant)` runs so `DatabaseTenancyBootstrapper` points the tenant connection at that tenant’s database. Application code in tenant routes assumes the default tenant connection is scoped to the current tenant.

4. **Supporting isolation (already aligned with stancl bootstrappers)**  
   - **Cache:** `CacheTenancyBootstrapper` — tenant-scoped key prefix.  
   - **Queue:** `QueueTenancyBootstrapper` — jobs restore tenant context on workers; tenant-aware jobs carry the tenant key.  
   - **Filesystem:** `FilesystemTenancyBootstrapper` — tenant-suffixed storage paths for configured disks.  
   - **Broadcasting:** channels and authorization must include the tenant identifier and must match the initialized tenant for the connection (no cross-tenant subscriptions).

5. **Tenant resolution**  
   Primary: HTTP `Host` mapped to `domains` → tenant. Secondary (when enabled): central API host with validated `X-Tenant-Id` or `X-Tenant-Domain` and production safeguards (see `App\Core\Http\Middleware\InitializeTenancyForTenantRequest`).

## Consequences

**Positive**

- Strong data isolation at the storage engine boundary.  
- Simple mental model: central vs tenant; matches stancl defaults for MySQL (`MySQLDatabaseManager`).  
- Backups and restores can be scoped per tenant database when required.

**Tradeoffs**

- Many databases per MySQL instance: monitor connection counts, metadata overhead, and DDL/migration concurrency; plan **sharding** (tenant → instance map on central) before tenant count drives operational pain.  
- No foreign keys from tenant databases to central: correlate by UUID and explicit service boundaries.  
- Operational tooling must treat “migrate tenant” as a fleet operation (stancl `tenants:migrate`, Horizon-tagged jobs, drift checks).

## Out of scope

- Replacing this model with a single-database `tenant_id` row model.  
- PostgreSQL-specific schema `search_path` strategies (different product decision).

## References (codebase)

- `backend/config/tenancy.php` — tenant model, bootstrappers, migration paths.  
- `backend/app/Providers/TenancyServiceProvider.php` — create/migrate/delete database pipeline on tenant lifecycle events.  
- `backend/app/Core/Http/Middleware/InitializeTenancyForTenantRequest.php` — tenant resolver for tenant API routes.
