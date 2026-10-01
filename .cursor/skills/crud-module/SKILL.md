---
name: crud-module
description: Scaffold a complete TowerOS feature for the active modules (Ticketing, E-Forms/EApproval, DocExtract, Documents) (tenant migration, model, permissions, service, controller, routes, PHPUnit tests, frontend API client, types, list + drawer screens). Use whenever the user asks to add a new entity, resource, CRUD screen, module, or "manage X" feature, even if they do not say "module".
---

# CRUD feature scaffold (TowerOS)

Only build new entities/screens inside Ticketing, EApproval (E-Forms), DocExtract or Documents (Document Control / `document_register`), or in the shared `app/Core` / platform plumbing they depend on. Do not scaffold features in any other product module.

Do not invent structure. Read first:
1. `backend/app/Core` (`Services/AbstractDomainService`, `Repositories/AbstractEloquentRepository`, Http, Exceptions, Support).
2. The Ticketing module as reference: `backend/app/Modules/Ticketing` (`Http/Controllers/V1` — one class per action, `Services`, `Support`, `Notifications`), its models in `backend/app/Models/Ticketing*.php`, and its scheduled commands in `backend/app/Console/Commands/Ticketing`. EApproval, Documents and DocExtract keep models in `app/Modules/<Module>/Models/`. Copy that module's controller: `can()` check, `$request->validate()`, Service, `AbstractApiController` response. Do not add FormRequest, Policy or API Resource classes.
3. The matching frontend: route under `app/(platform)/<route>`, UI in `components/<area>`, helpers in `lib/<area>`, types in `modules/<module>/types.ts`, client in `lib/api/modules/<module>-api.ts`.
4. `docs/modules` and `docs/architecture` for the module's spec.

## Backend
1. **Migration** in `database/migrations/tenant` (tenant data) or `database/migrations` (central). Ordered-UUID key, FKs, an index per filter/sort column. See `tenant-safe-migration`.
2. **Model** in the folder that module already uses (`app/Models` for Ticketing, `Modules/<Module>/Models` for EApproval, Documents, DocExtract). `$fillable`, casts, enums, relations. No business logic.
3. **Permissions** `<module>:<resource>:<action>`, seeded and attached to default roles. Match existing strings in that module.
4. **Service** `<Module><Thing>Service` extending `App\Core\Services\AbstractDomainService`: use its `transaction()` for multi-write work, its `logInfo`/`logWarning`/`logError` for logging, an audit entry, and a domain event after commit. Add a repository only for complex/shared queries.
5. **Plan gating**: if the feature is plan-limited, go through `<Module>PlanFeaturesService`.
6. **Controller** in `Http/Controllers/V1`, extending `AbstractApiController`: `abort_unless` on the permission, `$request->validate()`, call the Service, return `ok` / `created` / `okWithMeta`. Routes in `routes/api/v1` with auth + tenant + throttle.
7. **Tests** next to the module's existing tests (`tests/Feature/<Module>`, `tests/Unit/<Module>`): happy path, 422, 401, 403, cross-tenant 404, audit entry. Use `tests/Support/Concerns`.

## Frontend
1. `lib/api/modules/<module>-api.ts` (use the tenant `client.ts`) and shared shapes in `modules/<module>/types.ts`. Use `paginated.ts` for lists and `error.ts` for errors.
2. Feature helpers in `lib/<area>`, hooks in `hooks/`, UI in `components/<area>`.
3. List screen from `components/ui/data-table*.tsx` (see `data-table` skill). Create/edit in a `sheet.tsx` drawer with `components/forms` and react-hook-form + zod. Detail drawer, status chips.
4. Route under `app/(platform)/<route>` with `loading.tsx` and `error.tsx`. Navigation entry guarded by `lib/rbac`.
5. Loading, empty, error and 403 states. Light and dark verified.

## Finish
`cd backend; php vendor/bin/pint --dirty`, `npm run test:backend` (plus the module scenario if any), `cd frontend; npm run lint; npm run typecheck; npm run test:frontend`. Then delegate to `tenant-isolation-auditor` and `ui-ux-reviewer`.
