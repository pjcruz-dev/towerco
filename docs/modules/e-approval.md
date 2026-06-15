# E-APPROVAL module

TowerOS tenant module for digital forms, multi-step approvals, and audit — ported from `legacy/atcformbuiilder/` into the modular monolith (one deployment, all tenants).

**Auth:** TowerOS tenant users + Microsoft Entra only (no standalone formbuilder login).  
**Users:** Administration → Users + Spatie roles (`e_approval_admin`, `e_approval_approver`, `e_approval_requestor`).  
**Mail:** Laravel notifications via `TOWEROS_NOTIFICATIONS_MAIL_MAILER` (Microsoft 365 SMTP or AWS SES). See [e-approval-email.md](./e-approval-email.md). No legacy formbuilder Graph sidecar.

---

## Phase status

| Phase | Status | Scope |
|-------|--------|--------|
| **P0** | **Done** | Module shell: tenant schema, RBAC, API health + dashboard, sidebar |
| **P1** | **Done** | Forms, submissions, approvals, comments, attachments, notifications (mail + in-app) |
| **P2** | **Done** | Audit log, CSV export, form import/export, print layout, dashboard reporting KPIs |
| **P3** | **Done** | Legacy parity + automation |
| **P4** | **Done** | Decommission standalone app |
| **P5** | **Done** | Production UX + parity closure (delegation UI, master data bulk, DCF/reroute/doc links, form logo) |
| **P6** | **Done** | Automated legacy import, Entra manager approver, visual form builder |

---

## P0 (shipped)

### Backend

- `backend/app/Modules/EApproval/` — health + dashboard controllers/services
- `backend/database/migrations/tenant/2026_06_01_100000_create_e_approval_core_tables.php`
- Permissions in `TenantRbacBaselineService`
- Routes: `GET /api/v1/e-approval/health`, `GET /api/v1/e-approval/dashboard`

### Frontend

- `/e-approval` dashboard (KPI strip, schema badge)
- Sidebar group **E-APPROVAL**
- API client: `frontend/lib/api/modules/e-approval-api.ts`

### RBAC permissions

| Permission | Purpose |
|------------|---------|
| `e_approval:view` | Module access, dashboard |
| `e_approval:forms:manage` | Form builder, publish |
| `e_approval:submissions:create` | Submit requests |
| `e_approval:submissions:view` | Read submissions |
| `e_approval:approve` | Approval inbox actions |
| `e_approval:audit:view` | Audit log |
| `e_approval:settings:manage` | Tenant eApproval settings |

### Baseline roles

- `tenant_admin` — all permissions
- `viewer` — `e_approval:view`, `e_approval:submissions:view`
- `e_approval_admin`, `e_approval_approver`, `e_approval_requestor`

**My profile** (`/e-approval/profile`, `e_approval:view`) — signature and out-of-office delegation (approvers); module SLA/settings remain on `/e-approval/settings` (`e_approval:settings:manage`).

### Apply schema (existing tenants)

```bash
php artisan tenants:migrate
# or full refresh in Docker:
npm run dev:fresh
```

---

## P1 (shipped)

**Goal:** Requestors and approvers run end-to-end workflows inside TowerOS.

### Backend APIs

- `GET/POST /e-approval/forms`, `GET/PUT/DELETE /e-approval/forms/{form}`, `POST .../publish`, `POST /e-approval/forms/validate`
- `GET/POST /e-approval/submissions`, `GET .../submissions/{submission}`, `POST .../cancel`, `PUT .../resubmit`
- `GET/POST .../submissions/{submission}/comments`, `POST .../attachments`, `GET /e-approval/attachments/{attachment}`
- `GET /e-approval/approvals`, `POST /e-approval/approvals/{approval}/decide`
- `GET /e-approval/notifications`, unread count, mark read / mark all read
- Services: `EApprovalFormService`, `FormPublishService`, `SubmissionWorkflowService`, `ApprovalDecisionService`, `EApprovalDocumentSequenceService`, `EApprovalNotificationDispatcher`
- Migration: `2026_06_02_100000_add_snapshots_to_e_approval_submissions.php`

### Frontend routes

- `/e-approval/forms`, `/e-approval/forms/new`, `/e-approval/forms/[id]` — field/step editor (MVP; full drag-drop builder in a later phase)
- `/e-approval/submissions`, `/e-approval/submissions/new`, `/e-approval/submissions/[id]`
- `/e-approval/approvals` — inbox with `awaiting_me`

