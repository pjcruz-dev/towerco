# TowerOS production — Amazon EC2 + RDS MySQL

**Status:** Confirmed production baseline (AWS 1-year subscription).  
**Scale path later:** [ECS Fargate + Aurora](./aws-ecs-cicd.md).

Copy secrets from [`backend/.env.production.example`](../../backend/.env.production.example) → `backend/.env` on the EC2 host. Never commit real credentials.

---

## Confirmed AWS resources

| Resource | Spec | TowerOS use |
|----------|------|-------------|
| **Amazon EC2** | `t3.large` — 2 vCPU, 8 GB RAM, 50 GB root | Docker: API + Next.js web + Redis + queue worker (+ optional Soketi) |
| **Amazon EBS** | 100 GB General Purpose SSD (`gp3`) | Docker images, logs, build/temp |
| **Amazon RDS MySQL** | `db.t3.medium` — 2 vCPU, 4 GB, 50 GB, **Multi-AZ** | Central DB `toweros` + one DB per tenant (`tenant<uuid>`) |
| **Amazon S3** | Standard — 50 GB/mo; ~2M PUT/LIST + ~2M GET | Tenant documents, exports, presigned uploads |
| **Amazon CloudFront** | CDN | Static assets, frontend resources, file downloads |
| **Amazon Route 53** | Hosted zone | Domain + app / console / tenant subdomains |
| **Amazon CloudWatch** | Logs, metrics, alarms | API/web health, RDS CPU/storage, disk, 5xx |
| **AWS Backup** | **30-day** retention; storage sized to proposed volumes | RDS + EBS recovery |

### Gaps (required — not on the subscription slide)

| Missing | Why | First go-live option |
|---------|-----|----------------------|
| **Redis** | `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, sessions | Docker Redis on the EC2 (or ElastiCache `cache.t3.micro`) |
| **Queue worker** | Approvals, emails, async jobs | systemd → `php artisan queue:work` |
| **Scheduler** | SLA, document expiry, rollout recalc | cron `* * * * * schedule:run` |
| **TLS termination** | Public HTTPS | ALB + ACM, or Nginx/Caddy on EC2 |
| **Mail** | Gate / approval notifications | Amazon SES (or Microsoft 365 SMTP) |

---

## How to resolve the gaps (recommended)

All five fit on your existing **EC2 t3.large** without buying ElastiCache or an extra mail product. Do them in this order.

### 1. Redis — run Compose Redis on the EC2

TowerOS already ships a `redis` service in `docker-compose.yml`. On production, start it with the API (do **not** expose Redis to the internet).

```bash
cd /opt/toweros
docker compose --env-file .env.docker up -d redis
```

In `backend/.env`:

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
```

Verify:

```bash
docker compose --env-file .env.docker exec redis redis-cli ping
# → PONG
```

**Later (optional):** move to ElastiCache `cache.t3.micro` in the private subnet and set `REDIS_HOST` to the ElastiCache endpoint. Not required for first go-live.

---

### 2. Queue worker — systemd unit (always on)

Laravel only *enqueues* jobs; a worker must *run* them (approval emails, exports, AI ingest, etc.).

Create `/etc/systemd/system/toweros-worker.service`:

```ini
[Unit]
Description=TowerOS queue worker
After=docker.service
Requires=docker.service

[Service]
Restart=always
RestartSec=5
WorkingDirectory=/opt/toweros
ExecStart=/usr/bin/docker compose --env-file .env.docker exec -T api php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
ExecStop=/usr/bin/docker compose --env-file .env.docker exec -T api php artisan queue:restart

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now toweros-worker
sudo systemctl status toweros-worker
```

After every deploy: `sudo systemctl restart toweros-worker` (or `queue:restart` inside the API container).

---

### 3. Scheduler — cron every minute

Gate SLA, document expiry, rollout recalc, and other scheduled commands need:

```bash
sudo crontab -e
```

```cron
* * * * * cd /opt/toweros && docker compose --env-file .env.docker exec -T api php artisan schedule:run >> /var/log/toweros-scheduler.log 2>&1
```

```bash
sudo touch /var/log/toweros-scheduler.log
sudo chown $USER:$USER /var/log/toweros-scheduler.log
```

Laravel’s scheduler decides *which* jobs run; cron only wakes it once per minute.

---

### 4. TLS — ALB + ACM (preferred) or Nginx on EC2

**Option A — Application Load Balancer + ACM (recommended)**

