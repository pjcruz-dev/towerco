# Dynamic Entities

Tenant module for **fully dynamic** ATC operational packs (PM, Procurement, Finance, Ticketing).

Admins define entities and fields at runtime (Manage Fields). Records store values in JSON with filter indexes. Metacoresoft dumps import via artisan — never restore SQL into TowerOS.

## Permissions

| Permission | Purpose |
|---|---|
| `dynamic_entities:view` | List entities / records |
| `dynamic_entities:records:manage` | Create / update / delete records |
| `dynamic_entities:fields:manage` | Manage Fields / field groups |
| `dynamic_entities:entities:manage` | Create / update entities |

Module key: `dynamic_entities` (enable via `TOWEROS_TENANT_ENABLED_MODULES`).

## API (tenant)

- `GET/POST /api/v1/dynamic-entities/entities`
- `GET/PATCH /api/v1/dynamic-entities/entities/{id|slug}`
- `POST .../entities/{entity}/fields`
- `POST .../entities/{entity}/field-groups`
- `PATCH /api/v1/dynamic-entities/field-groups/{group}`
- `PATCH /api/v1/dynamic-entities/fields/{field}`
- `GET/POST .../entities/{entity}/records` (`parent_record_id`, `search`, `filter`, `status`)
- `GET/PATCH/DELETE /api/v1/dynamic-entities/records/{record}`

In-context schema UX (TowerOS chrome, Metacoresoft-style ease): users with `dynamic_entities:fields:manage` can **Add field / New group / Edit field / Edit group** from **View** and **New** via structured groups (drag reorder, collapse, right-click Edit field details). List **Print** uses per-entity `print_settings` (stored JSON + `AtcPrintTemplates` defaults) and opens `/dynamic-entities/records/{id}/print`.

Document layouts (config-driven, not hard-coded per page):

| Layout | Examples |
|---|---|
| `status_report` | SAQ / Power / Postcon / Construction — site assignment, milestones, STAGE/PLAN/ACTUAL workflow |
| `transmittal` | Site Permits → Permit Transmittal |
| `agreement` | Land Leases → Land Lease Agreement Summary |
| `grouped` | Default for all other entities (field groups + optional related expand) |

Seed / refresh print layouts (all active entities):

```bash
php artisan atc:ensure-print-layouts --tenant=<uuid>
```

Import Metacoresoft field groups (`sys_field_groups`) onto entities (Construction Projects → Project / Schedule / Design / Cost, etc.):

```bash
php artisan atc:import-field-groups --tenant=<uuid> --dump=/var/www/html/storage/app/imports/atc-dump.sql
# Optional full replace (also overwrites hand-seeded permit/lease print groups):
php artisan atc:import-field-groups --tenant=<uuid> --dump=... --replace
```

## UI (ATC-style structure)

Nav groups (TowerOS chrome):

- **Sites** — Executive Dashboard, Tower Sites, Construction Projects, SAQ / Power trackers, Permits, Leases, Contracts, Colocations
- **Ticketing** — Ticketing Board, Site Tickets, Categories, Activities (dynamic; separate from native `/ticketing`)
- **Reference** — Vendors, Territories, Project Teams, Milestone Catalogue, Branches, Lessors
- **System Core** — Manage Fields, Field Groups
- **Manage Fields → Arrange Form** — drag fields into 12-column rows and set widths (saved as `field_order` + `column_span`)
- **Manage Fields → ID / Actions / Print** — system list chrome; editable label + In table (hides/shows those columns on the record list). Workflows keeps the button editor.
- **Manage Printables** (`/dynamic-entities/printables`, permission `printables:manage`) — Metacoresoft-style template list **grouped by entity with multiple templates per entity** (New / Edit / Duplicate / Delete), editor at `/printables/edit?entity=…&template=…` with Fields/Images/BIR sidebar, Design/Styles/Source WYSIWYG, `{{system.*}}` / `{{record.*}}` / `{{item.*}}` / `{{theme.accent}}` tokens in `print_settings.templates[]` (`template_html` + `template_css`), orientation, and **PDF Forms Manager** at `/printables/pdf-forms` (tenant upload/rename/delete on `dyn_pdf_forms` + `tenant_files` disk; BIR 2307 remains bundled). Print runtime uses `?template=` (default template when omitted); BIR 2307 still uses overlay path.
- **Manage Fields → Field Groups** — assign fields to form/view groups
- **Roles & Permissions** — General System includes Manage Sidebar (`/admin/sidebar`, `sidebar:manage`) / Notifications / Printables; Data Access supports per-entity **Data Filters** (AND/OR field rules) enforced on record lists
- **Manage Sidebar** — tenant DB menu tree (`sidebar_nav_items`) seeded from workspace defaults; CRUD, reorder, permission keys, role login defaults; live nav via `GET /workspace/sidebar`

