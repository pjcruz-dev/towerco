# Removing ProcurementOne and Finance-One (final plan)

Decision: remove entirely. Confirmed by `proposals/procurement-removal-audit.ps1` output: this touches roughly 40 frontend pages, 6 backend services with direct imports, billing entitlements, 4 RBAC roles, an AI Assistant catalog entry, a Ticketing category pack, 8 scheduled commands and 17 tenant migrations. Do this in the order below; do not delete `app/Modules/ProcurementOne` first.

## Decision made: Option A — drop procurement-linked form types entirely
Given the scale (an entire `/procurement` app, not a small sync), keeping E-Forms' procurement-linked fields and print layouts just to point them at nothing would add complexity for no benefit. E-Forms drops purchase order, purchase requisition, GRN and AP invoice handling along with ProcurementOne. If any tenant is actively using these E-Forms form types today, migrate their data first (export or freeze, per your data policy) — this plan does not cover that.

## Step 1 — backend: remove the 6 E-Forms integration points
1. `ApprovalDecisionService`: remove the `ProcurementPrEApprovalHookService` constructor param and its two call sites.
2. `EApprovalSubmissionService`: remove the same dependency and its call site(s).
3. `EApprovalMasterDataService`: remove `ProcurementVendorRegistryService` and the vendor-registry lookup it powers.
4. `EApprovalVendorRegistrationMasterDataService`: remove `ProcurementVendorSyncService` and whatever it syncs.
5. `EApprovalPurchaseRequisitionService`: delete outright (only exists to read `ProcurementPo`/`ProcurementPr`). Then delete its two callers, which become dead once it's gone:
   - `EApprovalFinanceProcurementKpiService` (dashboard KPI cards: open/unliquidated cash advances, PRs without PO) — check whether the cash-advance counts should survive without the PR-without-PO count; if so, keep a smaller service for just those two, otherwise delete it whole.
   - `EApprovalFinanceProcurementPolicyService` (overspend policy for POs and liquidations) — same check for `EApprovalSubmissionParentLinkService`, `EApprovalSettingsShowController`, `EApprovalMetadataService`, which all inject it.
6. `EApprovalSubmissionPrintService`: remove `ProcurementPoPrintEnrichmentService` and `ProcurementPrPrintEnrichmentService`.
7. Route: `EApprovalPurchaseRequisitionOpenController` (checks `procurement_one:documents:create`) — delete the controller and its route.
8. E-Forms' own procurement-rendering files (not ProcurementOne's, but exist only to serve it): `components/e-approval/e-approval-procurement-link-field.tsx`, `.../e-approval-procurement-form-layout.tsx`, `.../print/procurement-print-shell.tsx`, `modules/e-approval/procurement-link-fields.ts`, `.../procurement-document-layout.ts`, `.../purchase-order-template.ts`, `.../purchase-requisition-template.ts`. Delete these and their imports in `e-approval-field-renderer.tsx`, `e-approval-form-fields-layout.tsx`, `e-approval-generic-form-print-view.tsx`, `e-approval-print-layout-editor.tsx`.

## Step 2 — Ticketing's light coupling
- `TicketingCategoryPackCatalog::procurementOneCategories()` and the `PACK_PROCUREMENT_ONE` entry — delete.
- `TicketingRelatedTickets sourceModule="procurement_one"` usages in the (about-to-be-deleted) procurement frontend pages go away with those pages; confirm no Ticketing-side code still references `"procurement_one"` as a valid source module after Step 4.

## Step 3 — billing, RBAC and AI Assistant
1. `TenantPlanEntitlementsService::procurementOneFeatures()` and its 9 feature flags (`goods_receipt`, `advanced_numbering`, `inventory`, `ap_invoices`, `payment_tracking`, `rfq_sourcing`, `vendor_contracts`, `reporting_exports`, `enabled`) — delete the method; check every plan definition (wherever plan tiers are configured) for a `procurement_one` key and remove it.
2. `TenantBillingReadService`: remove `$procurementSnapshot` / the `procurement_one` key in its response.
3. `TenantRbacModuleRoleTemplates::procurementRoles()` and the 4 roles (`procurement_viewer`, `procurement_contributor`, `procurement_operator`, `procurement_admin`) — delete. Remove them from `TenantRbacSystemRoles` too. Any tenant user currently holding one of these roles loses it silently unless you write a migration to reassign them first — decide what they should become (likely nothing, since the module is gone).
4. `TenantRbacPermissionCatalog`: remove the `procurement_one` permission group and its 8 permissions (`view`, `documents:create`, `documents:manage`, `settings:manage`, `vendors:view`, `vendors:manage`, `inventory:view`, `inventory:manage`).
5. `RoleCatalogService`: remove the `'procurement_' => 'procurement_one'` role-prefix mapping and the `finance_one` check that depends on it (`isRoleAssignableForEnabledModules`).
6. `AssistantModuleSuggestionCatalog`: remove the `procurement_one` entry so the AI assistant stops suggesting a module that no longer exists.
7. `TenantEnabledModulesResolver`: remove `procurement_one` and `finance_one` from `TOGGLEABLE_MODULES`, `MODULE_LABELS`, and from the default `platformModules()` list.

