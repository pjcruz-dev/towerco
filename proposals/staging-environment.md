# Pre-release (staging) environment: recommended setup

Two different things are called "staging" in TowerOS:

1. **Tenant environments** (staging/test/local tenants created from `console.alliancetowers.com`). Own database, domain and users, but they live in the production platform and run production code. Keep them: they are good for customer UAT and training. They are not a place to test new code or migrations.
2. **A pre-release stack** (separate servers, separate database, separate Redis and bucket). This is what protects production from a bad release. Build this one.

Why it matters more for you than for most: every release runs migrations against one database per tenant (`tenants:migrate`). A migration that fails halfway across many tenant databases is the worst release failure you can have, and a separate stack lets you rehearse it first.

## Recommended option (assuming production stays on EC2 + RDS + Docker Compose)

- A second, smaller EC2 running the same `docker-compose` and the same image tags as production, so parity is highest and there is nothing new to learn.
- A separate RDS MySQL 8.4 instance (smaller class). Never the production instance, not even a second schema on it.
- Its own Redis container, its own S3 bucket (or a prefix locked down by policy so staging credentials cannot read production), its own mail sandbox.
- Hostnames in a new namespace: `stg.alliancetowers.com` (`console.stg`, `api.stg`, tenants `<slug>.stg`). Do NOT reuse `staging.alliancetowers.com`: it is a production tenant host and must keep pointing at production.

Alternatives, and when to pick them:

- **ECS Fargate via the existing `deploy-staging.yml`.** Pick this if you plan to move production to ECS within roughly six months, so staging rehearses the move. Until then staging and production differ (ECS vs EC2), and you must build ECR, ECS, ALB and the OIDC role first.
- **Second Compose project on the production EC2.** Cheapest, weakest. A runaway migration or queue job can starve production, and one mistake in `.env` reaches production data. Only as a short stopgap, with separate databases, credentials, Redis instance and cookie names.
- **Keep only the staging tenant.** Acceptable as a customer sandbox. Never call it release staging.

## DNS with GoDaddy

Leave production DNS at GoDaddy. For staging, create a Route 53 hosted zone for `stg.alliancetowers.com` and add NS records for `stg` at GoDaddy (delegation). AWS then manages the staging records and the certificate validation without moving the main domain. Alternative: add the A record and a wildcard `*.stg` A record at GoDaddy by hand, and issue the wildcard certificate with ACM (DNS validation record added once at GoDaddy) if you use an ALB.

## Variables that must differ or be safe (names from `backend/.env.example`)

`APP_URL`, `FRONTEND_APP_URL`, `CENTRAL_DOMAINS`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_COOKIE`, `SESSION_DOMAIN`, `CACHE_PREFIX`, `REDIS_PREFIX`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `TENANCY_DATABASE_PREFIX` (use `stg_tenant`), `AWS_BUCKET` and the tenant files disk path, `PUSHER_*` (own Soketi app id, key and secret), `MAIL_*` (sandbox or allow-list only), `AZURE_*` (its own redirect URI registered in Entra), `TOWEROS_STRIPE_ENABLED=false` (or test keys), `DOC_EXTRACT_URL` (staging service), `TOWEROS_PLATFORM_SUPER_ADMIN_EMAIL`, `TRUSTED_PROXIES`. `TOWEROS_TENANT_ENABLED_MODULES` should equal production.

## Data

No real customer data by default. Use the `SeedAllianceDemo` command for demo data. To rehearse a migration, restore a `TenantDatabaseBackup` of one production tenant only if your data policy allows it, and mask personal data first.

## Release flow

Build once, deploy the same image tag to staging, run `php artisan migrate` and `tenants:migrate`, run the smoke checklist, promote the same tag to production.

## Guards

A visible "STAGING" banner (the tenant's `environment` is already available to the app), outbound email allow-list, Stripe off, credentials different from production, and a platform console per stack (`console.stg`) so nobody provisions in the wrong one.

Not verified: the exact behaviour of each variable in your code, and whether your certificate setup is ALB or nginx. Check both before building.
