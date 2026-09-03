# Tenant domain slugs & environments

## Recommended pattern

| Environment | Hostname pattern | Example (Alliance / ATC) |
|-------------|------------------|----------------------------|
| **local** | `{slug}.localhost` | `atc.localhost/login` |
| **test (local dev)** | `test.{slug}.localhost` | `test.atc.localhost/login` |
| **staging (local dev)** | `staging.{slug}.localhost` | `staging.atc.localhost/login` |
| **production (local dev)** | `app.{slug}.localhost` | `app.atc.localhost/login` |
| **test** | `test.{brand_domain}` | `test.alliancetowers.com` |
| **staging** | `staging.{brand_domain}` | `staging.alliancetowers.com` |
| **production** | `app.{brand_domain}` | `app.alliancetowers.com` |
| **production (alt)** | `{brand_domain}` | `alliancetowers.com` |

Slug is still required as **org identity** (links staging ↔ production for environment switch). It is omitted from **brand DNS** hostnames so production can be `app.alliancetowers.com`.

Localhost keeps the slug so multiple orgs can coexist on one developer machine.

## Why not only `app.` and `test.`?

- **`test.*`** — UAT, training, integration with MNO sandboxes; safe to break.
- **`app.*`** — primary production workspace (e.g. `app.alliancetowers.com`).
- **`staging.*`** — pre-prod release validation before promoting to `app.*`.
- **`{slug}.localhost`** — local dev without `/etc/hosts` wildcards beyond one tenant.

## Provisioning fields

When creating a tenant from the platform console:

| Field | Example | Purpose |
|-------|---------|---------|
| `domain` | `app.alliancetowers.com` | **Stancl primary domain** (must resolve for login today) |
| `slug` | `atc` | Org key across environments (not required in the public hostname) |
| `brand_domain` | `alliancetowers.com` | Customer-owned base domain |
| `environment` | `local` / `test` / `staging` / `production` | Which hostname set to recommend |
| `tco_sequence_prefix` | `A` | TCO Site ID sequence letter (Alliance → `A`) |

Recommended endpoints are stored in `tenant_domain_endpoints` for DNS/runbook reference.

## Multiple environments (staging + production)

TowerOS uses **one tenant record per environment**. Each environment has its own database, domain, and rollout data.

**Operator checklist (Phase 2):** [`tenant-environments-phase-2.md`](./tenant-environments-phase-2.md)

| Step | Action |
|------|--------|
| 1 | Create the first tenant with **slug** + **brand domain** + environment (e.g. production) |
| 2 | Platform → **Tenant directory** → **Add environment** on that row |
| 3 | Pick staging / test / production and confirm the recommended domain |
| 4 | Assign rollout policy if needed (copied from source when possible) |

Linked tenants share the same slug and point to the org root via `parent_tenant_id`. Uniqueness is enforced on `(slug, environment)`. Environment switch uses that link — **not** the hostname shape — so `app.alliancetowers.com` ↔ `staging.alliancetowers.com` works the same as before.

Platform API: `POST /api/v1/platform/tenants/{tenant}/environments`

```json
{ "environment": "staging", "domain": "staging.alliancetowers.com", "migrate": true }
```

## Alliance examples

| Purpose | URL |
|---------|-----|
| Local dev (current) | http://alliance.localhost/login |
| Local dev (slug style) | http://atc.localhost/login |
| Local dev (UAT) | http://test.atc.localhost/login |
| Local dev (staging) | http://staging.atc.localhost/login |
| Local dev (production) | http://app.atc.localhost/login |
| Staging | https://staging.alliancetowers.com/login |
| Production app | https://app.alliancetowers.com/login |
| **App Menu (public hub)** | https://appmenu.alliancetowers.com/ (redirects to `/appmenu`) |

## App Menu launcher (`appmenu.{brand_domain}`)

Use a dedicated hostname for the public App Menu landing (not the corporate apex, not `app.`).

| Step | Action |
|------|--------|
| 1 | DNS: CNAME `appmenu` → same TowerOS **web** ALB / edge as `app.` |
| 2 | TLS: include `appmenu.alliancetowers.com` on the cert |
| 3 | Proxy: route that host to the Next.js **web** service (same as tenant SPA) |
| 4 | Frontend env: `NEXT_PUBLIC_CENTRAL_API_BASE_URL` must point at the central API |
| 5 | CORS: ensure `TOWEROS_CORS_ALLOWED_ORIGIN_PATTERNS` covers `https://*.alliancetowers.com` (or add `https://appmenu.alliancetowers.com` to extras) |
| 6 | Do **not** register `appmenu.*` as a tenant domain, and do **not** put it in `CENTRAL_DOMAINS` / `NEXT_PUBLIC_CENTRAL_DOMAINS` (those are for platform console) |

Behavior: any host matching `appmenu.*` (or listed in `NEXT_PUBLIC_APP_MENU_HOSTS`) serves the App Menu at **`/`** (no `/appmenu` in the address bar). Visiting `/appmenu` redirects to `/`. Manage tiles in Platform → App Menu.

### TLS (fix “Not secure”)

`https://appmenu.…` shows **Not secure** when the certificate does **not** include that hostname (name mismatch), even if `app.` and `staging.` work.

| Stack | Fix |
|-------|-----|
| **ACM + ALB** | Request/expand cert to include `appmenu.alliancetowers.com` (or `*.alliancetowers.com`), attach to HTTPS listener |
| **Certbot + Nginx on EC2** | Expand the cert: `sudo certbot --nginx -d app.alliancetowers.com -d staging.alliancetowers.com -d appmenu.alliancetowers.com` (add your real hosts), then reload nginx |

Confirm in the browser: padlock → certificate → Subject Alternative Name lists `appmenu.alliancetowers.com`.

1. Use the **Open tenant sign-in** button (or the sign-in URL shown in the credentials panel).
2. Use the tenant hostname without a port when the web app listens on port 80, for example `http://test.atc.localhost/login`.
3. Sign in with **admin@{domain}** and the password shown in the panel (default dev password is often `password`).
4. If the page shows **Loading sign-in…** for more than a few seconds, restart the frontend dev server (`npm run dev`) so `next.config.ts` allowlist changes apply, then hard-refresh the tenant tab.
5. If you switched between tenant hosts (local → test → app), clear site data for that hostname in DevTools → Application when testing auth.

## DNS checklist (test / prod)

1. CNAME `staging` → TowerOS load balancer / CloudFront (→ `staging.alliancetowers.com`).
2. CNAME `app` → same (or separate origin for prod) (→ `app.alliancetowers.com`).
3. CNAME `appmenu` → same web origin (→ `appmenu.alliancetowers.com` public App Menu hub).
4. TLS cert covers `app.alliancetowers.com`, `staging.alliancetowers.com`, `appmenu.alliancetowers.com` (and optionally apex / wildcards).
5. Tenant hostnames are registered in the central DB when you provision environments; Sanctum stateful domains are merged from that data automatically. Use `SANCTUM_STATEFUL_DOMAINS` only for optional extras (e.g. platform console hosts).

## TCO Site ID

Format: `{Region}-{MNO}{TenantPrefix}{YY}-{TenantPrefix}{Seq}`

Example: `NS-GLO26-A042`

- **Region:** NL, SL, VI, MI, N1–N4, NC
- **MNO:** GLO, SMT, DIT
- **Tenant prefix:** `A` for Alliance (configurable per tenant)

Issued when a SAQ candidate is promoted to site — not at endorsement.
