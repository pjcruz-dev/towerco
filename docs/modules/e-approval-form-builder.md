# E-Approval form builder

TowerOS E-Approval lets tenant administrators design **published forms** with multi-step approval workflows. Requestors submit requests; approvers act in the approvals inbox.

## Routes (frontend)

| Path | Purpose |
|------|---------|
| `/e-approval/forms` | Manage forms (draft / published) |
| `/e-approval/forms/templates` | Template admin (system + tenant) |
| `/e-approval/forms/create` | New form wizard → full editor |
| `/e-approval/forms/{id}` | Edit form (Design, Workflow, Preview, History, Print) |
| `/e-approval/submissions/new` | Pick a published form |
| `/e-approval/request/{formId}` | **Full-page submit** (requestor) |
| `/e-approval/focus/{formId}` | **Focused submit** (minimal chrome, new tab) |
| `/e-approval/submissions` | My submissions |
| `/e-approval/submissions/{id}` | Submission detail |

Legacy redirect: `/e-approval/forms/new` → `/e-approval/forms/create`.

## Create wizard (new forms)

`/e-approval/forms/create` opens a **5-step wizard** before the full tabbed editor:

1. **Start** — template gallery, import JSON, or blank
2. **Details** — name and description
3. **Fields** — starter pack (summary + approver, single field, or empty)
4. **Workflow** — at least one approval step
5. **Review** — create draft and open the editor on **Design**

Use **Skip to full editor** to use tabs immediately (Setup, Design, Workflow, etc.).

## Form editor tabs

| Tab | Description |
|-----|-------------|
| **Setup** | Name, description, status, branding, import/export, templates (new forms) |
| **Design** | Form canvas: fields, layout (width, rows), properties |
| **Workflow** | Approval steps (user, approver field, Entra manager) |
| **Preview** | Requestor-style full-width preview |
| **History** | Version timeline (saved / published snapshots) |
| **Print** | PDF print layout (existing forms) |

## Field types

| Type | Notes |
|------|--------|
| Short text, Long text | Default text inputs |
| Email, Phone, URL | Typed inputs + client/server validation |
| Number, Currency, Date | Standard inputs |
| Dropdown, Radio, Checkbox | Static choices or **master data** key |
| Approver picker | Select tenant user on the form |
| File upload | Attachment on submit (plan-gated) |
| Signature | Draw on canvas or type name |
| Rating | 1–N stars (configurable max) |
| Location | Lat/lng + optional label; geolocation button |
| Tags | Suggested chips + optional custom tags |
| Grid / line items | Repeatable rows with column types |
| Section, Divider | Structure and grouping |

## Layout (Phase B)

Stored on `field.options.layout`:

- **width:** `full` (100%), `half` (50%), `third` (33%), `quarter` (25%)
- **row_id** + **slot:** multi-column rows; drag fields into row slots on the canvas
- **row_columns:** `2`, `3`, or `4` — quick-add row with matching column widths (half / third / quarter)
- Drag from the **field catalog** palette onto the canvas or row slots

Preview and submit use the same **12-column grid** renderer (`EApprovalFormFieldsLayout`).

## Conditional visibility

Stored in `field.options.visibility`:

| Property | Values |
|----------|--------|
| `mode` | `show_when` or `hide_when` |
| `field` | API key of the controlling field |
| `operator` | `equals`, `not_equals`, `contains`, `filled`, `empty` |
| `value` | Compare value (for equals / not_equals / contains) |

Hidden fields are omitted in preview, submit UI, client validation, and server validation.

Configure on **Design → Field properties → Conditional visibility**.

## Field properties

Stored in `field.validation` (JSON):

| Property | UI | Submit |
|----------|-----|--------|
| `required` | Checkbox | Enforced client + server |
| `placeholder` | Text | Shown on inputs |
| `max_length` | Text (text/textarea) | Enforced client + server |
| `default` | Text | Pre-filled on open |
| `help_text` | Text | Hint below field |

**API key (name):** Auto-generated from label on draft fields; locked after publish or when the form has submissions.

## Submission parent links (Phase A1 + A2)

Child submissions (e.g. Liquidation) can link to a parent submission (e.g. Cash advance) via API:

```json
POST /api/v1/e-approval/submissions
{
  "form_id": "<liquidation-form-uuid>",
  "parent_submission_id": "<cash-advance-submission-uuid>",
  "values": { "total_reimbursement": "1200" }
}
```

