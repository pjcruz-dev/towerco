# Rollout gate approvals — Phase D (Operational polish)

Phase D adds operational visibility, escalation reminders, acting approver delegation, audit export, dedicated mail transport, and realtime inbox refresh.

---

## What shipped

| Area | Deliverable |
|------|-------------|
| Dashboard | KPI **Awaiting my approval**, action queue link, preview widget |
| Inbox | **Awaiting me** tab, escalation badge, CSV export, delegation panel |
| Escalation | `gate_approval_escalation_working_days` tenant setting (default 3 WD) |
| Escalation job | `rollout:gate-approvals:escalate` (weekdays 08:00 schedule) |
| Delegation | `rollout_gate_approval_delegations` + acting approver resolution |
| Export | `GET /project-one/gate-approvals/export` CSV |
| Email | `TOWEROS_GATE_APPROVAL_MAIL_MAILER` wired on notifications (SMTP / SES) |
| Realtime | Echo invalidates gate-approvals + dashboard caches |

---

## Escalation

1. Tenant admin sets **Escalation after (working days)** on rollout playbook gate policies page.
2. Each approval step tracks `current_step_started_at`.
3. Scheduled command compares waiting working days (PH holiday-aware) to threshold.
4. Sends **escalated** email once per step; records `last_escalated_at`.

Manual run:

```bash
php artisan rollout:gate-approvals:escalate --domain=alliance.localhost
```

---

## Acting approver delegation

Approvers with `project_one:rollout:gate:approve` can delegate authority from the gate approvals inbox:

- **Delegate user ID** — tenant user UUID
- **Role scope** — optional (`saq`, `pmo`, etc.); blank = all roles you hold on rollouts
- Delegates can approve/reject steps where the delegator would have acted

API:

```http
GET  /api/v1/project-one/gate-approval-delegations
POST /api/v1/project-one/gate-approval-delegations
DELETE /api/v1/project-one/gate-approval-delegations/{id}
```

---

## Audit export

```http
GET /api/v1/project-one/gate-approvals/export?status=all
```

CSV columns: request id, rollout ref, phase, gate, status, chain, step log JSON, timestamps, notes.

Spatie `activity_log` also records `rollout.gate_approval_*` events including escalations.

---

## Email transport (SES on ECS)

```env
TOWEROS_GATE_APPROVAL_MAIL_MAILER=ses
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-southeast-1
```

For Microsoft 365:

```env
TOWEROS_GATE_APPROVAL_MAIL_MAILER=smtp
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

Ensure `QUEUE_CONNECTION=redis` and a worker on `toweros-notifications`.

---

## Realtime inbox

Set on frontend:

```env
NEXT_PUBLIC_SOCKET_ENABLED=true
NEXT_PUBLIC_PUSHER_APP_KEY=...
NEXT_PUBLIC_PUSHER_HOST=...
```

Gate approval actions broadcast `RolloutUpdated`; inbox and dashboard auto-refresh when Echo is enabled.

---

## Tenant migration

```bash
php artisan tenants:migrate --force
```

---

## Tests

```bash
php artisan test --filter=RolloutGateApproval
php artisan test --filter=RolloutGateApprovalDelegation
```

---

## Next: Phase E

Optional milestone grid alignment with unified timeline policy.
