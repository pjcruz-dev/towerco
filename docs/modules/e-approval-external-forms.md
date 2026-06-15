# E-Approval — external (public) form links

Option B: vendors, lessors, and partners can submit published forms **without** a TowerOS login.

## Admin workflow

1. Publish the form (workflow must use **fixed approvers** — `user` or `approver` field types; **manager** steps are blocked for public links).
2. Open **E-Approval → Forms → {form} → Setup → External sharing**.
3. Choose an **internal sponsor** (tenant user who receives notifications and acts as requestor for workflow routing).
4. **Create public link** — copy the URL immediately (token is only returned on create/rotate).
5. Optional: set expiry, max submissions, or link password.
6. **Revoke** or **rotate** links anytime.

## Public URL

```
https://{tenant-host}/public/e-approval/{access_token}
```

Example: `http://atc.localhost/public/e-approval/eyJpZCI6Li4ufQ`

The `access_token` is a base64url-encoded secret; do not share it in screenshots or email footers if sensitive.

## Public API (no auth)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/public/e-approval/forms/{token}` | Load form schema (`?access_password=` if required) |
| POST | `/api/v1/public/e-approval/forms/{token}/submissions` | Submit (`submitter_name`, `submitter_email`, `values`) |
| POST | `/api/v1/public/e-approval/forms/{token}/submissions/{id}/attachments` | Upload files (`upload_token` from submit response) |

Tenancy resolves from the request hostname (`atc.localhost`) or `X-Tenant-Domain` header.

Rate limit: `e-approval-public` (default 30 req/min per IP + token).

## Authenticated admin API

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/e-approval/forms/{form}/public-links` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/forms/{form}/public-links` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/public-links/{link}/revoke` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/public-links/{link}/rotate` | `e_approval:forms:manage` |

## Data model

- `e_approval_public_form_links` — token hash, sponsor, limits, counters
- `e_approval_submissions.submission_source` = `external`
- `external_submitter_name` / `external_submitter_email` — who filled the form
- `requestor_id` — internal **sponsor** (for workflow routing; not shown as “requestor” in emails)

## Notifications

| Recipient | Email | Content |
|-----------|-------|---------|
| Approver | `approval_assigned` | **Submitted by** external name + contact email; internal sponsor line; button opens the submission (`/e-approval/submissions/{id}?tab=workflow`) |
| Sponsor | `external_received` | Same submitter details; explains the request came via a public link |
| Sponsor | In-app | `public_submission_received` when the form is posted (no duplicate “submitted” in-app ping) |

External parties do not receive TowerOS email (no account).

## Security notes

- Only published forms; link can be revoked or rotated.
- Optional link password (bcrypt).
- Upload token expires (default 60 minutes, `E_APPROVAL_PUBLIC_UPLOAD_TOKEN_MINUTES`).
- Audit action: `public_submission_created`.
- External users cannot view submission status in TowerOS.

## Apply schema

```bash
php artisan tenants:migrate
```

## Tests

```bash
php artisan test --filter=EApprovalPublicSubmissionTest
```