Also supported on draft update/submit: `PUT .../submissions/{id}/draft`, `POST .../submissions/{id}/submit-draft`.

### Validation (A1)

- Parent must exist and belong to the **same requestor**
- Parent cannot be `draft`, `rejected`, or `cancelled`
- When child form `metadata_json.parent_form_family` is set, parent form `metadata_json.form_family` must match
- Reference fields are auto-filled when empty (`cash_advance_document_no`, `purchase_requisition_document_no`)

### Stricter rules (A2 — liquidation ↔ cash advance)

- Liquidation forms **require** `parent_submission_id` (`metadata_json.requires_parent_submission` or `form_family: liquidation` + `parent_form_family: cash_advance`)
- Parent cash advance must be **`approved`** (not merely pending)
- Parent must have **open balance** &gt; 0 (`requested_amount` minus sum of linked `total_reimbursement` on non-rejected children)
- **`total_reimbursement` is hard-blocked** when it exceeds open balance (includes cumulative liquidations against the same CA)
- Draft update/submit excludes the current submission from the balance calculation

Open cash advances: `GET /api/v1/e-approval/cash-advances/open` (approved parents with positive balance only).

### UI picker (A3)

When a published form requires a cash-advance parent (`requires_parent_submission` or liquidation metadata), the request compose panel shows **Cash advance to liquidate**:

- Loads open CAs from `GET /e-approval/cash-advances/open`
- Sends `parent_submission_id` on create, draft save, and submit
- Prefills `cash_advance_document_no` when empty
- Blocks save/submit until a CA is selected; client-side open-balance hint before server validation

Implemented in `EApprovalSubmissionComposePanel` + `EApprovalCashAdvancePicker` (request and focused submit routes).

### Prefill on link (A4)

When a parent is linked, empty child fields are auto-filled (client on picker select + server on save/submit via `enrichValues`):

| Parent family | Child field | Source |
|---------------|-------------|--------|
| `cash_advance` | `cash_advance_document_no` | Parent document no. |
| `cash_advance` | `liquidation_date` | Today (tenant date) |
| `cash_advance` | `notes` | Parent `purpose` |
| `cash_advance` | `department`, `currency` | Same-named parent fields (when present on child) |
| `purchase_requisition` | `purchase_requisition_document_no` | Parent document no. |
| `purchase_requisition` | `line_items`, `total_amount` | Parent `line_items`, `estimated_total` |

Open CA picker: `GET /e-approval/cash-advances/open?for_form_id={childFormId}` returns `prefill_values` per item for immediate UI fill.

Existing user-entered values are never overwritten.

### Hard block over-balance (A5)

`total_reimbursement` on liquidation (and other forms with that field linked to an approved cash advance) is **hard-blocked** when it exceeds the parent open balance:

| Layer | Behavior |
|-------|----------|
| Server | `assertChildAmounts()` on create, draft save/update/submit, and **resubmit**; cumulative liquidations count non-rejected children; current submission excluded on update/resubmit |
| UI | Live inline error on `total_reimbursement`, max-amount help text, warning alert, disabled Save/Submit, autosave paused while over balance |

Rejected child liquidations do not reduce open balance; returned/pending children do.

### Related submissions panel (A6)

Submission detail (`GET /e-approval/submissions/{id}`) includes `related_submissions`:

- `parent` — submission referenced by `parent_submission_id` (e.g. cash advance for a liquidation)
- `children` — submissions that link to the current record as parent (e.g. liquidations against a cash advance)

Each row includes document no., form name, status, and a key amount when available (`requested_amount`, `total_reimbursement`, etc.).

UI: **Summary** tab → **Related submissions** (above manual linked documents and related tickets).

### Feature tests (A7)

Regression coverage lives in `backend/tests/Feature/EApproval/EApprovalSubmissionParentLinkTest.php`.

