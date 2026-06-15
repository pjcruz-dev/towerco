# E-Approval — legacy API parity (P4 sign-off)

Standalone `legacy/atcformbuiilder` is **decommissioned**. TowerOS E-Approval is the only supported surface for forms, submissions, and approvals.

**Sign-off criteria:** All production workflows use TowerOS routes below. Intentional gaps are documented and accepted.

---

## Parity matrix

| Legacy area | Legacy routes | TowerOS | Status |
|-------------|---------------|---------|--------|
| Auth | `auth/login-local`, `register-local`, `logout`, `me` | TowerOS Sanctum + Entra | **Dropped** (by design) |
| Users | `users/*`, `admin/create-user`, import/export | Administration → Users | **Dropped** |
| Forms CRUD | `forms`, `forms/[id]` | `GET/POST/PUT/DELETE /e-approval/forms` | **Shipped** |
| Form validate | `forms/validate` | `POST /e-approval/forms/validate` | **Shipped** |
| Form import/export | `import`, `export` | `POST /e-approval/forms/import`, `GET .../export` | **Shipped** |
| Form logo | `forms/logo` upload | `POST /e-approval/forms/{form}/logo` → `brand_logo_url` | **Shipped** (P5) |
| Submissions | `submissions`, `[id]` | `GET/POST /e-approval/submissions` | **Shipped** |
| Comments | `comments` | `GET/POST .../comments` | **Shipped** |
| Cancel | `cancel` | `POST .../cancel` | **Shipped** |
| Revision | `revision` | `POST .../revision` | **Shipped** (P3) |
| Resubmit | `resubmit` | `PUT .../resubmit` | **Shipped** |
| DCF resubmit | `dcf-resubmit` | `PUT .../dcf-resubmit` | **Shipped** (P3) |
| Manual follow-up | `manual-follow-up` | `POST .../manual-follow-up` | **Shipped** (P3) |
| Submissions export | `export` | `GET /e-approval/submissions/export` | **Shipped** |
| Approvals | `approvals/[id]` | `GET /e-approval/approvals`, `POST .../decide` | **Shipped** |
| Notifications | `notifications/*` | `GET /e-approval/notifications/*` | **Shipped** |
| Audit | `audit` | `GET /e-approval/audit` | **Shipped** |
| Settings | `settings`, `settings/public` | `GET/PUT /e-approval/settings`, `settings/public` | **Shipped** (P3) |
| Test email | `test-email` | TowerOS mail / tenant admin | **Dropped** — use platform mail config |
| Master data admin | `admin/master-data-*` | `/e-approval/master-data-sets/*` | **Shipped** (P3) |
| Master data runtime | `master-data/[key]` | `GET /e-approval/master-data/{key}` | **Shipped** (P3) |
| PDF layout | `pdf-layout/[formId]` | `GET|PUT|DELETE /e-approval/pdf-layout/{formId}` | **Shipped** |
| Print | browser PDF | `GET .../print` + Next.js `/e-approval/submissions/[id]/print` | **Shipped** |
| Metadata | `metadata` | `GET /e-approval/metadata` | **Shipped** (P3) |
| Cash advances | `cash-advances/open` | `GET /e-approval/cash-advances/open` | **Shipped** (P3) |
| Delegation | user `delegated_to` | `e_approval_delegations` + API | **Shipped** (P3) |
| User signature / attachments | profile routes | `GET /e-approval/me/profile`, signature, attachments | **Shipped** (P3) |
| Document links | `document_links` | `POST .../document-links` | **Shipped** (P3) |
| Admin impersonate | `impersonate` | — | **Dropped** — platform-only if needed later |
| Admin reroute | `reroute` | `POST /e-approval/approvals/{approval}/reroute` | **Shipped** (P5) |
| Admin stats | `stats` | — | **Deferred** — platform analytics if needed |
| Visual FormBuilder UI | `FormBuilder.tsx` | Drag-drop builder + expanded types | **Shipped** (P6) — layout/formula UI partial |
| Manager approver | Graph manager lookup | `approver_type: manager` + Entra Graph | **Shipped** (P6) |
| SLA | in-process + cron | `e-approval:sla-run` + scheduler | **Shipped** (P3) |

---

## Deployment checklist (P4)

- [x] Root `docker-compose.yml` — no formbuilder service
- [x] `legacy/atcformbuiilder/` — archived, not in CI/deploy path
- [x] Tenant module routes under `/api/v1/e-approval/*`
- [x] Frontend `/e-approval/*` in main Next.js app
- [ ] Production tenants migrated (run `e-approval:import-legacy` per tenant if needed)
- [ ] Retire external nginx/pm2 for old `atcformbuilder` hostnames (ops)

---

## Verification commands

```bash
# Tenant context (replace tenant header / domain)
curl -s -H "Authorization: Bearer $TOKEN" -H "X-Tenant-Id: $TENANT" \
  http://localhost:8000/api/v1/e-approval/health

php artisan e-approval:sla-run --domain=atc.localhost
```

Frontend smoke: **E-APPROVAL** sidebar → Dashboard, Forms, Submissions, Approvals, Settings.
