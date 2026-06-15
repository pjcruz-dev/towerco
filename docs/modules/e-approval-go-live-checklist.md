# E-Approval — tenant go-live checklist

Use this checklist when moving a tenant from standalone `legacy/atcformbuiilder` to TowerOS E-Approval.

Related: [e-approval.md](./e-approval.md) · [e-approval-api-parity.md](./e-approval-api-parity.md)

---

## 1. Prerequisites

- [ ] TowerOS deployed with E-Approval module (P0–P6 merged)
- [ ] Tenant database migrated: `php artisan tenants:migrate` (or `npm run dev:fresh` locally)
- [ ] Tenant RBAC baseline includes `e_approval:*` permissions (`TenantRbacBaselineService`)
- [ ] **Administration → Tenant settings → Microsoft Entra ID** configured if using sign-in with Microsoft or **manager** workflow steps (per-tenant app registration; no global `.env` keys required)
- [ ] MFA is **off by default** for new tenants; enable per tenant in platform console when ready (`tenants.mfa_required`)
- [ ] TowerOS mail transport configured — see [e-approval-email.md](./e-approval-email.md) (`TOWEROS_NOTIFICATIONS_MAIL_MAILER=smtp` for Microsoft 365 or `ses` for AWS; **not** legacy formbuilder mail)
- [ ] `POST /e-approval/settings/test-email` succeeds for an admin (inbox receives test message)
- [ ] Queue worker on `toweros-notifications` when `QUEUE_CONNECTION=redis`

---

## 2. Role assignment matrix

Assign roles under **Administration → Users** (or Entra group → role mapping).

| Role | Permissions | Typical users |
|------|-------------|---------------|
| `tenant_admin` | All module + platform admin | IT / tenant owners |
| `e_approval_admin` | Forms, settings, audit, approve, submit | Form builders, process owners |
| `e_approval_approver` | View, submissions (read), approve | Line managers, approvers |
| `e_approval_requestor` | View, create/view own submissions | Staff submitting requests |
| `viewer` | View dashboard + read submissions only | Read-only stakeholders |

### Sidebar visibility (automatic)

| Nav item | Permission required |
|----------|---------------------|
| E-APPROVAL group | `e_approval:view` |
| Dashboard, Forms | `e_approval:view` |
| Submissions | `e_approval:submissions:view` |
| Approvals | `e_approval:approve` |
| **My profile** (signature, delegation) | `e_approval:view` |
| Audit log | `e_approval:audit:view` |
| Settings, Master data | `e_approval:settings:manage` |

**Note:** Approvers set signature on **E-APPROVAL → My profile** (`/e-approval/profile`), not on Settings (admin-only).

---

## 3. Legacy data import (if applicable)

Skip if starting fresh in TowerOS.

1. Configure legacy MySQL in `backend/.env`:

   ```env
   LEGACY_FB_HOST=127.0.0.1
   LEGACY_FB_PORT=3306
   LEGACY_FB_DATABASE=flow_architect
   LEGACY_FB_USERNAME=root
   LEGACY_FB_PASSWORD=...
   ```

2. Ensure every legacy user email exists in **Administration → Users** (import maps by email only).

3. Dry run:

   ```bash
   php artisan e-approval:import-legacy --tenant=<TENANT_UUID> --dry-run
   ```

4. Review warnings (unmapped users, duplicate forms).

5. Import:

   ```bash
   php artisan e-approval:import-legacy --tenant=<TENANT_UUID>
   ```

6. Optional partial runs: `--only=forms,submissions,master-data,settings,delegations`

---

## 4. Module configuration (admin)

Log in as `e_approval_admin` or `tenant_admin`.

- [ ] **E-APPROVAL → Settings** — set SLA reminder/escalation minutes, follow-up cooldown
- [ ] Enable **Show delegation UI** if approvers use out-of-office delegation
- [ ] **E-APPROVAL → Master data** — verify/import lookup sets used by forms
- [ ] Publish or re-publish critical forms after import
- [ ] **Form edit** — verify print layout, logo, `metadata_json` document control gate if used

---

## 5. Smoke tests by role

Run on the **pilot tenant** before wider cutover.

### A. Requestor (`e_approval_requestor`)

| # | Step | Expected |
|---|------|----------|
| 1 | Open **E-APPROVAL → Dashboard** | KPIs load, `schema_ready: true` via API |
| 2 | **Submissions → New** | Published form list; fields render (text, select, date, etc.) |
| 3 | Submit a test request | Document number assigned; status pending; requestor + approver receive email (if SMTP configured) |
| 4 | Open submission detail | Values visible; cancel/follow-up if applicable |
| 5 | **My profile** | Save signature (optional) |

### B. Approver (`e_approval_approver`)

| # | Step | Expected |
|---|------|----------|
| 1 | **Approvals** (`awaiting_me=1`) | Test submission appears |
| 2 | Approve or reject with remarks | Status updates; requestor notified |
| 3 | Request revision (if enabled) | Submission returned |
| 4 | **My profile** | Signature saves without needing Settings access |
| 5 | Delegation (if UI enabled) | Create/revoke acting approver |

### C. Admin (`e_approval_admin` / `tenant_admin`)

| # | Step | Expected |
|---|------|----------|
| 1 | **Forms → Edit** | Visual builder saves fields + workflow steps |
| 2 | Manager step form | Submit as user with Entra manager → approval assigned |
| 3 | **Audit log** | Actions recorded |
| 4 | CSV export (submissions) | Download succeeds |
| 5 | Admin reroute (pending approval) | Reassigns approver |
| 6 | Form logo upload | `brand_logo_url` set |
| 7 | Print `/e-approval/submissions/{id}/print` | Browser print/PDF works |

---

## 6. API health (optional automation)

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Id: $TENANT_UUID" \
  http://localhost:8000/api/v1/e-approval/health
```

Expect `schema_ready: true`.

```bash
php artisan e-approval:sla-run --domain=<tenant-domain>
```

No errors; overdue reminders only for real pending approvals.

```bash
# In Docker
docker compose exec backend php artisan test --filter=EApproval
```

---

## 7. Production cutover

- [ ] Communicate new URL (TowerOS tenant app, **E-APPROVAL** sidebar)
- [ ] Disable standalone formbuilder hosts (nginx/pm2/DNS)
- [ ] Monitor first 48h: failed jobs, mail delivery, Graph manager failures in logs
- [ ] Keep `legacy/atcformbuiilder/` DB read-only backup until sign-off

---

## 8. Known limitations (accept or plan)

| Item | Notes |
|------|--------|
| File fields on submit | UI may store filename only; verify attachment API if binary required |
| Form logo URL | `/storage/tenant/...` must be served by your stack |
| Manager step | Skipped if Graph credentials missing or manager not found |
| Legacy builder | No full formula/layout-span UI from old `FormBuilder.tsx` |
| Import | Users must exist in TowerOS with matching emails |

---

## Sign-off

| Role | Name | Date | OK |
|------|------|------|-----|
| Tenant admin | | | |
| Process owner | | | |
| IT / platform | | | |
