# E-Approval Form Workspace

Per-form operational dashboards on top of the existing E-Approval engine (submit, approve, print, export).

## Pilot (Phase 1 — shipped)

**Form:** ISO Document Control (`form_family: iso_document_control`)  
**URL:** `/e-approval/w/iso-approval`  
**API:** `GET /api/v1/e-approval/workspaces`, `GET /api/v1/e-approval/workspaces/{slug}`

### Included

- KPI strip (pending, needs revision, approved/rejected 30d, awaiting you)
- Submissions table scoped to the form (`form_id` filter)
- Visibility: requestors see own + approver assignments; form admins / auditors see all for this form
- Actions: New request (focused ISO flow), Open detail, Print
- **Sidebar:** enabled workspaces appear as **top-level Operations items** (peer to Sites, E-Approval), not nested under E-Approval
- ISO upgrade command writes `metadata_json.workspace` on upgrade

### Enable workspace on existing ISO form

```bash
php artisan e-approval:upgrade-iso-form-revisions --domain=app.towerone.localhost --form=<form-uuid>
```

Published ISO forms with `form_family: iso_document_control` **do not** auto-enable the workspace sidebar entry. Enable workspace explicitly on the **Workspace** tab (or use **Apply ISO pilot defaults**).

### Enable workspace via form editor (Phase 2)

1. Open **E-Approval → Forms → Edit form**
2. Open the **Workspace** tab
3. Enable workspace, set slug/title/visibility, save and publish
4. Sidebar refreshes from `GET /api/v1/e-approval/workspaces` after save

ISO forms can use **Apply ISO pilot defaults** on the Workspace tab.

### Workspace export (Phase 3)

- **API:** `GET /api/v1/e-approval/workspaces/{slug}/export`
- **Who can export:** `e_approval:audit:view` auditors, or form coordinators with `e_approval:forms:manage` when **Show export on workspace** is enabled on the form
- **Row scope:** Same as the workspace list — requestors see own + approver assignments; coordinators with `workspace_all` visibility see all rows for that form
- **Query params:** `status`, `search`, `from`, `to`, `mine` (force own scope), `include_fields` (default true)
- **Columns:** Standard submission columns plus exportable form fields (text, select, date, etc.; skips sections, files, signatures)
- **Tenant-wide export** (`GET /e-approval/submissions/export`) still requires `e_approval:audit:view`

### Dashboard builder (Phase 4)

Configure on the form editor **Workspace** tab → **Dashboard layout**:

- **Widgets:** KPI strip, status breakdown, recent activity, submissions table (toggle + reorder)
- **Table columns:** system columns plus exportable form fields; **Auto-add form fields** picks the first text/select fields
- **Saved views:** preset filters (status, mine, last N days) shown as chips on the workspace page

**API additions:**

- `GET /api/v1/e-approval/workspaces/{slug}` — includes `dashboard`, `status_breakdown`, `recent_activity`, `available_columns`
- `GET /api/v1/e-approval/workspaces/{slug}/submissions` — paginated rows with `field_values` for configured columns

### ACL, grouping & audit (Phase 5)

Configure on the form editor **Workspace** tab → **Access & grouping**:

- **Allowed roles:** optional Spatie role allow-list on the workspace (`metadata_json.workspace.acl.roles`)
- **Enforce form restricted roles:** when enabled (default), also requires the viewer to match the form's `restricted_to` roles
- **Linked forms:** group additional published forms under one workspace slug (`metadata_json.workspace.forms.linked_form_ids`); linked forms cannot have their own workspace enabled
- **Bypass:** users with `e_approval:audit:view` or `e_approval:forms:manage` always have workspace access
- **Multi-form UI:** submissions table and recent activity show `form_name`; dashboard returns `is_multi_form` and `forms[]`
- **Workspace audit log widget:** optional dashboard widget backed by `recent_audit` from `e_approval_audit_logs` for the scoped forms/submissions
- **Command palette:** workspaces appear under **Go to** when the user can access them (`GET /api/v1/e-approval/workspaces`)
- **Breadcrumbs:** `E-Approval / Workspaces / {title}` on the workspace page

**Access enforcement:**

- `GET /api/v1/e-approval/workspaces/{slug}` and related endpoints return **403** when ACL or `restricted_to` checks fail
- Sidebar list (`GET /api/v1/e-approval/workspaces`) omits workspaces the viewer cannot access

---

## Phase status

| Phase | Scope | Status |
|-------|--------|--------|
| **1 — ISO workspace pilot** | KPIs, scoped submissions, sidebar nav, ISO defaults | **Done** |
| **2 — Workspace config UI** | Form editor tab: enable workspace, title, slug, visibility, nav, actions | **Done** |
| **3 — Scoped export** | Workspace CSV export for coordinators (not only `audit:view`); viewer-scoped rows | **Done** |
| **4 — Dashboard builder** | No-code KPI/widget palette, saved views, custom columns from form fields | **Done** |
| **5 — ACL & roles** | Per-workspace role mapping UI; enforce `restricted_to`; cross-form workspaces; command palette; breadcrumbs; audit log | **Done** |

All planned form workspace phases are complete.

### Form branding (logo)

- **Upload:** `POST /api/v1/e-approval/forms/{id}/logo` (requires `e_approval:forms:manage`)
- **Download:** `GET /api/v1/e-approval/forms/{id}/logo` (authenticated; used by form editor preview and print)
- Logos are stored on the tenant files disk; API responses expose `/api/v1/e-approval/forms/{id}/logo` (not a static `/storage/tenant/...` path)

---

## Unchanged by workspace

- Approval workflow and notifications
- Print layout and signatures
- Form publish / schema snapshots for in-flight submissions
