# Tenant domain slugs & environments

## Recommended pattern

| Environment | Hostname pattern | Example (Alliance / ATC) |
|-------------|------------------|----------------------------|
| **local** | `{slug}.localhost` | `atc.localhost/login` |
| **test (local dev)** | `test.{slug}.localhost` | `test.atc.localhost/login` |
| **staging (local dev)** | `staging.{slug}.localhost` | `staging.atc.localhost/login` |
| **production (local dev)** | `app.{slug}.localhost` | `app.atc.localhost/login` |
| **test** | `test.{slug}.{brand_domain}` | `test.atc.alliancetowers.com` |
| **staging** | `staging.{slug}.{brand_domain}` | `staging.atc.alliancetowers.com` |
| **production** | `app.{slug}.{brand_domain}` | `app.atc.alliancetowers.com` |
| **production (alt)** | `{slug}.{brand_domain}` | `atc.alliancetowers.com` |

## Why not only `app.` and `test.`?

- **`test.*`** — UAT, training, integration with MNO sandboxes; safe to break.
- **`app.*`** — primary production workspace (matches common TowerCo DNS like `app.atc.alliancetowers.com`).
- **`staging.*`** — pre-prod release validation before promoting to `app.*`.
- **`{slug}.localhost`** — local dev without `/etc/hosts` wildcards beyond one tenant.

## Provisioning fields

When creating a tenant from the platform console:

| Field | Example | Purpose |
|-------|---------|---------|
| `domain` | `alliance.localhost` | **Stancl primary domain** (must resolve for login today) |
| `slug` | `atc` | Short tenant key for URL generation |
| `brand_domain` | `alliancetowers.com` | Customer-owned base domain |
| `environment` | `local` / `test` / `staging` / `production` | Which hostname set to recommend |
| `tco_sequence_prefix` | `A` | TCO Site ID sequence letter (Alliance → `A`) |

Recommended endpoints are stored in `tenant_domain_endpoints` for DNS/runbook reference.

## Multiple environments (staging + production)

TowerOS uses **one tenant record per environment**. Each environment has its own database, domain, and rollout data.

| Step | Action |
|------|--------|
| 1 | Create the first tenant with **slug** + **brand domain** + environment (e.g. production) |
| 2 | Platform → **Tenant directory** → **Add env** on that row |
| 3 | Pick staging / test / production and confirm the recommended domain |
| 4 | Assign rollout policy if needed (copied from source when possible) |

Linked tenants share the same slug and point to the org root via `parent_tenant_id`. Uniqueness is enforced on `(slug, environment)`.

Platform API: `POST /api/v1/platform/tenants/{tenant}/environments`

```json
{ "environment": "staging", "domain": "staging.atc.alliancetowers.com", "migrate": true }
```

## Alliance examples

| Purpose | URL |
|---------|-----|
| Local dev (current) | http://alliance.localhost/login |
| Local dev (slug style) | http://atc.localhost/login |
| Local dev (UAT) | http://test.atc.localhost/login |
| Local dev (staging) | http://staging.atc.localhost/login |
| Local dev (production) | http://app.atc.localhost/login |
| Test | https://test.atc.alliancetowers.com/login |
| Production app | https://app.atc.alliancetowers.com/login |

## After you create a tenant environment

1. Use the **Open tenant sign-in** button (or the sign-in URL shown in the credentials panel).
2. Use the tenant hostname without a port when the web app listens on port 80, for example `http://test.atc.localhost/login`.
3. Sign in with **admin@{domain}** and the password shown in the panel (default dev password is often `password`).
4. If the page shows **Loading sign-in…** for more than a few seconds, restart the frontend dev server (`npm run dev`) so `next.config.ts` allowlist changes apply, then hard-refresh the tenant tab.
5. If you switched between tenant hosts (local → test → app), clear site data for that hostname in DevTools → Application when testing auth.

## DNS checklist (test / prod)

1. CNAME `test.atc` → TowerOS load balancer / CloudFront.
2. CNAME `app.atc` → same (or separate origin for prod).
3. TLS cert covers `*.atc.alliancetowers.com` or SAN entries.
4. Tenant hostnames are registered in the central DB when you provision environments; Sanctum stateful domains are merged from that data automatically. Use `SANCTUM_STATEFUL_DOMAINS` only for optional extras (e.g. platform console hosts).

## TCO Site ID

Format: `{Region}-{MNO}{TenantPrefix}{YY}-{TenantPrefix}{Seq}`

Example: `NS-GLO26-A042`

- **Region:** NL, SL, VI, MI, N1–N4, NC
- **MNO:** GLO, SMT, DIT
- **Tenant prefix:** `A` for Alliance (configurable per tenant)

Issued when a SAQ candidate is promoted to site — not at endorsement.