| Scenario | Test |
|----------|------|
| CA → liquidation link + server prefill | `test_liquidation_submission_links_to_cash_advance_parent` |
| Open CA list + `prefill_values` | `test_open_cash_advances_include_prefill_values_for_child_form`, `test_open_cash_advances_lists_approved_parents_with_positive_balance` |
| Parent required / approved parent only | `test_liquidation_requires_parent_submission`, `test_liquidation_rejects_pending_cash_advance_parent` |
| Hard block over balance (create) | `test_liquidation_rejects_amount_over_open_balance` |
| Cumulative balance across children | `test_liquidation_respects_cumulative_open_balance` |
| Exact balance allowed | `test_exact_open_balance_liquidation_is_allowed` |
| Pending liquidations reduce open balance | `test_open_balance_reflects_pending_liquidations` |
| Rejected liquidations free balance | `test_rejected_liquidation_restores_open_balance_for_subsequent_liquidation` |
| Draft update / submit-draft / resubmit guards | `test_draft_update_rejects_amount_over_open_balance`, `test_submit_draft_rejects_amount_over_open_balance`, `test_resubmit_rejects_amount_over_open_balance` |
| Parent ownership + family | `test_parent_link_rejects_different_requestor`, `test_parent_link_rejects_wrong_parent_form_family` |
| Reimbursement standalone (no parent) | `test_reimbursement_submits_without_parent_link` |
| Prefill does not overwrite user values | `test_parent_prefill_does_not_overwrite_user_document_no` |
| Related submissions on detail | `test_submission_detail_includes_related_parent_and_children` |

Run:

```bash
docker compose exec api php artisan test tests/Feature/EApproval/EApprovalSubmissionParentLinkTest.php
```

## Procurement parent links (Phase B — PR → PO)

### Open purchase requisitions API (B1)

Approved PRs with remaining PO budget for the current requestor:

`GET /api/v1/e-approval/purchase-requisitions/open`

Optional query: `for_form_id={childPoFormId}` adds `prefill_values` per item (`purchase_requisition_document_no`, `line_items`, `total_amount` from `estimated_total`).

Response item fields:

| Field | Meaning |
|-------|---------|
| `estimated_total` | Approved PR amount |
| `committed_amount` | Sum of non-rejected linked PO `total_amount` values |
| `open_balance` | `estimated_total - committed_amount` |
| `requisition_title` | PR title when present on the form |

Permissions: `e_approval:submissions:create` or `e_approval:submissions:view`.

Tests: `backend/tests/Feature/EApproval/EApprovalPurchaseRequisitionOpenTest.php`

```bash
docker compose exec -T api php artisan test tests/Feature/EApproval/EApprovalPurchaseRequisitionOpenTest.php
```

### PO parent link validation (B2)

Purchase order forms with `parent_form_family: purchase_requisition` (or `requires_parent_submission: true`) enforce:

- `parent_submission_id` is **required** on create, draft save/update, submit-draft, and resubmit
- Parent PR must be **approved** with **open balance** &gt; 0 (`estimated_total` minus non-rejected child PO `total_amount` values)
- **`total_amount` is hard-blocked** when it exceeds the PR open balance (cumulative POs count)

Tests: `backend/tests/Feature/EApproval/EApprovalPurchaseOrderParentLinkTest.php`

### PR picker UI (B3)

When a published form requires a purchase-requisition parent (`purchase_order` + `parent_form_family: purchase_requisition`), the request compose panel shows **Purchase requisition to fulfill**:

- Loads open PRs from `GET /e-approval/purchase-requisitions/open?for_form_id={poFormId}`
- Sends `parent_submission_id` on create, draft save, and submit
- Prefills `purchase_requisition_document_no`, `line_items`, and `total_amount` when empty
- Blocks save/submit until a PR is selected; live `total_amount` open-balance validation

Components: `EApprovalPurchaseRequisitionPicker` + `EApprovalSubmissionComposePanel`.

### Related submissions on PR detail (B4)

Submission detail (`GET /e-approval/submissions/{id}`) `related_submissions` now includes procurement context:

| Field | Purpose |
|-------|---------|
| `context_form_family` | Current submission family (`purchase_requisition`, `cash_advance`, etc.) |
| `summary` | Budget card on PR/CA parents: total, committed (PO/liquidation totals), open balance |
| `children[].form_family` | Context-aware section titles (e.g. **Purchase orders** on PR detail) |
| `parent.form_family` | Parent link label (e.g. **Purchase requisition** on PO detail) |

UI: **Summary** tab → **Related submissions** shows the budget summary row and labeled parent/child sections.

Tests: `EApprovalPurchaseOrderParentLinkTest::test_pr_detail_lists_child_purchase_orders_and_budget_summary`

### Tenant finance & procurement policy (B5 + D4)

Per-tenant controls in `e_approval_settings` (managed via `GET|PUT /e-approval/settings` and UI at `/e-approval/settings`):

