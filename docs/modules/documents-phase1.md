# Documents — Phase 1

Site-scoped binder storage with AWS S3 (signed URLs in staging/prod), activity audit, and expiry tracking.

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Template | Platform default for all tenants (`DocumentBinderTemplateDefaults`) |
| Entry | Sites → Documents tab; `/documents` expiring home |
| Lessors | Repeatable per site (`Add lessor`) |
| Engineering | Fixed subfolders: Drawings, BOQ, Structural, As-built, Other |
| Approval | Phase 2 (E-Approval); Phase 1 status: draft / final / superseded |
| Template editors | Tenant admin + PMO (`documents:template:manage`) |
| S3 | One bucket, `{tenant_id}/documents/{site_id}/{document_id}/v{n}/` |
| Upload | API → S3; download via signed URL (60 min default) |
| Max size | 50 MB (`TOWEROS_DOCUMENTS_MAX_KB`) |
| Types | PDF, images, ZIP, Office (docx/xlsx/pptx) |
| Dev storage | Local `tenant_files` disk |
| Rollout files | Unchanged in Phase 1 |
| Delete | Soft delete |
| Versioning | New upload creates v2+; prior versions in `document_versions` |

## Default binder tree

```
eSite Binder (SAQ / Legal)
├── SAQ Phase 1
├── Lessors (repeatable)
│   └── Lessor N — {name}
│       └── Documents
└── Legal
    ├── COL
    └── Affidavit

eSite Folder (Engineering)
├── Drawings
├── BOQ / Estimates
├── Structural / Design
├── As-built
└── Other
```

## API (tenant)

| Method | Path | Permission |
|--------|------|------------|
| GET | `/sites/{site}/documents/workspace` | `documents:view` |
| PATCH | `/sites/{site}/documents/workspace` | `documents:manage` |
| POST | `/sites/{site}/documents/lessors` | `documents:upload` |
| GET | `/sites/{site}/documents/files?node_id=` | `documents:view` |
| POST | `/sites/{site}/documents/files` | `documents:upload` |
| PATCH | `/documents/files/{document}/metadata` | `documents:upload` |
| POST | `/documents/files/{document}/versions` | `documents:upload` |
| PATCH | `/sites/{site}/documents/files/reorder` | `documents:upload` |
| GET | `/documents/files/{document}/download` | `documents:view` |
| GET | `/documents/expiring?days=30` | `documents:view` |

## Module enablement

- Toggleable module key: `documents`
- Requires `sites` for site binders
- Professional + Enterprise: uploads enabled (see `billing.php`)

## Phase 2 (implemented)

See [documents-phase2.md](documents-phase2.md).

- E-Approval `Request approval`
- Binder template admin UI (read-only platform default)
- Workspace search documents group
- Gate checklist API + site binder UI
- PMO rollout link on site binder header

## Phase 3 (partial)

See [documents-phase3.md](documents-phase3.md).

**Done:** Gate enforcement, expiry notifications, binder template editing, lease_package migration

**Pending:** CAD/presigned upload, detail drawer UI, command palette UI
