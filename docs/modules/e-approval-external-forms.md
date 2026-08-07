# E-Approval — external (public) form links

Option B: vendors, lessors, and partners can submit published forms **without** a TowerOS login.

## Admin workflow

1. Publish the form (workflow must use **fixed approvers** — `user` or `approver` field types; **manager** steps are blocked for public links).
2. Open **E-Approval → Forms → {form} → Setup → External sharing**.
3. Choose an **internal sponsor** (tenant user who receives notifications and acts as requestor for workflow routing).
4. **Create public link** — URL can be re-copied anytime via **Copy URL** (encrypted at rest).
5. Optional: set expiry, max submissions, or link password.
6. **Revoke** or **rotate** links anytime.

## Sharing with vendors (ops users)

Users with `e_approval:submissions:create` (no form-edit permission required) can copy the newest active share link from:

**E-Approval → Submissions → New submission → Copy external link**

(Only shown when the form has an active re-copyable public link.)

Links created before re-copy support need a one-time **Rotate** under External sharing before they appear there.

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
| POST | `/api/v1/public/e-approval/forms/{token}/submissions` | Submit (`submitter_name`, `submitter_email`, `values`, optional `pending_attachment_counts`) |
| POST | `/api/v1/public/e-approval/forms/{token}/submissions/{id}/attachments` | Upload files (`upload_token` from submit response) |

### Master-data dropdowns (e.g. Site ID)

Public forms cannot call authenticated `GET /e-approval/master-data/{key}` or `/sites`. Instead, `GET …/forms/{token}` resolves `options.master_data_key` (including the builtin `sites` lookup and custom sets like `siteid`) into `options.choices` and strips the lookup key. The public UI uses those embedded choices only (`allowRemoteLookups=false`).

**Important:** a field API key of `site_id` normally activates the procurement Sites picker. If the field also has master-data or static choices, the normal select UI is used instead — otherwise the public form would call `/sites` and get 401.

Configure Site ID as **Master data → your set key** (e.g. `siteid`); do not duplicate a second public lookup API.

Tenancy resolves from the request hostname (`atc.localhost`) or `X-Tenant-Domain` header.

Rate limit: `e-approval-public` (default 30 req/min per IP + token).

Public attachment uploads: multipart with browser-set boundary; max size uses `toweros.tenant_files.max_size_kb` (default 25 MB). Failed uploads are reported to the submitter (submission is not treated as fully successful if files fail).

Required file fields are validated on create via `pending_attachment_counts` (selected file counts). Actual bytes are uploaded immediately after create with the returned `upload_token`.

## Authenticated admin / share API

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/e-approval/forms/{form}/public-links` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/forms/{form}/public-links` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/public-links/{link}/reveal` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/public-links/{link}/revoke` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/public-links/{link}/rotate` | `e_approval:forms:manage` |
| GET | `/api/v1/e-approval/forms/{form}/public-share-url` | `e_approval:submissions:create` or `e_approval:forms:manage` |

Published form index rows include `has_shareable_public_link` for the New submission picker.

## Data model

- `e_approval_public_form_links` — token hash, encrypted token ciphertext (for re-copy), sponsor, limits, counters
- `e_approval_submissions.submission_source` = `external`
- `external_submitter_name` / `external_submitter_email` — who filled the form
- `requestor_id` — internal **sponsor** (for workflow routing; not shown as “requestor” in emails)

## Notifications

| Recipient | Email | Content |
|-----------|-------|---------|
| Approver | `approval_assigned` | **Submitted by** external name + contact email; internal sponsor line; button opens the submission (`/e-approval/submissions/{id}?tab=workflow`) |
| Sponsor | `external_received` | Same submitter details; explains the request came via a public link |
| Sponsor | In-app | `public_submission_received` when the form is posted (no duplicate “submitted” in-app ping) |
| External submitter (opt-in) | `external_status_*` | See [e-approval-email.md](./e-approval-email.md) — tenant settings default **off** |

### Closed-loop (opt-in)

Tenant **E-Approval → Settings** toggles (all default off):

| Setting key | Effect |
|-------------|--------|
| `notify_external_on_received` | Ack email to `external_submitter_email` |
| `notify_external_on_approved` | Outcome email; includes secure package links when form `metadata.outbound` is enabled |
| `notify_external_on_rejected` | Outcome email with remarks |
| `notify_external_on_returned` | Revision email with `/public/e-approval/revise/{submissionId}?resubmit_token=…` |
| `teams_webhook_url` + `notify_teams_on_external_submit` | Teams Adaptive Card on new external submit |

Form metadata toggle for deliverables on approve (files are uploaded separately under **Setup → External deliverables**):

```json
{
  "outbound": {
    "email_package_on_approve": true
  },
  "revision": {
    "routing": "resume_returning_step"
  }
}
```

Upload ATC package files via:

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/e-approval/forms/{form}/outbound-files` | `e_approval:forms:manage` |
| POST | `/api/v1/e-approval/forms/{form}/outbound-files` | `e_approval:forms:manage` |
| DELETE | `/api/v1/e-approval/outbound-files/{id}` | `e_approval:forms:manage` |

Approved external emails include **clickable** Markdown links to `/public/e-approval/package-downloads/{token}`.

Public revise APIs (throttle `e-approval-public`):

| Method | Path |
|--------|------|
| GET | `/api/v1/public/e-approval/submissions/{id}/revise?resubmit_token=` |
| PUT | `/api/v1/public/e-approval/submissions/{id}/resubmit` |
| POST | `/api/v1/public/e-approval/submissions/{id}/attachments` (upload token) |
| GET | `/api/v1/public/e-approval/package-downloads/{token}` |

Email package links use the **frontend** path (so they open on the tenant host, not a bare `/api/v1` URL that Next.js would 404):

```
https://{tenant-host}/public/e-approval/package-downloads/{token}
```

That page calls the API above with `X-Tenant-Domain` and starts the file download.

## Security notes

- Only published forms; link can be revoked or rotated.
- Optional link password (bcrypt).
- Upload token expires (default 60 minutes, `E_APPROVAL_PUBLIC_UPLOAD_TOKEN_MINUTES`).
- Resubmit token TTL: `E_APPROVAL_PUBLIC_RESUBMIT_TOKEN_MINUTES` (default 7 days).
- Package download TTL: `E_APPROVAL_EXTERNAL_PACKAGE_DOWNLOAD_TOKEN_MINUTES` (default 7 days).
- Audit action: `public_submission_created`.
- External users cannot browse submission status in TowerOS; they use emailed revise / download links only.

## Apply schema

```bash
php artisan tenants:migrate
```

## Tests

```bash
php artisan test --filter=EApprovalPublicSubmissionTest
```
