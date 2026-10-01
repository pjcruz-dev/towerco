# Audit module gating (read-only)

Goal: finish checking what switching a module off really blocks, for the active modules only (Ticketing, E-Forms, DocExtract, Document Control). Do not edit files.

Verified from source (do not re-derive):
- `tenant.module` = `EnsureTenantModule`: aborts with 403 "This module is not enabled for your workspace." It appears in `backend/routes` on `doc_extract`, `billings`, `ai_assistant`, `documents`, `document_register`. Ticketing and E-Forms are not route-gated.
- Other enforcement: `TenantRbacPermissionCatalog` and `TenantModuleRbacSyncService` use the enabled list, some scheduled commands check it (documents, e-approval reports), and the frontend filters navigation (`isTenantModuleEnabled`).
- `ticketing:sla-run` and `e-approval:sla-run` run for every tenant.

The product set is `core,team_access,e_approval,ticketing,doc_extract,document_register`.

Still unknown. Report as a table (item | finding | risk):
1. Line numbers of the `tenant.module:` usages in `backend/routes/api/v1/tenant.php`. Which routes does each group wrap? Are all `ControlledDocument*` routes inside the `document_register` group?
2. For Ticketing and E-Forms, what actually stops a user from calling the API once the module is off? Check the controller `can()` checks and `TenantRbacPermissionCatalog`: are the module's permissions removed from roles when it is disabled, and is that immediate or only on an explicit sync?
3. Frontend: can someone open a disabled module's page by typing the URL? Look for a route guard in layouts under `app/(platform)` and in `lib/tenant/enabled-modules.ts`.

Finish with the exact value to set and the routes an operator could still reach with a module off.
