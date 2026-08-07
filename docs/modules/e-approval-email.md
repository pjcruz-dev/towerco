# E-Approval — email notifications (TowerOS modern mail)

E-Approval uses **Laravel queued notifications** on the `toweros-notifications` queue. Transport is **platform mail** (Microsoft 365 SMTP or AWS SES) — **not** the legacy standalone formbuilder Graph sidecar.

Related: [e-approval.md](./e-approval.md) · [e-approval-go-live-checklist.md](./e-approval-go-live-checklist.md)

---

## Who gets email

| Event | Recipient | When |
|--------|-----------|------|
| `submitted` | Requestor | First submit / draft submit when workflow is pending |
| `resubmitted_resume` | Requestor | Resubmit after revision when routing **resumes** at the return step |
| `resubmitted_restart` | Requestor | Resubmit after revision when routing does a **full restart** from step 1 |
| `approval_assigned` | Approver | Step activated on first submit, normal advance, or reroute (new assignee). **Parallel bands:** every activated peer is notified. |
| `approval_assigned_revised` | Approver | Step activated after revision resubmit (resume or full restart) |
| `approval_rerouted` | Previous approver | Admin reroutes a pending approval — email + in-app (no further action) |
| `workflow_steps_skipped` | Requestor | Exclusive path skip digest when condition-gated step orders are skipped |
| `approved` | Requestor | Final approval (or auto-approved with no matching steps) |
| `rejected` | Requestor | Rejection |
| `returned` | Requestor | Revision requested — body includes resume vs restart outlook |
| `awaiting_dcf` | Requestor | Document control gate |
| `manual_follow_up` | Current pending approver(s) | Requestor sends follow-up; **all pending peers on the current step** (parallel included) |
| `cancelled` | Requestor + pending approvers | Requestor cancels draft / pending / returned request |
| `approval_no_longer_needed` | Cleared parallel peers | Parallel `any` / `n_of_m` quorum met — remaining pending peers invalidated |
| `sla_reminder` | Approver | `php artisan e-approval:sla-run` (scheduled); each pending row independently |
| `sla_escalation` | Configured users | SLA runner escalation |

### External submitter (opt-in, anonymous mail route)

All default **off** in `e_approval_settings`. Recipients are `external_submitter_email` via `Notification::route('mail', …)` — not tenant users. CTAs never point at authenticated `/e-approval/...` paths.

| Event | Setting key | When |
|--------|-------------|------|
| `external_status_received` | `notify_external_on_received` | Public form submitted |
| `external_status_approved` | `notify_external_on_approved` | Final approve; may include clickable secure download links for form **External deliverables** uploads when `metadata.outbound.email_package_on_approve` is on |
| `external_status_rejected` | `notify_external_on_rejected` | Rejected |
| `external_status_returned` | `notify_external_on_returned` | Returned for revision; includes public revise URL |

Internal sponsor/approver mails above are unchanged when these toggles are enabled.

**In-app** bell notifications are separate; users still see actions in TowerOS if email is misconfigured.

**Comments** do not send email (in-app only).

### Revision-aware copy

| Situation | Requestor email | Approver email |
|-----------|-----------------|----------------|
| Returned for revision | `returned` — subject “Revision requested”; body states whether resubmit will resume or restart | — |
| Resubmit with resume | `resubmitted_resume` — “Request resubmitted — resumed” | `approval_assigned_revised` — “Revised approval required” |
| Resubmit with full restart | `resubmitted_restart` — “Request resubmitted — full restart” | `approval_assigned_revised` — “Revised approval required” |
| First submit | `submitted` | `approval_assigned` |

### Parallel bands

- On step activation, each pending peer receives `approval_assigned` or `approval_assigned_revised` (email + in-app).
- When parallel mode is **any** or **N of M** and quorum is met, remaining pending peers receive `approval_no_longer_needed` so their inbox is not left stale.
- Manual follow-up fans out to every pending approver on the current step (same cooldown for the band).

### Reroute and exclusive skip path

| Situation | Email / in-app |
|-----------|----------------|
| Admin reroutes pending approval | New assignee: `approval_assigned`. Previous assignee: `approval_rerouted` (reason included). |
| Exclusive If/Else or ladder skips unmatched step orders | Requestor: `workflow_steps_skipped` digest (skipped step numbers + now awaiting step). Fired after compile on submit/resubmit when condition-gated steps are omitted, and on advance when intermediate orders are skipped. |