| Setting | Values | Default |
|---------|--------|---------|
| `liquidation_requires_parent` | `true` \| `false` | `true` |
| `liquidation_overspend_mode` | `block` \| `warn` | `block` |
| `liquidation_max_overspend_percent` | `0`–`25` (warn mode only) | `0` |
| `po_overspend_mode` | `block` \| `warn` | `block` |
| `po_max_overspend_percent` | `0`–`25` (warn mode only) | `0` |

| Mode | Behavior |
|------|----------|
| **block** | Hard-block totals above open balance |
| **warn** | Allows totals above open balance up to parent amount × (1 + percent/100) minus committed children; shows UI warning; writes structured `liquidation_overspend_allowed` or `po_overspend_allowed` audit entry (see D5) |

Policy is exposed to requestors on `GET /e-approval/metadata` as `finance_procurement_policy` for compose-panel validation.

Tests: `EApprovalFinanceProcurementPolicyTest.php`, `EApprovalLiquidationPolicyTest.php`

**Phase B (PR → PO) is complete.**

## Vendor registration → master data (Phase C)

### Auto-sync on approval (C1)

When a **vendor registration** submission (`form_family: vendor_registration`) reaches final **approved** status:

1. Ensures master data set `vendors` exists (creates if missing)
2. **Creates or updates** a row keyed by normalized `tax_id`
3. Maps `company_name`, `tax_id`, `vendor_category`, contact fields, address, services, and banking fields into `data_json`
4. Stores `source_submission_id` and `source_document_no` for traceability
5. Writes audit `vendor_master_data_created` or `vendor_master_data_updated`

Approved vendors appear immediately in the PO **Vendor** dropdown (`master_data_key: vendors`).

Hook: `EApprovalVendorRegistrationMasterDataService` from `ApprovalDecisionService` on final approval.

Tests: `backend/tests/Feature/EApproval/EApprovalVendorRegistrationMasterDataTest.php`

### Canonical field mapping (C2)

Approved vendor rows use schema version **1** (`config/e_approval_vendor_master_data.php`):

| Group | Keys |
|-------|------|
| Identity | `company_name`, `tax_id`, `tax_id_normalized`, `vendor_category` |
| Contact | `contact.name`, `contact.email`, `contact.phone` |
| Address | `address.registered` |
| Banking | `banking.bank_name`, `banking.account_no` |
| Compliance | `compliance_documents[]` — attachment refs (`id`, `file_name`, `field_name`) |
| Source | `source.submission_id`, `source.document_no`, `source.approved_at` |

Top-level legacy aliases (`contact_email`, `source_submission_id`, etc.) remain for backward compatibility.

`GET /e-approval/master-data/vendors` returns `subtitle` per option (`category · email · tax id`) for PO vendor dropdowns.

### Dedupe rules (C4)

`EApprovalVendorMasterDataDedupeService` prevents duplicate vendor rows when syncing approved registrations:

| Priority | Match key | Behavior |
|----------|-----------|----------|
| 1 | `tax_id_normalized` | Upsert by row `code` or stored tax ID (C1) |
| 2 | `company_name_normalized` | Merge into existing row when names match after suffix/punctuation normalization |

Guards:

- **Tax ID conflict:** If both rows have different non-empty tax IDs, company-name dedupe is skipped (separate legal entities).
- **Manual rows:** Legacy/manual vendor rows without a tax ID can be merged when a registration normalizes to the same company name.
- **Audit:** `vendor_master_data_deduped` (company-name match) or `vendor_master_data_updated` (tax-id match); `data_json.dedupe.matched_by` records the rule used.

Config: `backend/config/e_approval_vendor_master_data.php` → `dedupe`.

Tests: `EApprovalVendorRegistrationMasterDataTest.php`, `EApprovalVendorMasterDataDedupeServiceTest.php`.

**Phase C (vendor registration → master data) is complete.**

## Finance & procurement dashboard KPIs (Phase D1)

`GET /e-approval/dashboard` includes tenant-wide finance metrics:

| KPI | Meaning |
|-----|---------|
| `open_cash_advances` | Approved cash advances with remaining open balance |
| `unliquidated_cash_advances` | Open CAs with no liquidation filed yet (`reimbursed_amount = 0`) |
| `prs_without_po` | Approved purchase requisitions with no linked PO |

Response fields: `finance_kpis` (display cards), `finance_counts` (raw integers). Quick actions surface non-zero counts on the dashboard.

Service: `EApprovalFinanceProcurementKpiService` (reuses `EApprovalCashAdvanceService` and `EApprovalPurchaseRequisitionService` balance SQL).

