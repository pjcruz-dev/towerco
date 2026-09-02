# ATC ERP parity (Dynamic Entities)

Bring Alliance Towers Metacoresoft ERP capability into TowerOS using the **Dynamic Entities** platform.

## Decisions

- TowerOS UI chrome (not Metacoresoft skin)
- All PM / Procurement / Finance / Ticketing fields are **dynamic**
- Keep E-Approval unchanged
- ETL from Metacoresoft dump — never SQL restore into TowerOS

## Status

| Phase | Scope | Status |
|---|---|---|
| 0 | Dynamic Entity engine + Manage Fields + meta import | Done |
| 1 | PM pack data import + ATC list/detail/nav + related tabs + field groups | Done |
| 2 | Procurement pack + Purchase Monitoring | Done |
| 3 | Finance pack + Capex/Opex, Cost vs Budget, Materials, Daily Sales, Bank Rec | Done (foundation) |
| 4 | ATC Executive Dashboard | Done |
| 5 | Dynamic Ticketing | Done |
| 6 | Cutover / hardening | Done |

## Phase 3 delivered

- `atc:import-data --pack=phase3` — CoA, GL, banks, sales, fixed assets, site operating costs, …
- Reports:
  - `/dynamic-entities/reports/materials-issued`
  - `/dynamic-entities/reports/cost-vs-budget`
  - `/dynamic-entities/reports/capex-opex`
  - `/dynamic-entities/reports/daily-sales`
  - `/dynamic-entities/reports/bank-reconciliation`
- Nav: **Cost & Capital**, **Finance**, Daily Sales + Bank Rec under **Procurement**

## Phase 4 delivered

- API: `GET /api/v1/dynamic-entities/reports/executive-dashboard`
- UI: `/dynamic-entities/executive-dashboard` (LIVE KPIs, portfolio charts, RFTI aging, attention + quick links)
- Nav: **Executive Dashboard** first under **Sites**
- Aggregates dyn_records across PM / construction / procurement / finance packs (no Metacoresoft restore)

## Phase 5 delivered

- Greenfield Dynamic Ticketing pack (current Metacoresoft dump has **no** ticket entities/tables)
- Seed: `php artisan atc:seed-ticketing-pack --tenant=<uuid>`
  - Entities: `ticket_categories`, `site_tickets`, `ticket_activities` (`module_pack=ticketing`)
  - Default categories + Tower Sites related tab **Site Tickets**
- Ops board: `/dynamic-entities/ticketing-board` + `GET .../reports/ticketing-board`
- Nav: **Ticketing** (board, site tickets, categories, activities)
- Native `/ticketing` IT helpdesk and **E-Approval remain unchanged**

## Phase 6 delivered

- Cutover runbook: [atc-etl-cutover-runbook.md](./atc-etl-cutover-runbook.md)
- `atc:verify-import` — dump eligible rows vs `dyn_records` (ETL-sourced) reconcile
- `atc:import-data --dry-run` — count only
- Structured audit events: `atc.import_meta.completed`, `atc.import.dry_run`, `atc.import.completed`, `atc.verify.completed`
- Staging → production checklist; backup reminder; never SQL-restore Metacoresoft dump

## Ops

```bash
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:import-data \
  --tenant=<uuid> --pack=phase3 \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql
```

Phase 3 import is large (GL ~7k + site opex ~7k + fixed assets ~1k). Expect several minutes.

```bash
# Phase 5 — seed (preferred; dump has no ticket tables)
docker exec -it toweros-api php artisan atc:seed-ticketing-pack --tenant=<uuid>

# Phase 6 — reconcile
MSYS_NO_PATHCONV=1 docker exec -it toweros-api php artisan atc:verify-import \
  --tenant=<uuid> --pack=all \
  --dump=/var/www/html/storage/app/imports/atc-dump.sql
```

See [dynamic-entities.md](../modules/dynamic-entities.md) and [atc-etl-cutover-runbook.md](./atc-etl-cutover-runbook.md).
