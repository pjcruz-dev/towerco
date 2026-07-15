# Documents — Phase 2

Extends [Phase 1](documents-phase1.md) with E-Approval integration, workspace search, binder template admin (read-only), rollout link UI, and gate checklist.

## Delivered in Phase 2

| Feature | Backend | Frontend |
|---------|---------|----------|
| Workspace search | `DocumentSearchService` → `GET /workspace/search` documents group | Deep links to site binder (`/sites/{id}`) |
| E-Approval request | `POST /documents/files/{document}/request-approval` | Request approval on file row + form picker |
| Approval sync | `e_approval_submission_id`, `approval_status` on `documents` | Approval column + link to submission |
| Rollout link | Existing `PATCH /sites/{site}/documents/workspace` | Rollout dropdown in site binder header |
| Gate checklist | `GET /sites/{site}/documents/gate-checklist` | Badge row for required folders |
| Binder template | `GET /documents/binder-template` (read-only) | `/documents/settings` for PMO/admin |

## API additions

| Method | Path | Permission |
|--------|------|------------|
| GET | `/documents/binder-template` | `documents:template:manage` |
| POST | `/documents/files/{document}/request-approval` | `documents:upload` + `e_approval:submissions:create` |
| GET | `/sites/{site}/documents/gate-checklist` | `documents:view` |

## E-Approval field auto-map

When requesting approval, TowerOS pre-fills form values when field **names** match:

`document_title`, `title`, `document_id`, `toweros_document_id`, `site_code`, `site`, `folder`, `notes`, etc.

Use a published form with at least one approver step. On approval, document `approval_status` syncs to `approved` and draft documents may be promoted to `final`.

## Gate checklist

Configured in `toweros.documents.gate_required_node_keys` (default: `saq_phase_1`, `col`, `affidavit`). Each required binder folder must have at least one **final** document to count as met.

## Phase 3 (pending)

- Expiry email/in-app notifications (90/60/30 day job)
- Editable per-tenant binder template (`PUT` + admin UI)
- Migrate rollout `lease_package` files into Documents
- CAD file types, presigned direct upload
- Full document detail drawer (activity timeline, version upload UI, reorder)
- Workspace command palette UI group for documents (API ready)

Gate enforcement is documented in [documents-phase3.md](documents-phase3.md).

## Ops

After deploy:

```bash
docker compose exec api php artisan toweros:migrate --tenants-only
docker compose restart api web
```