---

## Environment (Microsoft 365 — recommended)

Set on the **API** host (`.env` / ECS task / Docker `backend/.env.docker`):

```env
QUEUE_CONNECTION=redis
TOWEROS_NOTIFICATIONS_MAIL_MAILER=smtp
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-app-password-or-secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="TowerOS"
```

Use a **Microsoft 365 mailbox** with SMTP AUTH enabled, or an app registration–backed relay your infra team approves. Entra **sign-in** settings (Administration → Sign-in & security) are unrelated to SMTP — do not confuse them.

### AWS production (SES)

```env
TOWEROS_NOTIFICATIONS_MAIL_MAILER=ses
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-southeast-1
```

### Local development

- `MAIL_MAILER=log` — messages go to `storage/logs/laravel.log` only (not inboxes).
- For a real inbox locally, use SMTP to a dev relay or set `MAIL_MAILER=smtp` with your M365 test mailbox.

**Docker:** Put SMTP settings in **`backend/.env`** only. Do **not** set `MAIL_MAILER=log` in `backend/.env.docker` — Compose `env_file` overrides `.env` and mail will never reach Mailtrap/M365 until you remove those lines and `docker compose restart api`.

`TOWEROS_GATE_APPROVAL_MAIL_MAILER` is still supported as an alias; prefer **`TOWEROS_NOTIFICATIONS_MAIL_MAILER`** for all modules.

---

## Queue worker (required when `QUEUE_CONNECTION=redis`)

Workflow emails implement `ShouldQueue`. Without a worker, jobs sit in Redis and **no email is sent**.

```bash
php artisan queue:work redis --queue=toweros-notifications
# or Horizon in production
```

With `QUEUE_CONNECTION=sync`, jobs run inline after the HTTP response (acceptable for local smoke tests).

---

## Deep links in email

Notification URLs use the tenant’s primary domain (e.g. `http://atc.localhost/project-one/gate-approvals`), not bare `http://localhost`. Subject lines and the mail header use the **tenant slug** (e.g. `[ATC]`), not `TowerOS`.

Optional: set `TOWEROS_TENANT_APP_URL` only when you need a non-default scheme/port; hostname always comes from the tenant domain record.

---

## Verify delivery

1. Configure SMTP/SES as above; ensure mailer is **not** `log`.
2. Log in as `e_approval_admin` → **E-APPROVAL → Settings**.
3. Call **`POST /api/v1/e-approval/settings/test-email`** (or use the Settings UI **Send test email** when available).
4. Submit a test request as requestor → check requestor inbox for **Request submitted**.
5. Check approver inbox for **Approval required**.
6. Approve → requestor receives **Request approved**.

### API test email

```http
POST /api/v1/e-approval/settings/test-email
Authorization: Bearer {token}
X-Tenant-Id: {tenant-uuid}
```

Returns `sent_to` and `mailer`. Rejects `log` mailer with a clear validation error.

---

## SLA reminder emails

Schedule per tenant (or all tenants):

```bash
php artisan e-approval:sla-run --domain=alliance.localhost
```

Use cron / Laravel scheduler in production (e.g. every 15–60 minutes).

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| No email at all | `notifications_mailer` = `log`? Queue worker running? |
| Test email 422 | Mail still on `log` — set `TOWEROS_NOTIFICATIONS_MAIL_MAILER=smtp` |
| SMTP auth failed | M365 SMTP AUTH, correct app password, FROM matches licensed mailbox |
| Delayed email | Redis queue backlog — scale workers |
| Submit/approval API fails with SMTP error | Fixed: mail is sent after the HTTP response and failures are logged, not returned to the client. Mailtrap free tier may rate-limit (`550 Too many emails per second`) — wait or upgrade; workflow still completes. |
| Wrong link in email | Tenant primary domain in central `domains` table |

---

## Legacy formbuilder

The old app’s `test-email` route and any Graph sidecar for mail are **not** used. TowerOS sends mail only through Laravel `config/mail.php` and `toweros.notifications_mail_mailer`.