### P1 limitations (see P2–P3)

- No visual Form Builder parity (`FormBuilder.tsx`); JSON/table editor only
- Manager approver type skipped (no Graph manager lookup)
- No DCF gate, revision/resubmit flows, SLA runner, master data, PDF export
- No delegation table (approver = assigned `users.id` only)

---

## P2 (shipped)

- `GET /e-approval/audit` — paginated audit log (`e_approval:audit:view`)
- `GET /e-approval/submissions/export` — CSV (max 5000 rows, filters: status, form_id, from, to, search)
- `GET /e-approval/forms/{form}/export` — `atc-form-export` JSON download
- `POST /e-approval/forms/import` — import envelope as new form
- `GET|PUT|DELETE /e-approval/pdf-layout/{formId}` — print layout + template in `e_approval_settings`
- `GET /e-approval/submissions/{submission}/print` — JSON print payload (fields filtered by saved layout)
- Frontend: `/e-approval/audit`, export on submissions, import/export on form edit, checkbox **Print layout** editor, `/e-approval/submissions/[id]/print` in `(print)` route group
- Dashboard: submissions (30d), stale approvals (>3 days pending), recent audit strip

### P2 notes

- Print/PDF: Next.js page at `/e-approval/submissions/{id}/print` → browser **Print / Save as PDF** (no Composer PDF library).
- Layout: per-form JSON visibility list (`key`, `label`, `visible`) via `GET|PUT /e-approval/pdf-layout/{formId}`; checkbox editor on form edit. When no custom layout is saved, all submission values print.
- Full drag-drop PDF designer from legacy app is **not** ported (P3+ if needed).

---

## P3 (shipped)

- Master data: `GET/POST/PUT/DELETE /e-approval/master-data-sets`, rows CRUD + `POST .../rows/bulk`
- `GET /e-approval/master-data/{key}` — runtime lookups for form fields
- Submission: `POST .../revision`, `PUT .../dcf-resubmit`, `PUT .../resubmit`, `POST .../manual-follow-up`
- `POST .../document-links`, `DELETE /e-approval/document-links/{link}`
- `GET /e-approval/cash-advances/open` — open CA balance lookup
- `GET /e-approval/metadata` — roles + user emails for form builder
- `GET/PUT /e-approval/me/signature`, `POST/DELETE /e-approval/me/attachments`
- Delegation: `GET/POST /e-approval/delegations`, `DELETE .../delegations/{id}` (`e_approval_delegations` table)
- Settings: `GET/PUT /e-approval/settings`, `GET /e-approval/settings/public` (SLA minutes, follow-up cooldown, delegation UI flag)
- SLA: `php artisan e-approval:sla-run` (scheduled every 5 minutes in `bootstrap/app.php`)
- Legacy import stub: `php artisan e-approval:import-legacy --tenant=…` (requires `legacy_formbuilder` DB connection)
- Frontend: `/e-approval/settings`, `/e-approval/master-data`; submission detail actions (revision, follow-up, resubmit)

### Document control gate (DCF)

Configure on form `metadata_json.documentControlGate` with `afterStepOrder` and field names. When workflow completes that step, submission moves to `awaiting_dcf`; requestor uses **DCF resubmit** with updated field values.

---

## P4 (shipped) — Decommission standalone app

- Standalone app moved to `legacy/atcformbuiilder/` (see `legacy/README.md`, `ARCHIVED.md`)
- Root `docker-compose.yml` has no formbuilder service
- `docs/local-development-docker-guide.md` documents TowerOS-only local stack
- Per-tenant data cutover: `php artisan e-approval:import-legacy --tenant=<uuid>` (mapping script still manual)

---

## P5 (shipped) — Production UX + parity closure

**Goal:** Close remaining operator-facing gaps for production cutover without porting the legacy drag-drop builder.

### Backend

- `POST /e-approval/approvals/{approval}/reroute` — admin reroute pending step (`e_approval:forms:manage`)
- `POST /e-approval/forms/{form}/logo` — tenant-scoped logo upload → `brand_logo_url` (`/storage/tenant/...`)

### Frontend

- **Settings:** delegation panel when `feature_delegation_ui` is enabled and user has `e_approval:approve`
- **Master data:** row add/delete + bulk JSON import
- **Submission detail:** DCF resubmit (`awaiting_dcf`), document links, admin reroute
- **Form edit:** logo upload + `metadata_json` editor for `documentControlGate`

## P6 (shipped) — Legacy import, manager approver, visual builder

