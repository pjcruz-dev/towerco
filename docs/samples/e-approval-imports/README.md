# E-Approval form import samples (ATC)

Three `atc-form-export` JSON files ready for **E-Approval → Forms → Edit → Import** (or `POST /api/v1/e-approval/forms/import`).

| File | Form |
|------|------|
| `01-document-approval.json` | Document Approval (3 sequential approver fields) |
| `02-iso-approval.json` | ISO Approval (type, department, 3 reviewers) |
| `03-document-control-form.json` | Document Control Form (DCF) — matches paper DCF layout |
| `03-payment-request.json` | Payment Request (manager + finance approver) |

## Revisions from legacy export

- **Document Approval:** fixed field name `appprover_1` → `approver_1` (steps aligned).
- **ISO Approval:** replaced hardcoded user UUID steps with portable `iso_reviewer_2` / `iso_reviewer_3` approver fields; fixed department label typo.
- **Payment Request:** third form (not in your paste) — adjust or replace if you use a different third form.
- **All:** `status` is `draft` so you can re-upload logo and **Publish** when ready; legacy `/uploads/...` logos are omitted (re-upload in form settings).
- Empty `condition` objects use `null` (import normalizes either).

## Import steps

1. Sign in to the tenant (e.g. `http://atc.localhost`).
2. **Plan tier:** forms with `file` fields need **Professional** or **Enterprise** on the central tenant (`tenants.plan_tier`).  
   - **Platform:** **Platform → Tenants** → row menu → **Billing & plan** → set **Professional**.  
   - **CLI (Docker):**  
     `docker compose --env-file .env.docker exec api php artisan tenants:set-plan-tier professional --domain=atc.localhost`  
   - New tenants in **local** `APP_ENV` default to `professional` automatically (see `TOWEROS_TENANT_DEFAULT_PLAN_TIER`).
3. Open **E-Approval → Forms**, create or open a form, use **Import** and select one JSON file per form.
4. Upload brand logo under form settings if needed.
5. Set **Published** when the workflow looks correct.

Document number settings (owner code, template, etc.) can also be configured under **Setup → Document number** when editing a form — no JSON required.

### Document control registry (ISO / QMS)

Forms that should publish to **Documents → Document control** after final approval must include:

```json
"metadata_json": {
  "controlledDocumentSync": {
    "enabled": true,
    "autoRevision": true,
    "documentCodeField": "document_code",
    "fieldMap": {
      "title": "title",
      "document_type": "document_type",
      "department": "department",
      "revision_number": "revision_number",
      "effective_date": "effective_date",
      "next_review_date": "review_date",
      "change_summary": "change_summary"
    },
    "attachmentField": "attachments"
  }
}
```

- **New document** section: title, type, department, effective date, next review date.
- **Revision** section: revision (auto), existing document code, change summary — leave blank when creating a new document.
- **Approval** section: head approval, ISO reviewers, attachments (required for every submission).
- On approval (4A), the record is **published** immediately in the master list.
- Legacy rows: **Import CSV** on `/documents/controlled` (Pass 1), then attach PDFs per revision (Pass 2).

CSV columns: `document_code,title,document_type,department,revision_number,effective_date,next_review_date,change_summary`

### Document Control Form (paper DCF)

Import `03-document-control-form.json` for the Alliance Towers **Document Control Form** layout.

| Paper field | System |
|-------------|--------|
| DCF Number | E-Approval submission **document no.** (template `DCF-{seq:4}`) |
| Document number | `document_code` — registry picker on revision |
| Document title | `document_title` |
| Previous revision | `previous_revision` — auto from registry |
| Current revision | `current_revision` — auto next number |
| Details of change | `details_of_change` |
| Reason for change | `reason_for_change` |
| Requested by (name, date) | Logged-in **requestor** + submission timestamp |
| Authorization signature | Approver step + **e-signature** on approval |
| Logo | Form **brand logo** (Setup → upload Alliance Towers logo) |
| Print/PDF | **Print layout** tab — match paper template |

Requestors use **New document** vs **Revision of existing** on submit. Revisions prefill identification fields from **Documents → Controlled documents**.

### Error: “File upload fields require a Professional or Enterprise plan”

Your tenant is still on **starter**. Run the CLI command above or change plan tier in the platform console, then import again.

If you have a different third legacy form, export it from the old app and we can add `03-*.json` to match.
