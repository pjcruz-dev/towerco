# TowerOS tenant environments — Phase 2

**Status:** Phase 2 complete (product already supports this; this doc is the operator checklist)  
**Scope:** Ensure each customer org has isolated **staging** + **production** tenant workspaces.  
**Does not change:** Ticketing, Project-One, E-Approval, or other module business logic.

Related: [`tenant-domain-slugs.md`](./tenant-domain-slugs.md) · [`release-runbook.md`](./release-runbook.md)

---

## What Phase 2 means

TowerOS is **multi-tenant** and **multi-environment**:

| Axis | Meaning |
|------|---------|
| Tenant (customer) | Alliance / ATC vs another TowerCo |
| Environment | `local` / `test` / `staging` / `production` |

**One tenant record per environment.** Each has its own database, domain, users, and operational data. Linked orgs share `slug` and `parent_tenant_id`. Uniqueness: `(slug, environment)`.

Release testing uses the **staging tenant host**. Production customers use the **production tenant host**. App code promote is Phase 1 / Phase 3 — this phase is about **workspace isolation**.

---

## Prerequisites

- [ ] Platform console access (central admin)
- [ ] Org has a root tenant with `slug` + `brand_domain` set
- [ ] DNS or hosts file ready for the new hostname (local: `staging.{slug}.localhost`)
- [ ] Backend can create tenant DBs (`CREATE DATABASE` grants)

---

## Operator checklist — add Staging for an existing Production org

### UI (preferred)

1. Open **Platform → Tenants** (tenant directory).
2. Find the org root (or any linked env row for that slug).
3. Row actions → **Add environment**.
4. Choose **Staging** (or Test / Production if missing).
5. Confirm recommended domain (override only if DNS requires it).
6. Confirm create (API defaults `migrate: true`).
7. Save initial admin credentials from the success panel; open tenant sign-in and verify login.

### API (automation / scripting)

```http
POST /api/v1/platform/tenants/{tenantId}/environments
```

```json
{
  "environment": "staging",
  "domain": null,
  "migrate": true,
  "seed": false
}
```

- Omit `domain` (or `null`) to use the recommended hostname from slug + brand + environment.
- `migrate: true` (default) creates schema on the new tenant DB.
- `seed: false` unless you explicitly want demo seed data.

Response includes `tenant_id`, `domain`, `environment`, `parent_tenant_id`, optional `initial_admin`, and `domain_endpoints`.

Audit event: `tenant.environment_provisioned`.

---

## Recommended layout per customer

| Environment | Host (deployed) | Host (local Docker) | Use for |
|-------------|-----------------|---------------------|---------|
| **staging** | `staging.{slug}.{brand}` | `staging.{slug}.localhost` | Release smoke (Phase 1) |
| **production** | `app.{slug}.{brand}` | `app.{slug}.localhost` | Live users |
| **test** (optional) | `test.{slug}.{brand}` | `test.{slug}.localhost` | UAT / training |
| **local** (optional) | — | `{slug}.localhost` | Engineer sandbox |

Minimum for release discipline: **staging + production** for each go-live customer.

---

## After Add environment

1. Sign in on the **new** host only (do not reuse prod session cookies across hosts).
2. Confirm modules enabled match policy for that env (platform Modules sheet if needed).
3. Confirm playbook / rollout policy copied (service copies binding when possible).
4. For Staging: use this host for Phase 1 smoke checklist before Production promote.
5. For Production: never point Staging deploy validation at this host.

### Environment switch (profile menu)

Seamless **Switch** between Test / Staging / Production requires the tenant permission `workspace:environments:switch`.

- **tenant_admin** has it by default.
- Grant it to others in **Team & Access → Roles** (or extra user permissions).
- Grant it on **each** environment (staging and production are separate tenant databases) if they need to switch both ways.
- Users still need the **same email** on the target host.

---

## DNS / TLS (deployed Staging + Production)

- [ ] CNAME `staging.{slug}` → ALB / edge
- [ ] CNAME `app.{slug}` → ALB / edge (or production origin)
- [ ] Certificate covers required names / wildcards
- [ ] Tenant domains registered in central DB (done at provision time)
- [ ] Sanctum stateful domains pick up tenant hosts automatically

Local: add hosts entries if needed (`127.0.0.1 staging.myapp.localhost`).

---

## Verification checklist

| # | Check | Pass? |
|---|--------|-------|
| 1 | Directory shows both `staging` and `production` for the same slug | |
| 2 | Each row has a distinct primary domain | |
| 3 | Staging login works on staging host | |
| 4 | Production login works on production host | |
| 5 | Creating data on Staging does **not** appear on Production | |
| 6 | `parent_tenant_id` links env tenants to org root | |

---

## What Phase 2 does **not** do

- Does not auto-deploy app code between Staging and Production (see Phase 1 / 3)
- Does not copy tenant business data between environments
- Does not replace git tags or CI/CD
- Does not require module feature changes

---

## Implementation notes (already in product)

| Piece | Location |
|-------|----------|
| Provisioning service | `TenantEnvironmentProvisioningService` |
| Platform API | `POST .../platform/tenants/{tenant}/environments` |
| UI | Tenant directory → **Add environment** (`TenantEnvironmentSheet`) |
| Domain recommendations | `TenantDomainSlugService` / `recommendedTenantDomain` |
| Tests | `TenantEnvironmentProvisioningServiceTest` |

---

## Roadmap status

| Phase | Name | Status |
|-------|------|--------|
| **1** | Manual promote + rollback | Done — [`release-runbook.md`](./release-runbook.md) |
| **2** | Tenant environments | **Done** — this doc |
| **3** | CI/CD automation | Done — [`cicd-phase-3.md`](./cicd-phase-3.md) |
| **4** | Hardening | Done — [`hardening-phase-4.md`](./hardening-phase-4.md) |