### Automated legacy import

```bash
# Configure LEGACY_FB_* in .env (see config/database.php connection legacy_formbuilder)
php artisan e-approval:import-legacy --tenant=<uuid> --dry-run
php artisan e-approval:import-legacy --tenant=<uuid>
php artisan e-approval:import-legacy --tenant=<uuid> --only=forms,submissions
```

Maps legacy `users` → TowerOS `users` by **email**, preserves form/submission UUIDs where possible, imports master data, settings, and `users.delegated_to` → `e_approval_delegations`.

### Manager approver (Entra ID)

Workflow steps with `type: manager` resolve the requestor's direct manager via Microsoft Graph using the same per-tenant app registration as **Administration → Tenant settings → Microsoft Entra ID**. Auto-provision managers under **E-Approval → Settings → Workflow & Entra**.

### Visual form builder

`/e-approval/forms/[id]` uses drag-and-drop field ordering (`@dnd-kit`), expanded field types (select, approver, section, etc.), workflow step type **Direct manager (Entra ID)**, and live field preview.

**Deep dive:** [e-approval-form-builder.md](./e-approval-form-builder.md) — tabs, layout model, templates, versions, validation, and requestor submit routes.

### Still deferred (optional backlog)

| Priority | Item |
|----------|------|
| Medium | Visual template builder (tenant templates use JSON admin at `/e-approval/forms/templates` today) |
| Medium | Central **platform** E-Approval analytics (tenant-level stats exist on `/e-approval/forms`) |
| Low | Advanced formulas / calculated fields UI |
| Low | Richer revision diff (field-level compare beyond snapshot summary) |
| Low | Server-generated PDF (today: browser print from layout JSON) |

### Recently shipped (requestor + builder UX)

- Draft submissions (`status: draft`), save/resume/submit APIs, autosave on compose form
- Focused submit: `/e-approval/focus/{formId}` (no sidebar)
- Form builder: catalog drag-and-drop, blank multi-column rows, scrollable catalog/properties
- Toast notifications auto-dismiss after 5 seconds (manual dismiss still available)

---

## Legacy API inventory (`atcformbuiilder`)

Use this checklist when implementing P1–P3:

| Area | Legacy routes | TowerOS target |
|------|---------------|----------------|
| Auth | `auth/login-local`, `register-local`, `logout`, `me`, `url` | **Drop** — TowerOS auth |
| Users | `users/*`, `admin/create-user`, `import-users`, `export-users` | **Drop** — Admin → Users |
| Forms | `forms`, `forms/[id]`, `validate`, `import`, `export`, `logo` | `e-approval/forms/*` |
| Submissions | `submissions`, `[id]`, `comments`, `cancel`, `revision`, `resubmit`, `dcf-resubmit`, `manual-follow-up`, `export` | `e-approval/submissions/*` |
| Approvals | `approvals/[id]` | `e-approval/approvals/*` |
| Notifications | `notifications`, `unread-count`, `mark-all-read`, `[id]/read` | `e-approval/notifications/*` |
| Audit | `audit` | `e-approval/audit` |
| Settings | `settings`, `settings/public`, `test-email` | `e-approval/settings` + `POST .../settings/test-email` (TowerOS mail) |
| Master data | `admin/master-data-*`, `master-data/[key]` | `e-approval/master-data/*` |
| PDF | `pdf-layout/[formId]` | `e-approval/pdf-layout/*` |
| Admin | `impersonate`, `stats`, `reroute`, rate-limit | **Drop** or platform-only |
| Misc | `metadata`, `cash-advances/open` | P3 if needed |

---

## Data model

Tenant tables prefixed `e_approval_*` (see migration). **No** duplicate `users` table — all FKs reference `users.id`.

Source reference: `legacy/atcformbuiilder/src/server/db.ts`

---

## Local verification

1. `npm run dev:fresh` (or `php artisan tenants:migrate` for existing tenants)
2. Log in as tenant admin on a seeded tenant
3. Open **E-APPROVAL → Dashboard**
4. Confirm API: `GET /api/v1/e-approval/health` returns `schema_ready: true`

See also:

- [e-approval-email.md](./e-approval-email.md) — Microsoft 365 / SES mail, queue worker, test email
- [e-approval-api-parity.md](./e-approval-api-parity.md) — legacy route mapping and P4 sign-off
- [e-approval-go-live-checklist.md](./e-approval-go-live-checklist.md) — per-tenant rollout, roles, smoke tests
