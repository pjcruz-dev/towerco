# Rollout gate approvals — Phase A

Phase A delivers **multi-step timeline gate approvals** with **queued email notifications** on the fixed v2 playbook timeline.

## Scope (Phase A)

| Item | Detail |
|------|--------|
| Gates | Pilot 5: `site_hunting`, `tssr_creation`, `pre_construction`, `permitting`, `construction` |
| Workflow | Submit → multi-step role chain → auto `passed` on final approve |
| Reject | Gate stays **`pending`**; requester can **resubmit** |
| Waive / fail | Manual dropdown (waived / failed) when no open approval |
| Email | Laravel queued notifications (`toweros-notifications` queue) |
| Tenant config | Enable/disable gates + override chains at `/project-one/rollout-playbook` |
| Inbox | `/project-one/gate-approvals` |

## API (tenant)

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/project-one/gate-approvals` | `project_one:rollout:view` |
| POST | `/api/v1/project-one/rollout-phases/{phase}/gate-approvals` | `project_one:rollout:manage` |
| POST | `/api/v1/project-one/gate-approvals/{request}/decide` | actor in chain or `project_one:rollout:gate:approve` |
| PATCH | `/api/v1/project-one/rollout-playbook` | `project_one:playbook:configure` — accepts `gate_approval_policies` |

Direct **Passed** on approval-required gates is blocked until a completed approval exists.

## Default chains (platform)

| Phase | Chain |
|-------|--------|
| site_hunting | saq → pmo |
| tssr_creation | saq_engineering → saq → pmo |
| pre_construction | engineering → pmo |
| permitting | saq → engineering → pmo |
| construction | cme → pmo → tenant_admin |

## Email configuration

Set in `backend/.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="TowerOS"

TOWEROS_TENANT_APP_URL=http://localhost:3001
QUEUE_CONNECTION=database
```

For AWS production, use `MAIL_MAILER=ses` with standard SES credentials.

**Do not** use legacy SMTP basic auth against Microsoft 365.

## Tenant migration

```bat
cd backend
php artisan tenants:migrate --force
php artisan tenants:sync-playbook --domain=alliance.localhost --with-rbac
```

## Manual verify

1. Open rollout timeline → **Request approval** on Site Hunting gate.
2. Approve as SAQ step, then PMO step → gate becomes **Passed**.
3. Reject on another gate → status stays **Pending**; resubmit works.
4. Check mail log / inbox for notification emails.
5. Configure chains on **Rollout playbook** page.

## Tests

```bat
php artisan test --filter=RolloutGateApproval
```