Routes:

- `/dynamic-entities` — entity catalog by module pack
- `/dynamic-entities/executive-dashboard` — ATC Executive Dashboard (portfolio / RFTI / build / procurement / capital KPIs)
- `/dynamic-entities/ticketing-board` — Ticketing ops board
- `/dynamic-entities/{slug}` — ATC list (search, LIVE, actions, status pills, pagination)
- `/dynamic-entities/{slug}/new` — create record (in-form Add field / New group / Edit group for fields managers)
- `/dynamic-entities/records/{id}` — detail + related tabs; in-view schema edit for fields managers
- `/dynamic-entities/records/{id}/print` — structured print (e.g. Permit Transmittal, Land Lease Agreement Summary); uses tenant branding, not hardcoded ATC
- `/dynamic-entities/fields` — Manage Fields
- `/dynamic-entities/field-groups` — Field Groups (assign fields to groups)

## Import (Phase 0–6)

Metacoresoft **Excel/CSV export templates** (37 entities, field labels as headers) are mapped in
`AtcMetacoresoftExportMap` + `Data/atc-metacoresoft-export-map.json`. Dynamic Entity **Import**
auto-maps those labels (including UTF-8 BOM) and download templates use the same labels/order.
`materials_&_equipment` → TowerOS `products`.

**Workflow buttons** (Metacoresoft Automation): **Manage Fields → Workflows (pencil) → Automation**.
Configure expandable buttons with WHEN conditions, THEN field updates, colour, confirmation, and
optional role restriction. Related-record / create / email stubs show as Coming soon.
Stored on `workflows.options_json.buttons`. Runtime:
`POST /api/v1/dynamic-entities/records/{id}/workflow-actions/{action}`.
BIR Form 2307 defaults: Post (Draft→Posted), Cancel (Posted→Cancelled).

```bash
# Metadata: entities + fields
php artisan atc:import-meta --tenant=<uuid> --dump=/path/to/dump.sql

# Dry-run (count eligible rows; no writes)
php artisan atc:import-data --tenant=<uuid> --pack=all --dry-run --dump=/path/to/dump.sql

# Phase 1 PM + reference rows
php artisan atc:import-data --tenant=<uuid> --pack=phase1 --dump=/path/to/dump.sql

# Phase 2 procurement rows
php artisan atc:import-data --tenant=<uuid> --pack=phase2 --dump=/path/to/dump.sql

# Phase 3 finance rows
php artisan atc:import-data --tenant=<uuid> --pack=phase3 --dump=/path/to/dump.sql

# Phase 5 ticketing (greenfield seed — dump has no ticket tables)
php artisan atc:seed-ticketing-pack --tenant=<uuid>

# Or all packs (phase5 ETL will report missing tables until dump includes them)
php artisan atc:import-data --tenant=<uuid> --pack=all --dump=/path/to/dump.sql

# Phase 6 — reconcile dump vs dyn_records (exit 0 required before prod cutover)
php artisan atc:verify-import --tenant=<uuid> --pack=all --dump=/path/to/dump.sql
```

Cutover checklist: [atc-etl-cutover-runbook.md](../programs/atc-etl-cutover-runbook.md).

## Reports

| Report | Path | API |
|---|---|---|
| Executive Dashboard | `/dynamic-entities/executive-dashboard` | `.../reports/executive-dashboard` |
| Ticketing Board | `/dynamic-entities/ticketing-board` | `.../reports/ticketing-board` |
| Purchase Monitoring | `/dynamic-entities/reports/purchase-monitoring` | `.../reports/purchase-monitoring` |
| Materials Issued | `/dynamic-entities/reports/materials-issued` | `.../reports/materials-issued` |
| Cost vs Budget | `/dynamic-entities/reports/cost-vs-budget` | `.../reports/cost-vs-budget` |
| Capex & Opex | `/dynamic-entities/reports/capex-opex` | `.../reports/capex-opex` |
| Daily Sales | `/dynamic-entities/reports/daily-sales` | `.../reports/daily-sales` |
| Bank Reconciliation | `/dynamic-entities/reports/bank-reconciliation` | `.../reports/bank-reconciliation` |

## Ticketing note

ATC **Site Tickets** use Dynamic Entities (`module_pack=ticketing`). Native TowerOS **IT helpdesk** (`/ticketing`) and **E-Approval** are unchanged and remain separate products.

## Tables

`dyn_entities`, `dyn_field_groups`, `dyn_fields`, `dyn_records`, `dyn_record_indexes`, `atc_import_id_maps`

## Related

- Program: [docs/programs/atc-erp-parity.md](../programs/atc-erp-parity.md)
- Cutover: [docs/programs/atc-etl-cutover-runbook.md](../programs/atc-etl-cutover-runbook.md)
- E-Approval form builder stays separate