1. Request an ACM certificate in the **same region** as the ALB for `app.customer.com`, `console.yourdomain.com` (and wildcards if needed). Validate via Route 53.
2. Create an ALB in public subnets; HTTPS listener `:443` with the ACM cert.
3. Target group(s) → EC2:
   - Prefer **one Nginx on EC2** listening `:80` that routes `/api` → `127.0.0.1:8000` and `/` → `127.0.0.1:80` (web container), **or**
   - Two target groups (web `:80`, API `:8000`) with ALB path rules.
4. EC2 security group: allow `80`/`8000` **only from the ALB SG**, not `0.0.0.0/0`.
5. Route 53 alias `A` records → ALB.

**Option B — Nginx + Let’s Encrypt on the EC2** (simpler, no ALB cost)

```bash
sudo dnf install -y nginx certbot python3-certbot-nginx   # Amazon Linux
# or: sudo apt install -y nginx certbot python3-certbot-nginx
```

Nginx proxies `:443` → local Docker ports (see [§7](#7-https--route-53)); then:

```bash
sudo certbot --nginx -d app.customer.com -d console.yourdomain.com
```

Point Route 53 to the EC2 Elastic IP. Open SG `443` from `0.0.0.0/0` (and `80` for ACM/HTTP-01).

Set `APP_URL` / frontend URLs to `https://…` and force HTTPS at the proxy (`X-Forwarded-Proto`).

---

### 5. SES — Amazon Simple Email Service

TowerOS already supports `MAIL_MAILER=ses` (`aws/aws-sdk-php` is in the backend). Prefer an **EC2 instance role** over access keys.

1. **SES console** (same region as the app, e.g. `ap-southeast-1`):
   - Verify domain `yourdomain.com` (DKIM via Route 53).
   - Verify From address e.g. `noreply@yourdomain.com`.
   - Leave sandbox only after AWS approves production access (request “Production access”).
2. **IAM** on the EC2 role — allow at least:

```json
{
  "Effect": "Allow",
  "Action": ["ses:SendEmail", "ses:SendRawEmail"],
  "Resource": "*"
}
```

3. **`backend/.env`:**

```env
MAIL_MAILER=ses
TOWEROS_NOTIFICATIONS_MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=TowerOS
AWS_DEFAULT_REGION=ap-southeast-1
# Leave AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY empty when using the instance role
```

4. **Test** (after API is up):

```bash
docker compose --env-file .env.docker exec api php artisan tinker
>>> Mail::raw('TowerOS SES OK', fn ($m) => $m->to('you@yourdomain.com')->subject('SES test'));
```

**Alternative:** Microsoft 365 SMTP if the customer already uses it:

```env
MAIL_MAILER=smtp
TOWEROS_NOTIFICATIONS_MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
```

---

### Gap resolution checklist

- [ ] `docker compose … up -d redis` + `redis-cli ping` → PONG  
- [ ] `toweros-worker` systemd enabled and running  
- [ ] cron `schedule:run` every minute; log file writable  
- [ ] HTTPS works (`curl -fsS https://app…/up`); HTTP redirects or blocked  
- [ ] SES domain verified + out of sandbox (or M365 SMTP working)  
- [ ] Trigger one approval / notification and confirm the email arrives  

---

## Topology

```text
Internet
  → Route 53
  → CloudFront (static + downloads; optional at cutover)
  → ALB or Nginx :443
       ├─ /api/*  → Laravel API :8000 (Docker)
       └─ /*      → Next.js :80 (Docker)
  → RDS MySQL Multi-AZ (private subnet)
  → S3 (tenant files; CloudFront origin for downloads)
  → Redis (Docker on EC2 or ElastiCache)
  → CloudWatch (agent / container logs)
  → AWS Backup (RDS + EBS, 30 days)
```

### DNS

| Host | Role |
|------|------|
| `console.yourdomain.com` | Platform superadmin (`CENTRAL_DOMAINS`) |
| `app.customer.com` | Tenant SPA + API (`/api/v1` on same host) |
| `*.customer.com` | Optional per-tenant hosts |

See also [tenant-domain-slugs.md](./tenant-domain-slugs.md).

---

## 1. Provision AWS

1. **VPC** — public + private subnets; RDS in private subnet only.
2. **RDS MySQL 8.0** — `db.t3.medium`, Multi-AZ, 50 GB, DB name `toweros`; note endpoint.
3. **EC2 `t3.large`** — Amazon Linux 2023 (or Ubuntu 22.04); attach **100 GB gp3** EBS; SG: SSH from your IP only; `80`/`443` from ALB or internet if on-box Nginx.
4. **S3** — e.g. `toweros-prod-files-<account-id>`; block public access; versioning on.
5. **IAM instance role** on EC2 — `s3:GetObject` / `PutObject` / `DeleteObject` on the bucket; `ses:SendEmail` if using SES; CloudWatch agent permissions.
6. **Route 53** — hosted zone + records to ALB or Elastic IP.
7. **CloudFront** — origin S3 (and/or ALB) for static/download acceleration; set `AWS_URL` to the distribution URL when ready.
8. **CloudWatch** — alarms: EC2 disk/CPU, RDS CPU/free storage, ALB 5xx, queue depth (custom metric optional).
9. **AWS Backup** — plan covering RDS + EBS, **30-day** retention.

**RDS app user** (once, as master):

```sql
CREATE USER 'toweros'@'%' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON `toweros`.* TO 'toweros'@'%';
GRANT CREATE ON *.* TO 'toweros'@'%';
FLUSH PRIVILEGES;
```

`CREATE` is required so provisioning can create `tenant<uuid>` databases.

---

## 2. Install Docker on EC2

**Amazon Linux 2023:**

```bash
sudo dnf update -y
sudo dnf install -y docker git
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
# Log out and back in
docker compose version || sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose && sudo chmod +x /usr/local/bin/docker-compose
```

**Ubuntu 22.04:**

```bash
sudo apt update && sudo apt install -y docker.io docker-compose-plugin git
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

---

## 3. Clone and configure env

```bash
sudo mkdir -p /opt/toweros && sudo chown $USER:$USER /opt/toweros
cd /opt/toweros
git clone <your-repo-url> .
cp backend/.env.production.example backend/.env
# Edit backend/.env — APP_KEY, DB_*, AWS_BUCKET, domains, mail
```

Generate `APP_KEY` once and store it safely:

```bash
docker run --rm -v "$PWD/backend:/app" -w /app php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
# or: php artisan key:generate --show  (after PHP is available)
```

### Root Docker env — `/opt/toweros/.env.docker`

```env
TOWEROS_MYSQL_PORT=3307
MYSQL_ROOT_PASSWORD=unused-local-only
MYSQL_DATABASE=toweros
MYSQL_USER=toweros
MYSQL_PASSWORD=unused-local-only
TOWEROS_API_PORT=8000
TOWEROS_WEB_PORT=80
TOWEROS_REDIS_PORT=6379
TOWEROS_DOCKER_AUTO_MIGRATE=0
TOWEROS_DOCKER_MIGRATE_TENANTS=0
TOWEROS_API_WORKERS=4
TOWEROS_API_MEM_LIMIT=2g
TOWEROS_WEB_MEM_LIMIT=3g
TOWEROS_WEB_MODE=prod
```

### Frontend — `frontend/.env.docker`

```env
NEXT_PUBLIC_APP_ENV=production
NEXT_PUBLIC_API_BASE_URL=https://app.customer.com/api/v1
NEXT_PUBLIC_CENTRAL_API_BASE_URL=https://console.yourdomain.com/api/v1
NEXT_PUBLIC_CENTRAL_DOMAINS=console.yourdomain.com
NEXT_PUBLIC_SOCKET_ENABLED=false
TOWEROS_WEB_MODE=prod
```

Do **not** put `APP_KEY` in `.env.docker` — only in `backend/.env`.

**Critical for MFA:** Authenticator secrets are encrypted with `APP_KEY`. If the key changes after users enroll, TOTP codes stop working until they use a recovery code and re-enroll. Generate once, store in Secrets Manager / sealed `backend/.env`, and never regenerate on container boot.

Local/ops diagnostic (also works against a running API container):

```bash
docker compose --env-file .env.docker exec api php artisan toweros:mfa-health
# or from repo root on Windows:
npm run mfa:health
```

Compare the printed `APP_KEY fingerprint` across deploys — it must stay stable.

---

## 4. Start services (RDS, not container MySQL)

```bash
cd /opt/toweros
export DB_HOST=<rds-endpoint>
export DB_USERNAME=toweros
export DB_PASSWORD=<rds-password>

docker compose --env-file .env.docker up -d --build redis api
docker compose --env-file .env.docker --profile web up -d --build web
```

First boot:

```bash
docker compose --env-file .env.docker exec api php artisan migrate --force
docker compose --env-file .env.docker exec api php artisan db:seed --force
docker compose --env-file .env.docker exec api php artisan passport:client --personal --no-interaction
docker compose --env-file .env.docker exec api php artisan config:cache
```

---

## 5. Queue worker (required)

`/etc/systemd/system/toweros-worker.service`:

```ini
[Unit]
Description=TowerOS queue worker
After=docker.service
Requires=docker.service

[Service]
Restart=always
WorkingDirectory=/opt/toweros
ExecStart=/usr/bin/docker compose --env-file .env.docker exec -T api php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
ExecStop=/usr/bin/docker compose --env-file .env.docker exec -T api php artisan queue:restart

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now toweros-worker
```

---

## 6. Scheduler (required)

```cron
* * * * * cd /opt/toweros && docker compose --env-file .env.docker exec -T api php artisan schedule:run >> /var/log/toweros-scheduler.log 2>&1
```

---

## 7. HTTPS + Route 53

**Option A — ALB + ACM (recommended):** target groups → EC2 `:80` (web) and `:8000` (API), or single Nginx on EC2 routing `/api` → API.

**Option B — Nginx on EC2:**

```nginx
server {
    listen 443 ssl http2;
    server_name app.customer.com;

    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

Point Route 53 `A`/`CNAME` to ALB or Elastic IP.

---

## 8. CloudWatch + AWS Backup checklist

- [ ] CloudWatch agent (or Docker log driver) shipping API/web logs
- [ ] Alarms: EC2 CPU/disk, RDS CPU/free storage, ALB/target 5xx
- [ ] AWS Backup plan: RDS + EBS, **30 days**
- [ ] S3 versioning + lifecycle as needed
- [ ] CloudFront attached for static / download acceleration (`AWS_URL` set)

---

## 9. Smoke test

| Check | Action |
|-------|--------|
| Health | `curl -fsS https://app.customer.com/up` |
| Platform | `https://console.yourdomain.com/platform/login` |
| Provision | Create tenant; confirm `tenant*` DB on RDS |
| Files | Upload to site binder / document register (S3) |
| Queue | Trigger approval → email/notification delivered |
| MFA / SSO | Per-tenant Sign-in & security |

---

## 10. Release upgrades

```bash
cd /opt/toweros
git pull
docker compose --env-file .env.docker build api web
docker compose --env-file .env.docker up -d api web
docker compose --env-file .env.docker exec api php artisan toweros:migrate --force
docker compose --env-file .env.docker exec api php artisan config:cache
docker compose --env-file .env.docker exec api php artisan queue:restart
sudo systemctl restart toweros-worker
```

After every deploy that touches env/secrets, confirm MFA prerequisites:

```bash
docker compose --env-file .env.docker exec api php artisan toweros:mfa-health
# fingerprint must match the previous release
```

---

## 10b. MFA incident recovery (already deployed)

If users suddenly see **Invalid MFA code** or **MFA authenticator secret is unreadable** after a deploy/restart:

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| Exact TOTP rejected | Host/container clock skew | Fix NTP; retry with a fresh code |
| “secret is unreadable” | `APP_KEY` changed | Restore the **previous** `APP_KEY` from Secrets Manager / backup `.env`, then `config:cache` + restart API. Do **not** invent a new key. |
| Recovery codes work, TOTP does not after restore | Ciphertext was written under a different key than restored | Users must recover → re-enroll; admin may disable broken factors |
| All MFA rows gone | Wrong DB / restored empty schema | Restore RDS from snapshot; do not re-seed production |

**Break-glass (per user, no key restore needed):**

1. Sign in with password → MFA screen → enter a **recovery code**.
2. In Security settings, remove/re-enroll authenticator and save new recovery codes offline.
3. If the user has no recovery codes and TOTP is dead because `APP_KEY` was lost: platform/tenant admin must clear that user’s MFA factors (or temporarily disable tenant `mfa_required`) so they can sign in and re-enroll — then turn policy back on.

**Prevention**

- Pin `APP_KEY` in AWS Secrets Manager; inject into `backend/.env` / task definition. Never put it only in an ephemeral container layer.
- Do not run `php artisan key:generate` in production pipelines.
- Redis/session restarts are safe for MFA secrets (DB-backed). RDS + stable `APP_KEY` are the durable pair.
- Keep printed recovery codes offline at enroll time.

---

## Environment checklist

| Concern | Production value |
|---------|------------------|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| Database | RDS MySQL 8 Multi-AZ (`DB_HOST` = RDS endpoint) |
| Files | `TOWEROS_TENANT_FILES_DISK=s3` + `AWS_BUCKET` |
| CDN | `AWS_URL` = CloudFront URL when enabled |
| Redis | Docker `redis` or ElastiCache |
| Queues + scheduler | Always running |
| Mail | SES |
| MFA | `TOWEROS_TENANT_DEFAULT_MFA_REQUIRED=true` |
| Bootstrap passwords | `TOWEROS_TENANT_BOOTSTRAP_EXPOSE_PASSWORD_IN_API=false` |

---

## Related

- Env template: [`backend/.env.production.example`](../../backend/.env.production.example)
- Release process: [release-runbook.md](./release-runbook.md)
- ECS scale path: [aws-ecs-cicd.md](./aws-ecs-cicd.md)
- Performance: [`docs/performance-runbook.md`](../performance-runbook.md)
