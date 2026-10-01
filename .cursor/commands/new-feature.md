# New feature (end to end)

Build the feature I describe after this command, across backend, frontend and tests.

1. **Understand.** Read `.cursor/rules/global.mdc`, the relevant rules, and one existing module as the pattern to copy. If a requirement is ambiguous, ask at most 3 questions. Otherwise state your assumptions.
2. **Plan first** (short, before coding): module, tables, endpoints, permissions, events/jobs, screens. Wait for my OK if the change adds tables or touches auth/tenancy.
3. **Implement in this order:** migration (tenant or central), model, permissions, Service (repository only if the module already uses one), controller (`can()` check, `$request->validate()`, `AbstractApiController` response) + routes, PHPUnit tests, frontend API client (`lib/api/modules`) + `types.ts`, feature helpers/hooks, screens with loading/empty/error states.
4. **Verify.** Run `cd backend; php vendor/bin/pint --dirty`, `npm run test:backend` (plus the module scenario if one exists), then `cd frontend; npm run lint; npm run typecheck; npm run test:frontend`. Fix failures and report the actual results.
5. **Deliver** with these sections:
   - Folder structure and full file paths created or changed
   - Database schema (tables, columns, indexes, FKs)
   - API routes (method, path, permission)
   - Validation and authorization rules
   - Error handling and logging/audit strategy
   - UI notes: responsive behaviour, states, dark/light checked
   - Short step-by-step explanation of the design choices

Use the `crud-module`, `data-table` and `tenant-safe-migration` skills when they apply. Finish by delegating to the `tenant-isolation-auditor` and `ui-ux-reviewer` subagents on your changes.