## Step 4 — frontend: delete the procurement app
1. Every route under `frontend/app/(platform)/procurement/**` and `frontend/app/(print)/procurement/**` (ap-invoices, budget, contracts, grns, inventory, payments, pos, prs — full CRUD trees per the audit).
2. `frontend/app/(platform)/finance/finance-one-dashboard-page-client.tsx` and its route — it exists only to show ProcurementOne data.
3. `frontend/components/procurement-one/**`, `frontend/modules/procurement-one/**`, `frontend/lib/procurement-one/**`, `frontend/lib/api/modules/procurement-one-api.ts`.
4. `frontend/app/(platform)/billing/billing-page-client.tsx`: remove the `ProcurementPlanFeatures` import and every `procurement_one` reference (plan features, entitlements, the "PROJECT-ONE, and Procurement-One" description line).
5. `frontend/app/(platform)/governance/audit/workspace-audit-page-client.tsx`: remove the `procurement_one` filter option.
6. `permissions.procurementOne*` constants (view, documentsCreate, documentsManage, settingsManage, inventoryView, inventoryManage) — remove once nothing references them (Step 4.1-4.5 first).

## Step 5 — backend module and scheduled work
1. Delete `backend/app/Modules/ProcurementOne` entirely.
2. Delete the 8 command classes: `ProcurementExportScheduledRunCommand`, `ProcurementOneMigratePrsCommand`, `ProcurementOneMigrateVendorsCommand`, `ProcurementOnePurgeTransactionalDataCommand`, `ProcurementOneRepairLineMetadataCommand`, `ProcurementRfqAutoCloseCommand`, `ProcurementRfqPruneDuplicateDraftsCommand`, `ProcurementRfqReminderCommand`.
3. Remove the 3 `bootstrap/app.php` schedule lines: `procurement:export-run-scheduled`, `procurement:rfq-reminders`, `procurement:rfq-auto-close`.

## Step 6 — tenant-database migrations (data, not just code)
The 17 tenant migrations that created ProcurementOne's schema stay in history (never rewrite migrations that already ran in production) — see `tenant-safe-migration`:
`create_procurement_one_settings_table`, `create_procurement_vendor_tables`, `create_procurement_pr_tables`, `create_procurement_po_tables`, `procurement_lifecycle_phase6`, `create_procurement_grn_tables`, `procurement_inventory_phase8`, `procurement_budget_phase9`, `procurement_ap_invoice_phase10`, `procurement_payment_phase11`, `procurement_rfq_phase12`, `procurement_contracts_phase13`, `procurement_rfq_vendor_portal_phase1/2/3`, `procurement_rfq_quote_basis_phase_b`, `procurement_rfq_bid_version_quote_basis_phase_b`.
Write ONE new migration that drops every table these created, run it with `tenants:migrate` across all tenant databases, verify row counts were zero or acceptable to lose first (export a backup per `tenant-safe-migration` if any tenant has real procurement data).

## Step 7 — verify
- `npm run test:backend`, `npm run test:frontend`, `npm run lint`, `npm run typecheck`.
- Grep the whole repo case-insensitively for `procurement` and `finance_one`, outside `docs/archives` — expect zero hits in `backend/app` and `frontend` (some may remain in `docs/` and historical migrations, which is fine).
- `npm run ui:drift` should show no new drift from the deletions (fewer files, not more).
- Manually create and approve one ordinary (non-procurement) E-Forms submission end to end to confirm nothing in the approval pipeline broke from removing the two hook injections.
- Confirm `TOWEROS_TENANT_ENABLED_MODULES` in every environment no longer lists `procurement_one` or `finance_one`.
