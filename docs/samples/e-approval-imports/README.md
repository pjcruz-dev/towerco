# E-Approval form import samples (ATC)

Three `atc-form-export` JSON files ready for **E-Approval → Forms → Edit → Import** (or `POST /api/v1/e-approval/forms/import`).

| File | Form |
|------|------|
| `01-document-approval.json` | Document Approval (3 sequential approver fields) |
| `02-iso-approval.json` | ISO Approval (type, department, 3 reviewers) |
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

### Error: “File upload fields require a Professional or Enterprise plan”

Your tenant is still on **starter**. Run the CLI command above or change plan tier in the platform console, then import again.

If you have a different third legacy form, export it from the old app and we can add `03-*.json` to match.