Tests: `backend/tests/Feature/EApproval/EApprovalFinanceProcurementDashboardTest.php`

### Related form navigation (D2)

Finance & procurement templates declare `related_template_ids` in `metadata_json`. When forms are provisioned, TowerOS stores resolved UUIDs on `related_form_ids` for form-level navigation hints.

**Gallery bundle** — creates all six finance/procurement templates and wires links in one step:

`POST /e-approval/form-templates/finance-procurement-bundle`

| Template | Related templates |
|----------|-------------------|
| Cash advance | Liquidation, Reimbursement |
| Liquidation | Cash advance |
| Reimbursement | _(standalone)_ |
| Purchase requisition | Purchase order |
| Purchase order | Purchase requisition, Vendor registration |
| Vendor registration | Purchase order |

Single-template create (`POST /e-approval/form-templates`) also resolves `related_form_ids` when sibling forms already exist (matched by `created_from_template` or `form_family`).

Tests: `backend/tests/Feature/EApproval/EApprovalFinanceProcurementTemplateBundleTest.php`

### Document links on detail (D3)

Cross-reference submissions without `parent_submission_id`:

| API | Purpose |
|-----|---------|
| `GET /e-approval/submissions/{id}` | Returns `document_links`, `incoming_document_links`, `related_form_navigation` |
| `POST /e-approval/submissions/{id}/document-links` | Link to another submission (`target_submission_id`, optional `link_type`) |
| `DELETE /e-approval/document-links/{link}` | Remove an outgoing link (requires view access on source submission) |

- **Outgoing** links are created from the current submission.
- **Incoming** links show other submissions that reference this document.
- **Related form navigation** uses D2 `related_form_ids` for published sibling forms.
- Duplicate links are blocked; create/delete write audit `document_link_created` / `document_link_deleted`.

UI: `EApprovalDocumentLinksPanel` on `/e-approval/submissions/[id]`.

Tests: `backend/tests/Feature/EApproval/EApprovalDocumentLinkTest.php`

### Tenant policy UI (D4)

`/e-approval/settings` — finance & procurement section for admins (`e_approval:settings:manage`):

- Require linked cash advance on liquidation
- Liquidation over-balance mode and max overspend percent
- PO over-balance mode and max overspend percent

Compose helpers: `frontend/modules/e-approval/finance-procurement-policy.ts`

### Finance audit trail (D5)

`EApprovalSubmissionFinanceAuditService` records compliance events when parent links or warn-mode overspend policies are exercised.

| Action | When |
|--------|------|
| `parent_submission_linked` | Child submission is created (submit or draft) with a new parent |
| `parent_submission_changed` | Draft save or draft submit updates `parent_submission_id` |
| `parent_submission_unlinked` | Parent link is cleared on update |
| `liquidation_overspend_allowed` | Liquidation total exceeds open balance but is within tenant warn policy |
| `po_overspend_allowed` | PO total exceeds PR open balance but is within tenant warn policy |

`remarks` is JSON with parent/child document numbers, `parent_form_family`, amounts, `strict_open_balance`, `policy_max_amount`, and human-readable `message` for overspend events.

Hooked from `EApprovalSubmissionService` on create, draft save, draft submit, and resubmit (overspend only when parent already linked).

Tests: `backend/tests/Feature/EApproval/EApprovalSubmissionFinanceAuditTest.php`

**Phase D (polish & governance) is complete.**

## Workflow

- **Fixed user** — specific approver
- **From approver field** — value chosen on the form
- **Direct manager (Entra)** — resolved at submit from Microsoft Entra; test lookup on Workflow tab

Requires tenant Entra settings and E-Approval workflow settings (auto-provision manager users optional).

## Templates

Built-in templates (config: `backend/config/e_approval.php` + `e_approval_finance_procurement_templates.php`):

**HR & general**

- Leave request
- Employee onboarding

**Finance**

- Cash advance (`requested_amount` — used by open CA balance API)
- Liquidation (`total_reimbursement`, links to CA via document no.)
- Reimbursement (`total_reimbursement`)

**Procurement**

- Purchase request (CAPEX) — legacy capex template
- Purchase requisition (PR) — line items + procurement/finance approval
- Purchase order (PO) — vendor master data + PR reference
- Vendor registration — internal or public-link vendor intake

After creating from template, assign fixed approvers on **Workflow** (empty `user` steps) or publish when approver fields are used. Load vendors under **E-Approval → Master data** with key `vendors` for the PO vendor dropdown.

