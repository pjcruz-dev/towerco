# Documents — Phase 3

## Gate enforcement (done)

Project-One rollout gates cannot be marked **passed** (or bulk backfilled as passed) until the linked site binder checklist is complete.

### Behaviour

1. Applies only when the **documents** module is enabled and `TOWEROS_DOCUMENTS_GATE_ENFORCEMENT=true`.
2. Applies to phase keys in `toweros.documents.gate_enforcement.phase_keys` (default: `moc_col`, `col_social`, `pre_assessment`, `site_license`).
3. Resolves site from `rollout_programs.site_id` or `document_site_workspaces.rollout_program_id`.
4. Each folder in `toweros.documents.gate_required_node_keys` must have at least one **final** document.
5. **Waived** and **failed** statuses are not blocked.
6. Rollout detail API includes `document_binder_gate` on each enforced timeline phase.

### API error (422)

```json
{
  "message": "Site binder checklist is incomplete. Upload final documents to: COL, Affidavit.",
  "errors": { "gate_status": ["..."] }
}
```

### Config (`backend/config/toweros.php`)

```php
'gate_required_node_keys' => ['saq_phase_1', 'col', 'affidavit'],
'gate_enforcement' => [
    'enabled' => true,
    'phase_keys' => ['moc_col', 'col_social', 'pre_assessment', 'site_license'],
],
```

### Ops

No new migration. Restart API after deploy if config cache is used.

---

## Expiry notifications (done)

- Command: `documents:expiry-notify` (scheduled daily 07:00)
- Dedup: `document_expiry_alerts` per document + window (90/60/30)
- Notifies users with `documents:view` via tenant notification center
- Module: `documents`, type: `document_expiring`

## Editable binder template (done)

- Table: `document_binder_templates`
- `GET/PUT /documents/binder-template`, `POST /documents/binder-template/reset`
- New site binders use tenant template; existing sites unchanged
- UI: `/documents/settings`

## Rollout lease_package migration (done)

Copies Project-One candidate `lease_package` files (`lease_document` context) into the site Documents binder.

### Behaviour

- **Auto:** When a candidate is selected and Documents + Sites modules are enabled, lease files migrate into the site binder.
- **Manual API:** `POST /project-one/rollouts/{rollout}/documents/migrate-lease-package` (`documents:manage` + `project_one:rollout:view`)
- **Backfill:** `php artisan documents:migrate-lease-packages` (optional `--rollout=`, `--domain=`, `--tenants=*`)
- **Target folder:** Lessor “Documents” folder when `lessor_name` is set; otherwise **COL**
- **Dedup:** `documents.source_rollout_file_id` unique — safe to re-run
- **Rollout API:** Migrated files expose `document_id` and `document_href` on `lease_package.documents[]`
- **UI:** Site binder → **Import lease package** when a rollout is linked

Original rollout files remain in place (copy, not move).

---

## CAD file types + presigned direct upload (done)

Engineering CAD files (DWG, DXF, DWF, etc.) are allowed in the site binder. Large files and CAD uploads use **presigned PUT** to S3 when tenant files are on the `s3` disk; local dev keeps multipart API upload as fallback.

### CAD validation

- Extensions: `toweros.documents.cad_extensions` (default: `dwg`, `dxf`, `dwf`, `dgn`, `step`, `stp`, `iges`, `igs`, `ifc`)
- CAD files may use `application/octet-stream` or listed `cad_mimes`
- Standard office/PDF/image MIME allow-list unchanged

### Presigned flow (S3 only)

1. `GET /documents/upload-capabilities` — client discovers `direct_upload_enabled`, size thresholds, CAD extensions
2. `POST /sites/{site}/documents/files/presign` — returns `upload_url`, `upload_token`, `upload_headers`
3. Client **PUT** file directly to S3
4. `POST /sites/{site}/documents/files/complete` — creates document + version from stored object

### Config

```php
'cad_extensions' => ['dwg', 'dxf', 'dwf', ...],
'presigned_upload_enabled' => true,
'presigned_upload_ttl_minutes' => 15,
'presigned_upload_min_kb' => 10240,  // files >= 10 MB use presign when S3
```

Env: `TOWEROS_DOCUMENTS_PRESIGNED_UPLOAD`, `TOWEROS_DOCUMENTS_PRESIGNED_TTL_MINUTES`, `TOWEROS_DOCUMENTS_PRESIGNED_MIN_KB`

### Local dev

When `TOWEROS_TENANT_FILES_DISK=tenant_files`, presign returns **422**; UI falls back to multipart upload automatically.

### Migration

`document_upload_intents` — tracks pending presigned sessions (`upload_token`, `stored_path`, `expires_at`, `consumed_at`).

```bash
docker compose exec api php artisan toweros:migrate --tenants-only
```

### UI

Site binder upload uses smart routing: presigned for CAD or files above `presigned_min_bytes` when S3 is enabled; otherwise standard multipart.

---

## Remaining Phase 3

| Item | Status |
|------|--------|
| CAD types + presigned direct upload | Done |
| Document detail drawer UI | Done |
| Workspace command palette UI | Done |

---

## Workspace command palette (done)

Documents appear in **⌘K / Ctrl+K** workspace search when the `documents` module is enabled.

### Backend

`DocumentSearchService::asWorkspaceResults()` — matches title, filename, or site code/name. Deep link: `/sites/{siteId}?document={documentId}` (or `/documents?document={id}` when no site).

### Frontend

- **Find · Documents** section in command palette (module-grouped entity results)
- `FileText` icon and **Documents · Document** label for search hits
- Quick action: **Documents expiring soon** → `/documents`
- Selecting a document opens the site binder (scrolls to panel) and the **detail drawer** via `?document=` query param

---

## Document detail drawer (done)

Right-side `Sheet` drawer for a single document. Opens from the site binder file list (click title) or Documents home expiring list.

### API

`GET /documents/files/{document}` — metadata, `download_url`, `versions[]`, `activities[]`

`POST /documents/files/{document}/versions` — multipart new version (drawer action)

### UI (`document-detail-drawer.tsx`)

- Download, upload new version, request approval (when permitted)
- Edit title, status, expiry (when `documents:upload`)
- Version history list
- Activity timeline (upload, metadata, approval, rollout migration)
