# Rollout Playbook — Phase 7 (Media & lease package)

**Status: implemented** — tenant file uploads, lease package UI, SAQ/CME/hunting attachments.

Replace URL-only evidence fields with tenant-scoped file uploads and complete SAQ lease documentation UI.

## Goals

- SAQ, CME, and hunting logs support **real file attachments**
- Candidates expose **`lease_package`** (structured JSON) in the UI
- Files are tenant-isolated, RBAC-protected, and audit-ready

## Backend

### 1. Tenant file storage service

| Item | Detail |
|------|--------|
| Service | `TenantFileStorageService` — store/resolve/delete under tenant prefix |
| Disk | `s3` in prod; `local` + `storage/app/tenant-files` in dev |
| Config | `config/toweros.php` → `tenant_files_disk`, max size, allowed mime types |
| Path pattern | `{tenant_id}/rollout/{rollout_id}/{context}/{uuid}.{ext}` |

### 2. Upload API

| Method | Path | Permission |
|--------|------|------------|
| POST | `/api/v1/project-one/files` | `project_one:saq:manage` or `project_one:cme:manage` (by context) |
| GET | `/api/v1/project-one/files/{file}` | `project_one:rollout:view` |

Multipart: `file`, `context` (`candidate_photo`, `hunting_log`, `cme_report`, `lease_document`), optional `rollout_id`.

Response: `{ "id", "url", "path", "mime_type", "size_bytes" }`

### 3. Wire existing JSON fields

Update services/controllers to accept:

- `photo_links` — array of `{ file_id, url, label? }` or validated URLs from upload response
- `lease_package` — structured object, e.g. `{ lessor_id_type, lease_term_months, documents: [...] }`

Validation rules in `SiteCandidateStore/Update`, `SiteHuntingLogStore`, `CmeDailyReportStore`.

## Frontend

| Surface | Change |
|---------|--------|
| SAQ tab | Photo upload on candidate create/edit; lease package section (term, rate, document uploads) |
| SAQ tab | Display photo thumbnails + download links |
| CME tab | Optional photo attachments on daily report form |
| Hunting log form | Optional photo attachments |

Use shared `FileUploadField` component (drag-drop + camera on mobile — full camera UX in Phase 13).

## Security

- Max file size (e.g. 10 MB), mime allowlist (`image/*`, `application/pdf`)
- Virus scan hook placeholder (no-op in dev)
- Signed/temporary URLs for private S3 objects

## Tests

```bat
cd backend
php artisan test --filter=TenantFileStorage
php artisan test --filter=RolloutCandidateMedia
```

## Verify locally

1. Create candidate with 2 photos + lease PDF on Alliance rollout SAQ tab
2. Submit CME report with site photo
3. Confirm files not accessible cross-tenant

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase6.md](./rollout-playbook-phase6.md)