API: `GET /e-approval/form-templates`, `POST /e-approval/form-templates` with `{ "template_id": "leave_request" }`.

UI: gallery on **Forms** list and **New form → Setup**.

**Tenant template admin** — `/e-approval/forms/templates` stores custom definitions in `e_approval_settings` (`tenant_form_templates` JSON). System templates from config remain read-only.

**Admin statistics** — `GET /e-approval/admin/stats` (requires `e_approval:forms:manage`) shown on the Forms list page.

## Version history

Each **save** and **publish** appends a revision to `metadata_json.revisions` (max 30):

- Label e.g. `Draft v2`, `Published v3`
- Snapshot payload, field/step counts, actor, timestamp

**Restore** loads a snapshot into the editor via `POST /e-approval/forms/{id}/revisions/{n}/restore` (then save to persist). Version history is preserved on restore.

**Compare** — **vs current** (revision → editor state) or **vs previous** (revision → prior revision). Opens a diff dialog for form metadata, fields, and workflow steps.

## Publish flow

1. **Save** — persists draft or published definition; records a revision
2. **Publish** — runs save, then publishes (updates `published_snapshot`, bumps `schema_version`)
3. **Publish checklist** — blocking errors (name, fields, workflow, duplicate keys) and warnings

Only **published** forms appear on `/e-approval/submissions/new`.

## Submit flow (requestor)

1. Pick form on `/e-approval/submissions/new`
2. Open **`/e-approval/request/{formId}`** (full page)
3. Validation: required, email/phone/url, max length, grid rows, duplicate approver fields
4. `POST /e-approval/submissions` with `{ form_id, values }`
5. File fields upload attachments after create

Server: `EApprovalSubmissionValuesValidator`.

## API permissions (summary)

| Action | Permission |
|--------|------------|
| Manage forms | `e_approval:forms:manage` |
| View forms | `e_approval:view` |
| Create submission | `e_approval:submissions:create` |
| View submissions | `e_approval:submissions:view` (scoped) |

## Implemented vs pending

### Done

- Phase A: Preview tab, field catalog, section grouping, validation properties UI
- Phase B: Canvas layout, multi-column rows, width
- Templates gallery, version timeline (view)
- Workflow tab, save-then-publish
- Full-page request submit, shared compose panel
- P1: Create wizard, 2–4 column rows, mobile properties dialog, catalog drag-and-drop
- P2: Conditional visibility, revision restore, unsaved-changes warning
- P3: Rating / location / tags fields, signature draw pad, plan-gated file uploads
- Post-P3: Revision diff, tenant template admin, admin stats panel
- Requestor UX: draft save (server + local), section progress, inline validation + toasts, focused submit route

### Plan-gated file uploads

Tier comes from central `tenants.plan_tier` (exposed on `GET /e-approval/metadata` as `plan_features`):

| Plan | File fields on forms | Submission uploads |
|------|----------------------|-------------------|
| starter | Not allowed | Blocked |
| professional | Up to 10 fields | Allowed |
| enterprise | Unlimited | Allowed |

### External sharing (public links)

- Form editor **Setup → External sharing** (published forms only)
- Public page: `/public/e-approval/{token}`
- See [e-approval-external-forms.md](./e-approval-external-forms.md)

### Form upgrade policy (versioning)

Submissions snapshot `schema_snapshot_json`, `workflow_snapshot_json`, and `workflow_version_id` at submit time. Detail views render fields from the schema snapshot; workflow advancement for open requests uses the submit-time workflow snapshot (not newly added live steps).

| Scenario | Recommendation |
|----------|----------------|
| Major workflow or field-key change | **Clone** to a new form, publish v2, retire v1 when open requests drain |
| Cosmetic change (labels, options) | In-place save on published form |
| Open submissions + structural edit | Save with **confirm form upgrade** checked, or wait until zero open submissions |

**Retire form:** Setup → Form lifecycle → turn off **Accept new submissions**. The form stays published for history; new internal and public submissions are blocked.

**Safe sync:** Field and workflow rows referenced by in-flight submissions are updated in place or retained — not cascade-deleted.

### Pending

| Priority | Item |
|----------|------|
| — | Visual template builder (JSON editor today); central platform-wide E-Approval analytics |

## Related docs

- Master data: E-Approval → Master data UI
- Tenant settings: Entra / workflow settings under E-Approval → Settings
- Impersonation (support): [tenant-user-impersonation.md](./tenant-user-impersonation.md)
